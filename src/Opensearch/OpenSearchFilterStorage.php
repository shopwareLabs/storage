<?php

namespace Shopware\Storage\Opensearch;

use OpenSearch\Client;
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
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Filter\FilterStorage;
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
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\StorageContext;

class OpenSearchFilterStorage implements FilterStorage
{
    public function __construct(private readonly Client $client, private readonly Schema $schema) {}

    public function setup(): void
    {
        // todo@o.skroblin auto setup feature
    }

    public function remove(array $keys): void
    {
        $documents = array_map(function ($key) {
            return ['delete' => ['_id' => $key]];
        }, $keys);

        $arguments = [
            'index' => $this->schema->source,
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
                    '_index' => $this->schema->source,
                    '_id' => $document->key,
                ]
            ];

            $body[] = $document->data;
        }

        if (empty($body)) {
            return;
        }

        $this->client->bulk(['body' => $body]);

        $this->client->indices()->refresh([
            'index' => $this->schema->source
        ]);
    }

    public function filter(
        FilterCriteria $criteria,
        StorageContext $context
    ): FilterResult {
        $search = new Search();

        if ($criteria->paging instanceof Page) {
            $search->setFrom(($criteria->paging->page - 1) * $criteria->limit);
        }

        if ($criteria->limit) {
            $search->setSize($criteria->limit);
        }

        if ($criteria->keys) {
            $search->addQuery(
                new TermsQuery(
                    field: '_id',
                    terms: $criteria->keys
                )
            );
        }

        $queries = $this->parseRootFilter(
            filters: $criteria->filters,
            context: $context
        );

        foreach ($queries as $query) {
            $search->addQuery(
                query: $query,
                boolType: BoolQuery::FILTER
            );
        }

        if ($criteria->sorting) {
            foreach ($criteria->sorting as $sorting) {
                $search->addSort(new FieldSort(
                    field: $sorting->field,
                    order: $sorting->order
                ));
            }
        }

        $parsed = $search->toArray();

        $result = $this->client->search([
            'index' => $this->schema->source,
            '_source' => true,
            'track_total_hits' => $criteria->total,
            'body' => $parsed
        ]);

        $documents = [];
        foreach ($result['hits']['hits'] as $hit) {
            $documents[] = new Document(
                key: $hit['_id'],
                data: $hit['_source'],
            );
        }

        return new FilterResult(
            elements: $documents,
            total: $this->getTotal($criteria, $result),
        );
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

            $property = SchemaUtil::property($filter->field);

            $type = SchemaUtil::type(schema: $this->schema, accessor: $property);

            $translated = SchemaUtil::translated(schema: $this->schema, accessor: $filter->field);

            // object field? created nested
            if (in_array($type, [FieldType::OBJECT, FieldType::OBJECT_LIST], true) || $translated) {
                $nested = $this->parse(
                    filter: $filter,
                    context: $context
                );

                $queries[] = new NestedQuery(path: $property, query: $nested);

                continue;
            }

            $queries[] = $this->parse(
                filter: $filter,
                context: $context
            );
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
                        )
                    ]
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
            BoolQuery::SHOULD => $queries
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
    private function getTotal(FilterCriteria $criteria, array $result): int|null
    {
        if (!$criteria->total) {
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
                BoolQuery::MUST => $nested
            ]);
        }

        if ($operator instanceof OrOperator) {
            $bool = new BoolQuery([
                BoolQuery::SHOULD => $nested
            ]);

            $bool->addParameter('minimum_should_match', 1);

            return $bool;
        }

        if ($operator instanceof NandOperator) {
            return new BoolQuery([
                BoolQuery::MUST_NOT => $nested
            ]);
        }

        if ($operator instanceof NorOperator) {
            $bool = new BoolQuery([
                BoolQuery::MUST_NOT => $nested
            ]);

            $bool->addParameter('minimum_should_match', 1);

            return $bool;
        }

        throw new \LogicException(sprintf('Unsupported operator %s', $operator::class));
    }

    private function parseFilter(Filter $filter, StorageContext $context): BuilderInterface
    {
        $value = $filter->value;

        $translated = SchemaUtil::translated(schema: $this->schema, accessor: $filter->field);

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
                    BoolQuery::MUST_NOT => new ExistsQuery(field: $field)
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
                    )
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
                    )
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

}
