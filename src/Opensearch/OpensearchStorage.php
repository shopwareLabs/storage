<?php

namespace Shopware\Storage\Opensearch;

use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
use OpenSearchDSL\Aggregation\AbstractAggregation;
use OpenSearchDSL\Aggregation\Bucketing\FilterAggregation;
use OpenSearchDSL\Aggregation\Bucketing\NestedAggregation;
use OpenSearchDSL\Aggregation\Bucketing\TermsAggregation;
use OpenSearchDSL\Aggregation\Metric\MaxAggregation;
use OpenSearchDSL\Aggregation\Metric\AvgAggregation;
use OpenSearchDSL\Aggregation\Metric\MinAggregation;
use OpenSearchDSL\Aggregation\Metric\SumAggregation;
use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\TermLevel\ExistsQuery;
use OpenSearchDSL\Query\TermLevel\RangeQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Query\TermLevel\TermsQuery;
use OpenSearchDSL\Query\TermLevel\WildcardQuery;
use OpenSearchDSL\Search;
use OpenSearchDSL\Sort\FieldSort;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Aggregation\Type\Aggregation;
use Shopware\Storage\Common\Aggregation\Type\Avg;
use Shopware\Storage\Common\Aggregation\Type\Count;
use Shopware\Storage\Common\Aggregation\Type\Distinct;
use Shopware\Storage\Common\Aggregation\Type\Max;
use Shopware\Storage\Common\Aggregation\Type\Min;
use Shopware\Storage\Common\Aggregation\Type\Sum;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Exception\NotSupportedByEngine;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Filter\Paging\Limit;
use Shopware\Storage\Common\Filter\Result;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Filter\Operator\AndOperator;
use Shopware\Storage\Common\Filter\Operator\NandOperator;
use Shopware\Storage\Common\Filter\Operator\NorOperator;
use Shopware\Storage\Common\Filter\Operator\Operator;
use Shopware\Storage\Common\Filter\Operator\OrOperator;
use Shopware\Storage\Common\Filter\Paging\Page;
use Shopware\Storage\Common\Filter\Type\Any;
use Shopware\Storage\Common\Filter\Type\Contains;
use Shopware\Storage\Common\Filter\Type\Equals;
use Shopware\Storage\Common\Filter\Type\Filter;
use Shopware\Storage\Common\Filter\Type\Gt;
use Shopware\Storage\Common\Filter\Type\Gte;
use Shopware\Storage\Common\Filter\Type\Lt;
use Shopware\Storage\Common\Filter\Type\Lte;
use Shopware\Storage\Common\Filter\Type\Neither;
use Shopware\Storage\Common\Filter\Type\Not;
use Shopware\Storage\Common\Filter\Type\Prefix;
use Shopware\Storage\Common\Filter\Type\Suffix;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Schema\Field;
use Shopware\Storage\Common\Schema\FieldsAware;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\ListField;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Common\Total;

class OpensearchStorage implements Storage, FilterAware, AggregationAware
{
    public function __construct(
        private readonly AggregationCaster $caster,
        private readonly Client $client,
        private readonly Hydrator $hydrator,
        private readonly Collection $collection
    ) {}

    public function setup(): void
    {
        $properties = $this->setupProperties($this->collection);

        if (!$this->exists()) {
            $this->client->indices()->create([
                'index' => $this->collection->name,
            ]);
        }

        $this->client->indices()->putMapping([
            'index' => $this->collection->name,
            'body' => [
                'properties' => $properties,
            ],
        ]);

        $this->client->putScript([
            'id' => 'translated',
            'body' => [
                'script' => [
                    'lang' => 'painless',
                    'source' => file_get_contents(__DIR__ . '/scripts/translated.groovy'),
                ],
            ],
        ]);
    }

    private function exists(): bool
    {
        return $this->client->indices()->exists(['index' => $this->collection->name]);
    }

    /**
     * @return array<string, mixed>
     */
    private function setupProperties(FieldsAware $fields): array
    {
        $properties = [];
        foreach ($fields->fields() as $field) {
            $definition = $this->getType($field);

            if ($field instanceof FieldsAware) {
                $definition['properties'] = $this->setupProperties($field);
            }

            $properties[$field->name] = $definition;
        }

        return $properties;
    }

    public function mget(array $keys, StorageContext $context): Documents
    {
        $data = $this->client->mget([
            'index' => $this->collection->name,
            'body' => [
                'ids' => $keys,
            ],
        ]);

        $documents = [];
        foreach ($data['docs'] as $doc) {
            if (!array_key_exists('_source', $doc)) {
                continue;
            }

            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $doc['_source'],
                context: $context
            );
        }

        return new Documents($documents);
    }

    public function get(string $key, StorageContext $context): ?Document
    {
        try {
            $result = $this->client->get([
                'index' => $this->collection->name,
                'id' => $key,
            ]);
        } catch (Missing404Exception) {
            return null;
        }

        return $this->hydrator->hydrate(
            collection: $this->collection,
            data: $result['_source'],
            context: $context
        );
    }

    public function remove(array $keys): void
    {
        $documents = array_map(function ($key) {
            return ['delete' => ['_id' => $key]];
        }, $keys);

        $arguments = [
            'index' => $this->collection->name,
            'body' => $documents,
        ];

        $this->client->bulk($arguments);
    }

    public function store(Documents $documents): void
    {
        $body = [];
        foreach ($documents as $document) {
            $body[] = [
                'index' => [
                    '_index' => $this->collection->name,
                    '_id' => $document->key,
                ],
            ];

            $body[] = $document->encode();
        }

        if (empty($body)) {
            return;
        }

        $response = $this->client->bulk(['body' => $body]);

        if ($response['errors'] === true) {
            dd($response);
        }
    }

    public function filter(
        Criteria $criteria,
        StorageContext $context
    ): Result {
        $search = new Search();

        $this->handlePaging(criteria: $criteria, search: $search);

        $this->handlePrimaries(criteria: $criteria, search: $search);

        $this->handleFilters(criteria: $criteria, search: $search, context: $context);

        $this->handleSorting(criteria: $criteria, search: $search);

        $parsed = $search->toArray();

        $params = [
            'index' => $this->collection->name,
            '_source' => true,
            'track_total_hits' => $criteria->total !== Total::NONE,
            'body' => $parsed,
        ];

        $result = $this->client->search($params);

        $documents = [];
        foreach ($result['hits']['hits'] as $hit) {
            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $hit['_source'],
                context: $context
            );
        }

        return new Result(
            elements: $documents,
            total: $this->getTotal($criteria, $result),
        );
    }

    public function aggregate(array $aggregations, Criteria $criteria, StorageContext $context): array
    {
        $search = new Search();

        $search->setSize(0);

        $this->handlePrimaries(criteria: $criteria, search: $search);

        $this->handleFilters(criteria: $criteria, search: $search, context: $context);

        $this->handleAggregations(aggregations: $aggregations, search: $search, context: $context);

        $parsed = $search->toArray();

        $params = [
            'index' => $this->collection->name,
            'body' => $parsed,
        ];

        try {
            $response = $this->client->search($params);
        } catch (\Throwable $e) {
            dump(json_decode($e->getMessage(), true));
            throw $e;
        }

        $result = [];

        foreach ($aggregations as $aggregation) {
            $type = SchemaUtil::type(collection: $this->collection, accessor: $aggregation->field);

            $translated = SchemaUtil::translated(collection: $this->collection, accessor: $aggregation->field);

            $value = $response['aggregations'][$aggregation->name];

            // nested aggregation? get the value from the nested path
            $value = $value[$aggregation->name] ?? $value;

            if ($aggregation instanceof Avg) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $value['value_as_string'] ?? $value['value']
                );
                continue;
            }

            if ($aggregation instanceof Min) {
                $data = $value['value_as_string'] ?? $value['value'];

                if ($type === FieldType::DATETIME && $translated) {
                    $data = date('Y-m-d H:i:s', $data / 1000);
                }

                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $data
                );
                continue;
            }

            if ($aggregation instanceof Max) {
                $data = $value['value_as_string'] ?? $value['value'];

                if ($type === FieldType::DATETIME && $translated) {
                    $data = date('Y-m-d H:i:s', $data / 1000);
                }

                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $data
                );
                continue;
            }

            if ($aggregation instanceof Sum) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $value['value_as_string'] ?? $value['value']
                );
                continue;
            }

            if ($aggregation instanceof Count) {
                $values = array_map(function ($bucket) {
                    return [
                        'key' => $bucket['key_as_string'] ?? $bucket['key'],
                        'count' => $bucket['doc_count'],
                    ];
                }, $value['buckets']);

                $values = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $values
                );

                $result[$aggregation->name] = $values;

                continue;
            }

            if ($aggregation instanceof Distinct) {

                $values = array_map(function ($bucket) {
                    return $bucket['key_as_string'] ?? $bucket['key'];
                }, $value['buckets']);

                $values = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $values
                );

                $result[$aggregation->name] = $values;

                continue;
            }

            throw new \LogicException(sprintf('Unsupported aggregation type %s', $aggregation::class));
        }

        return $result;
    }

    private function handlePrimaries(Criteria $criteria, Search $search): void
    {
        if ($criteria->primaries) {
            $search->addQuery(
                new TermsQuery(field: '_id', terms: $criteria->primaries)
            );
        }
    }

    private function handleFilters(Criteria $criteria, Search $search, StorageContext $context): void
    {
        $queries = $this->parseRootFilter(
            filters: $criteria->filters,
            context: $context
        );

        foreach ($queries as $query) {
            $search->addQuery(query: $query, boolType: BoolQuery::FILTER);
        }
    }

    private function handlePaging(Criteria $criteria, Search $search): void
    {
        if ($criteria->paging instanceof Page) {
            $search->setFrom(($criteria->paging->page - 1) * $criteria->paging->limit);
            $search->setSize($criteria->paging->limit);
        } elseif ($criteria->paging instanceof Limit) {
            $search->setSize($criteria->paging->limit);
        }
    }

    private function handleSorting(Criteria $criteria, Search $search): void
    {
        if (!$criteria->sorting) {
            return;
        }

        foreach ($criteria->sorting as $sorting) {
            $search->addSort(new FieldSort(
                field: $sorting->field,
                order: $sorting->order
            ));
        }
    }

    /**
     * @param array<Operator|Filter> $filters
     * @return array<BuilderInterface>
     */
    private function parseRootFilter(array $filters, StorageContext $context): array
    {
        $queries = [];

        foreach ($filters as $filter) {
            if ($filter instanceof Operator) {
                $queries[] = $this->parseOperator($filter, $context);

                continue;
            }

            $path = $this->getNestedPath($filter->field);

            $parsed = $this->parse(filter: $filter, context: $context);

            // object field? created nested
            if ($path !== null) {
                $queries[] = new NestedQuery(path: $path, query: $parsed);

                continue;
            }

            $queries[] = $parsed;
        }

        return $queries;
    }

    private function translatedQuery(\Closure $factory, Filter $filter, StorageContext $context): BuilderInterface
    {
        $queries = [];
        $before = [];

        foreach ($context->languages as $index => $languageId) {
            if (array_key_first($context->languages) === $index) {
                $queries[] = new BoolQuery([
                    BoolQuery::MUST => [
                        new ExistsQuery(field: $filter->field . '.' . $languageId),
                        $factory(
                            $filter->field . '.' . $languageId,
                            $filter->value
                        ),
                    ],
                ]);

                $before[] = $languageId;

                continue;
            }

            $bool = new BoolQuery();

            foreach ($before as $id) {
                $bool->add(
                    new ExistsQuery(field: $filter->field . '.' . $id),
                    BoolQuery::MUST_NOT
                );
            }

            $bool->add(
                $factory($filter->field . '.' . $languageId, $filter->value)
            );

            $before[] = $languageId;
            $queries[] = $bool;
        }

        $source = new BoolQuery([
            BoolQuery::SHOULD => $queries,
        ]);

        $source->addParameter('minimum_should_match', 1);

        return $source;
    }

    private function parse(Filter|Operator $filter, StorageContext $context): BuilderInterface
    {
        if ($filter instanceof Operator) {
            return $this->parseOperator($filter, $context);
        }

        return $this->parseFilter($filter, $context);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function getTotal(Criteria $criteria, array $result): int|null
    {
        if ($criteria->total === Total::NONE) {
            return null;
        }

        if (!array_key_exists('hits', $result) || !is_array($result['hits'])) {
            throw new \LogicException('Missing hits key in opensearch result set');
        }

        if (!array_key_exists('total', $result['hits']) || !is_array($result['hits']['total'])) {
            throw new \LogicException('Missing hits.total key in opensearch result set');
        }

        if (!array_key_exists('value', $result['hits']['total'])) {
            throw new \LogicException('Missing hits.total.value key in opensearch result set');
        }

        return (int) $result['hits']['total']['value'];
    }

    private function parseOperator(Operator $operator, StorageContext $context): BoolQuery
    {
        $nested = array_map(function (Filter|Operator $query) use ($context) {
            return $this->parse(filter: $query, context: $context);
        }, $operator->filters);

        if ($operator instanceof AndOperator) {
            return new BoolQuery([
                BoolQuery::MUST => $nested,
            ]);
        }

        if ($operator instanceof OrOperator) {
            $bool = new BoolQuery([
                BoolQuery::SHOULD => $nested,
            ]);

            $bool->addParameter('minimum_should_match', 1);

            return $bool;
        }

        if ($operator instanceof NandOperator) {
            return new BoolQuery([
                BoolQuery::MUST_NOT => $nested,
            ]);
        }

        if ($operator instanceof NorOperator) {
            $bool = new BoolQuery([
                BoolQuery::MUST_NOT => $nested,
            ]);

            $bool->addParameter('minimum_should_match', 1);

            return $bool;
        }

        throw new \LogicException(sprintf('Unsupported operator %s', $operator::class));
    }

    private function parseFilter(Filter $filter, StorageContext $context): BuilderInterface
    {
        $value = $filter->value;

        $translated = SchemaUtil::translated(collection: $this->collection, accessor: $filter->field);

        $inner = SchemaUtil::type(collection: $this->collection, accessor: $filter->field, innerType: true);

        if ($inner === FieldType::DATETIME && $filter instanceof Contains) {
            throw new NotSupportedByEngine('', sprintf('Can only use wildcard queries on keyword and text fields - not on [%s] which is of type [date]', $filter->field));
        }

        // create an inline function which generates me a BuilderInterface, based on the given filter
        $factory = function (\Closure $factory) use ($filter) {
            return $factory($filter->field, $filter->value);
        };

        if ($translated) {
            $factory = function (\Closure $generator) use ($filter, $context) {
                return $this->translatedQuery($generator, $filter, $context);
            };
        }

        if ($value === null && $filter instanceof Equals) {
            return $factory(function (string $field) {
                return new BoolQuery([
                    BoolQuery::MUST_NOT => [new ExistsQuery(field: $field)],
                ]);
            });
        }

        if ($value === null && $filter instanceof Not) {
            return $factory(function (string $field) {
                return new ExistsQuery(field: $field);
            });
        }

        if ($filter instanceof Equals) {
            return $factory(function (string $field, mixed $value) {
                return new TermQuery(
                    field: $field,
                    value: $value
                );
            });
        }

        if ($filter instanceof Not) {
            return $factory(function (string $field, mixed $value) {
                return new BoolQuery([
                    BoolQuery::MUST_NOT => new TermsQuery(
                        field: $field,
                        terms: [$value]
                    ),
                ]);
            });
        }

        if ($filter instanceof Any) {
            return $factory(function (string $field, mixed $value) {
                return new TermsQuery(
                    field: $field,
                    terms: $value
                );
            });
        }

        if ($filter instanceof Neither) {
            return $factory(function (string $field, mixed $value) {
                return new BoolQuery([
                    BoolQuery::MUST_NOT => new TermsQuery(
                        field: $field,
                        terms: $value
                    ),
                ]);
            });
        }

        if ($filter instanceof Contains) {
            return $factory(function (string $field, mixed $value) {
                return new WildcardQuery(
                    field: $field,
                    value: '*' . $value . '*'
                );
            });
        }

        if ($filter instanceof Prefix) {
            return $factory(function (string $field, mixed $value) {
                return new WildcardQuery(
                    field: $field,
                    value: $value . '*'
                );
            });
        }

        if ($filter instanceof Suffix) {
            return $factory(function (string $field, mixed $value) {
                return new WildcardQuery(
                    field: $field,
                    value: '*' . $value
                );
            });
        }

        if ($filter instanceof Gt) {
            return $factory(function (string $field, mixed $value) {
                return new RangeQuery(
                    field: $field,
                    parameters: ['gt' => $value]
                );
            });
        }

        if ($filter instanceof Gte) {
            return $factory(function (string $field, mixed $value) {
                return new RangeQuery(
                    field: $field,
                    parameters: ['gte' => $value]
                );
            });
        }

        if ($filter instanceof Lt) {
            return $factory(function (string $field, mixed $value) {
                return new RangeQuery(
                    field: $field,
                    parameters: ['lt' => $value]
                );
            });
        }

        if ($filter instanceof Lte) {
            return $factory(function (string $field, mixed $value) {
                return new RangeQuery(
                    field: $field,
                    parameters: ['lte' => $value]
                );
            });
        }

        throw new \LogicException(sprintf('Unsupported filter type %s', $filter::class));
    }

    private function parseAggregation(Aggregation $aggregation, StorageContext $context): AbstractAggregation
    {
        $type = SchemaUtil::type(collection: $this->collection, accessor: $aggregation->field);

        if ($aggregation instanceof Max) {
            if (in_array($type, [FieldType::STRING, FieldType::TEXT, FieldType::LIST], true)) {
                throw new NotSupportedByEngine('45', 'Max aggregation is not supported for string, text or list fields');
            }

            return new MaxAggregation(
                name: $aggregation->name,
                field: $aggregation->field
            );
        }

        if ($aggregation instanceof Min) {
            if (in_array($type, [FieldType::STRING, FieldType::TEXT, FieldType::LIST], true)) {
                throw new NotSupportedByEngine('45', 'Min aggregation is not supported for string, text or list fields');
            }

            return new MinAggregation(
                name: $aggregation->name,
                field: $aggregation->field
            );
        }

        if ($aggregation instanceof Sum) {
            if (in_array($type, [FieldType::STRING, FieldType::TEXT, FieldType::LIST], true)) {
                throw new NotSupportedByEngine('45', 'Sum aggregation is not supported for string, text or list fields');
            }

            return new SumAggregation(
                name: $aggregation->name,
                field: $aggregation->field
            );
        }

        if ($aggregation instanceof Avg) {
            if (in_array($type, [FieldType::STRING, FieldType::TEXT, FieldType::LIST], true)) {
                throw new NotSupportedByEngine('45', 'Avg aggregation is not supported for string, text or list fields');
            }

            return new AvgAggregation(
                name: $aggregation->name,
                field: $aggregation->field
            );
        }

        if ($aggregation instanceof Distinct) {
            if ($type === FieldType::TEXT) {
                throw new NotSupportedByEngine('45', 'Distinct aggregation is not supported for text fields');
            }

            return new TermsAggregation(
                name: $aggregation->name,
                field: $aggregation->field
            );
        }

        if ($aggregation instanceof Count) {
            if ($type === FieldType::TEXT) {
                throw new NotSupportedByEngine('45', 'Distinct aggregation is not supported for count fields');
            }

            return new TermsAggregation(
                name: $aggregation->name,
                field: $aggregation->field
            );
        }

        throw new \LogicException(sprintf('Unsupported aggregation type %s', $aggregation::class));
    }

    /**
     * @param array<Aggregation> $aggregations
     */
    private function handleAggregations(
        array $aggregations,
        Search $search,
        StorageContext $context
    ): void {
        foreach ($aggregations as $aggregation) {
            $parsed = $this->parseAggregation(
                aggregation: $aggregation,
                context: $context
            );

            $translated = SchemaUtil::translated(collection: $this->collection, accessor: $aggregation->field);

            $path = $this->getNestedPath($aggregation->field);

            if ($translated) {
                $parsed->setField(null);

                assert(method_exists($parsed, 'setScript'));
                $parsed->setScript([
                    'id' => 'translated',
                    'params' => [
                        'languages' => $context->languages,
                        'field' => $aggregation->field,
                        'fallback' => 0,
                    ],
                ]);
            } elseif ($path !== null) {
                $parsed = (new NestedAggregation($aggregation->name, $path))
                    ->addAggregation($parsed);
            }

            if (empty($aggregation->filters)) {
                $search->addAggregation($parsed);
                continue;
            }

            $queries = $this->parseRootFilter(
                filters: $aggregation->filters,
                context: $context
            );

            $filter = new FilterAggregation(
                name: $aggregation->name . '.filter',
                filter: new BoolQuery([
                    BoolQuery::FILTER => $queries,
                ])
            );

            $filter->addAggregation($parsed);

            $search->addAggregation($filter);
        }
    }

    private function getNestedPath(string $field): ?string
    {
        $property = SchemaUtil::property($field);

        $type = SchemaUtil::type(collection: $this->collection, accessor: $property);

        // object field? created nested
        if ($type === FieldType::OBJECT_LIST) {
            return $property;
        }

        return null;
    }

    /**
     * @param Field $field
     * @return array<string, mixed>
     */
    private function getType(Field $field): array
    {
        $type = $field->type;
        if ($field instanceof ListField) {
            $type = $field->innerType;
        }

        $mapped = match ($type) {
            FieldType::BOOL => ['type' => 'boolean'],
            FieldType::DATETIME => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss||strict_date_optional_time||epoch_millis',
                'ignore_malformed' => true,
            ],
            FieldType::FLOAT => ['type' => 'double'],
            FieldType::INT => ['type' => 'integer'],
            FieldType::TEXT => ['type' => 'text'],
            FieldType::STRING => ['type' => 'keyword'],
            FieldType::OBJECT_LIST => ['type' => 'nested'],
            FieldType::OBJECT => ['type' => 'object'],
            default => throw new \LogicException(sprintf('Unsupported field type %s', $type)),
        };

        if (!$field->translated) {
            return $mapped;
        }

        return [
            'type' => 'object',
            'properties' => [
                'en' => $mapped,
                'de' => $mapped,
            ],
        ];
    }
}
