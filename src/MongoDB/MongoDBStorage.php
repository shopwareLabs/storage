<?php

namespace Shopware\Storage\MongoDB;

use MongoDB\Client;
use MongoDB\Collection;
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
use Shopware\Storage\Common\Filter\Sorting;
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
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\Search\Group;
use Shopware\Storage\Common\Search\Query;
use Shopware\Storage\Common\Search\Search;
use Shopware\Storage\Common\Search\SearchAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

class MongoDBStorage implements Storage, FilterAware, AggregationAware, SearchAware
{
    private const TYPE_MAP = [
        'root' => 'array',
        'document' => 'array',
        'array' => 'array',
    ];

    public function __construct(
        private readonly AggregationCaster $caster,
        private readonly Hydrator $hydrator,
        private readonly string $database,
        private readonly \Shopware\Storage\Common\Schema\Collection $collection,
        private readonly Client $client
    ) {}

    public function destroy(): void
    {
        $this->collection()->drop();
    }

    public function clear(): void
    {
        $this->collection()->deleteMany([]);
    }

    public function setup(): void
    {
        $this->collection()->createIndex(['$**' => 'text']);
    }

    public function search(Search $search, Criteria $criteria, StorageContext $context): Result
    {
        $filter = [];

        $options = [];

        if ($criteria->paging instanceof Page) {
            $options['skip'] = ($criteria->paging->page - 1) * $criteria->paging->limit;
            $options['limit'] = $criteria->paging->limit;
        } elseif ($criteria->paging instanceof Limit) {
            $options['limit'] = $criteria->paging->limit;
        }

        if ($criteria->sorting) {
            $options['sort'] = array_map(function (Sorting $sort) {
                return [
                    $sort->field => $sort->order === 'ASC' ? 1 : -1,
                ];
            }, $criteria->sorting);
        }

        if ($criteria->primaries) {
            $filter['key'] = ['$in' => $criteria->primaries];
        }

        if ($criteria->filters) {
            $parsed = $this->parseFilters($criteria->filters, $context);

            $filter = array_merge($filter, $parsed);
        }

        $terms = $this->collectTerms(queries: $search->group->queries);

        $filter['$text'] = ['$search' => implode(' ', $terms)];

        $cursor = $this->collection()->find(filter: $filter, options: $options);

        $cursor->setTypeMap(self::TYPE_MAP);

        $documents = [];
        foreach ($cursor as $item) {
            if (!is_array($item)) {
                throw new \RuntimeException('Invalid document');
            }

            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $item,
                context: $context
            );
        }

        return new Result($documents, null);
    }

    public function mget(array $keys, StorageContext $context): Documents
    {
        $query['key'] = ['$in' => $keys];

        $cursor = $this->collection()->find($query);

        $cursor->setTypeMap([
            'root' => 'array',
            'document' => 'array',
            'array' => 'array',
        ]);

        $result = [];
        foreach ($cursor as $item) {
            $data = $item;

            if (!is_array($data)) {
                throw new \RuntimeException('Mongodb returned invalid data type');
            }

            $result[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $data,
                context: $context
            );
        }

        return new Documents($result);
    }

    public function get(string $key, StorageContext $context): ?Document
    {
        $options = [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ],
        ];

        $cursor = $this->collection()->findOne(['key' => $key], $options);

        if ($cursor === null) {
            return null;
        }

        if (!is_array($cursor)) {
            throw new \RuntimeException('Mongodb returned invalid data type');
        }

        return $this->hydrator->hydrate(
            collection: $this->collection,
            data: $cursor,
            context: $context
        );
    }

    public function aggregate(array $aggregations, Criteria $criteria, StorageContext $context): array
    {
        $query = [];

        $options = ['typeMap' => self::TYPE_MAP];

        $filters = $criteria->filters;
        if ($criteria->primaries) {
            $filters[] = new Any('key', $criteria->primaries);
        }

        if (!empty($filters)) {
            $filters = $this->parseFilters($filters, $context);

            $query[] = ['$match' => $filters];
        }

        $parsed = [];
        foreach ($aggregations as $aggregation) {
            $parsed[$aggregation->name] = $this->parseAggregation(
                aggregation: $aggregation,
                context: $context
            );
        }

        $query[] = ['$facet' => $parsed];

        $response = $this->collection()->aggregate(
            pipeline: $query,
            options: $options
        );

        $result = [];
        foreach ($response as $value) {
            if (!is_array($value)) {
                throw new \RuntimeException('Invalid aggregation result');
            }

            $key = (string) array_key_first($value);

            if (!isset($value[$key])) {
                throw new \RuntimeException('Invalid aggregation result');
            }

            $aggregation = $this->getAggregation(name: $key, aggregations: $aggregations);

            if ($aggregation instanceof Min) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $value[$key][0]['min']
                );
                continue;
            }

            if ($aggregation instanceof Max) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $value[$key][0]['max']
                );
                continue;
            }
            if ($aggregation instanceof Sum) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $value[$key][0]['sum']
                );
                continue;
            }
            if ($aggregation instanceof Avg) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $value[$key][0]['avg']
                );
                continue;
            }
            if ($aggregation instanceof Distinct) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: array_column($value[$key], '_id')
                );
                continue;
            }

            if ($aggregation instanceof Count) {
                $values = array_map(function (array $item) {
                    return ['key' => $item['_id'], 'count' => $item['count']];
                }, $value[$key]);

                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $values
                );
                continue;
            }

            throw new \RuntimeException(sprintf('Unsupported aggregation type %s', $aggregation::class));
        }

        return $result;
    }

    /**
     * @param Aggregation[] $aggregations
     */
    private function getAggregation(string $name, array $aggregations): Aggregation
    {
        foreach ($aggregations as $aggregation) {
            if ($aggregation->name === $name) {
                return $aggregation;
            }
        }

        throw new \RuntimeException(sprintf('Aggregation %s not found', $name));
    }

    /**
     * @return array<mixed>
     */
    private function parseAggregation(Aggregation $aggregation, StorageContext $context): array
    {
        $parsed = [];

        if (!empty($aggregation->filters)) {
            $parsed[] = ['$match' => $this->parseFilters($aggregation->filters, $context)];
        }

        $property = SchemaUtil::property(accessor: $aggregation->field);

        $type = SchemaUtil::type(collection: $this->collection, accessor: $property);

        if (in_array($type, [FieldType::OBJECT_LIST, FieldType::LIST], true)) {
            $parsed[] = ['$unwind' => '$' . $property];
        }
        $field = '$' . $aggregation->field;

        $translated = SchemaUtil::translated(collection: $this->collection, accessor: $aggregation->field);

        if ($translated) {
            $field = array_map(function (string $language) use ($field) {
                return $field . '.' . $language;
            }, $context->languages);

            $field = ['$ifNull' => $field];
        }

        if ($aggregation instanceof Min) {
            $parsed[] = [
                '$group' => [
                    '_id' => 0,
                    'min' => ['$min' => $field],
                ],
            ];
            return $parsed;
        }
        if ($aggregation instanceof Max) {
            $parsed[] = [
                '$group' => [
                    '_id' => 0,
                    'max' => ['$max' => $field],
                ],
            ];
            return $parsed;
        }
        if ($aggregation instanceof Sum) {
            $parsed[] = [
                '$group' => [
                    '_id' => 0,
                    'sum' => ['$sum' => $field],
                ],
            ];
            return $parsed;
        }
        if ($aggregation instanceof Avg) {
            $parsed[] = [
                '$group' => [
                    '_id' => 0,
                    'avg' => ['$avg' => $field],
                ],
            ];
            return $parsed;
        }
        if ($aggregation instanceof Count) {
            $parsed[] = [
                '$group' => [
                    '_id' => $field,
                    'count' => ['$sum' => 1],
                ],
            ];
            return $parsed;
        }
        if ($aggregation instanceof Distinct) {
            $parsed[] = [
                '$group' => [
                    '_id' => $field,
                ],
            ];

            return $parsed;
        }

        throw new \RuntimeException(sprintf('Unsupported aggregation type %s', $aggregation::class));
    }

    public function remove(array $keys): void
    {
        $this->collection()->deleteMany([
            'key' => ['$in' => $keys],
        ]);
    }

    public function store(Documents $documents): void
    {
        $items = $documents->map(function (Document $document) {
            return $document->encode();
        });

        if (empty($items)) {
            return;
        }

        $this->collection()->insertMany(array_values($items));
    }

    public function filter(Criteria $criteria, StorageContext $context): Result
    {
        $query = [];

        $options = [];

        if ($criteria->paging instanceof Page) {
            $options['skip'] = ($criteria->paging->page - 1) * $criteria->paging->limit;
            $options['limit'] = $criteria->paging->limit;
        } elseif ($criteria->paging instanceof Limit) {
            $options['limit'] = $criteria->paging->limit;
        }

        if ($criteria->sorting) {
            $options['sort'] = array_map(function (Sorting $sort) {
                return [
                    $sort->field => $sort->order === 'ASC' ? 1 : -1,
                ];
            }, $criteria->sorting);
        }

        if ($criteria->primaries) {
            $query['key'] = ['$in' => $criteria->primaries];
        }

        if ($criteria->filters) {
            $filters = $this->parseFilters($criteria->filters, $context);

            $query = array_merge($query, $filters);
        }

        $cursor = $this->collection()->find($query, $options);

        $cursor->setTypeMap(self::TYPE_MAP);

        $documents = [];
        foreach ($cursor as $item) {
            if (!is_array($item)) {
                throw new \RuntimeException('Invalid document');
            }

            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $item,
                context: $context
            );
        }

        return new Result($documents, null);
    }

    private function collection(): Collection
    {
        return $this->client
            ->selectDatabase($this->database)
            ->selectCollection($this->collection->name);
    }

    /**
     * @param array<Operator|Filter> $filters
     * @return array<string|int, array<mixed>>
     */
    private function parseFilters(array $filters, StorageContext $context): array
    {
        $queries = [];

        foreach ($filters as $filter) {
            if ($filter instanceof Operator) {
                $queries[] = $this->parseOperator($filter, $context);
                continue;
            }

            $queries = array_merge_recursive($queries, $this->parseFilter($filter, $context));
        }

        return $queries;
    }

    /**
     * @return array<mixed>
     */
    private function parseFilter(Filter $filter, StorageContext $context): array
    {
        $translated = SchemaUtil::translated(collection: $this->collection, accessor: $filter->field);

        $value = SchemaUtil::cast(
            collection: $this->collection,
            accessor: $filter->field,
            value: $filter->value
        );

        $field = $filter->field;

        $factory = fn(\Closure $function): array => $function($field, $value);

        if ($translated) {
            $factory = function (\Closure $generator) use ($filter, $context, $value) {
                return $this->translationQuery($generator, $filter, $context, $value);
            };
        }

        if ($filter instanceof Equals) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$eq' => $value]];
            });
        }

        if ($filter instanceof Any) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$in' => $value]];
            });
        }

        if ($filter instanceof Not) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$ne' => $value]];
            });
        }

        if ($filter instanceof Neither) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$nin' => $value]];
            });
        }

        if ($filter instanceof Gt) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$gt' => $value]];
            });
        }

        if ($filter instanceof Gte) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$gte' => $value]];
            });
        }

        if ($filter instanceof Lt) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$lt' => $value]];
            });
        }

        if ($filter instanceof Lte) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$lte' => $value]];
            });
        }

        if ($filter instanceof Prefix) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$regex' => '^' . $value]];
            });
        }

        if ($filter instanceof Suffix) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$regex' => $value . '$']];
            });
        }

        if ($filter instanceof Contains) {
            return $factory(function (string $field, mixed $value) {
                return [$field => ['$regex' => $value]];
            });
        }

        throw new \LogicException(sprintf('Unsupported filter type %s', $filter::class));
    }

    /**
     * @return array<string, mixed>
     */
    private function parseOperator(Operator $operator, StorageContext $context): array
    {
        if ($operator instanceof AndOperator) {
            return ['$and' => $this->parseFilters($operator->filters, $context)];
        }

        if ($operator instanceof OrOperator) {
            return ['$or' => $this->parseFilters($operator->filters, $context)];
        }

        if ($operator instanceof NorOperator) {
            return ['$nor' => $this->parseFilters($operator->filters, $context)];
        }

        if ($operator instanceof NandOperator) {
            return ['$not' => $this->parseFilters($operator->filters, $context)];
        }

        throw new \RuntimeException(sprintf('Unsupported operator %s', $operator::class));
    }

    /**
     * @return array{"$or": array<mixed>}
     */
    private function translationQuery(\Closure $gen, Filter $filter, StorageContext $context, mixed $value): array
    {
        $queries = [];

        $before = [];

        $field = $filter->field;

        foreach ($context->languages as $index => $language) {
            if (array_key_first($context->languages) === $index) {
                $queries[] = ['$and' => [
                    [$field . '.' . $language => ['$ne' => null]],
                    $gen($field . '.' . $language, $value),
                ]];
                $before[] = $language;
                continue;
            }

            $nested = [];
            foreach ($before as $id) {
                $nested[] = [$field . '.' . $id => ['$eq' => null]];
            }
            $nested[] = $gen($field . '.' . $language, $value);

            $queries[] = ['$and' => $nested];
        }

        return ['$or' => $queries];
    }

    /**
     * @param array<Group|Query> $queries
     * @return array<string>
     */
    private function collectTerms(array $queries): array
    {
        $terms = [];

        foreach ($queries as $query) {
            if ($query instanceof Group) {
                $terms = array_merge($terms, $this->collectTerms(queries: $query->queries));
                continue;
            }

            if ($query instanceof Query) {
                $terms[] = $query->term;
            }
        }
        return $terms;
    }
}
