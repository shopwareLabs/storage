<?php

namespace Shopware\Storage\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
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
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\Search\Group;
use Shopware\Storage\Common\Search\Query;
use Shopware\Storage\Common\Search\Search;
use Shopware\Storage\Common\Search\SearchAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\StorageTests\Common\TestSchema;

class MeilisearchStorage implements Storage, FilterAware, AggregationAware, SearchAware
{
    public function __construct(
        private readonly AggregationCaster $caster,
        private readonly Hydrator $hydrator,
        private readonly Client $client,
        private readonly Collection $collection
    ) {}

    public function setup(): void
    {
        if (!$this->exists()) {
            $this->client->createIndex(
                uid: $this->collection->name,
                options: ['primaryKey' => 'key']
            );
        }

        $this->updateIndex();
    }

    public function destroy(): void
    {
        $this->client->deleteIndex($this->collection->name);
    }

    public function clear(): void
    {
        $this->index()->deleteAllDocuments();
    }

    public function get(string $key, StorageContext $context): ?Document
    {
        try {
            $result = $this->client->index($this->collection->name)->getDocument($key);
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            throw $e;
        }

        return $this->hydrator->hydrate(
            collection: $this->collection,
            data: $result,
            context: $context
        );
    }

    public function mget(array $keys, StorageContext $context): Documents
    {
        $result = $this->client->index($this->collection->name)->getDocument($keys);

        $documents = [];
        foreach ($result->getHits() as $hit) {
            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $hit,
                context: $context
            );
        }

        return new Result(
            elements: $documents
        );
    }

    public function search(Search $search, Criteria $criteria, StorageContext $context): Result
    {
        $params = [];

        if ($criteria->paging instanceof Page) {
            $params['page'] = $criteria->paging->page;
            $params['hitsPerPage'] = $criteria->paging->limit;
        }

        $filters = $criteria->filters;
        if ($criteria->primaries !== null) {
            $filters[] = new Any(field: 'key', value: $criteria->primaries);
        }

        if (!empty($filters)) {
            $params['filter'] = $this->parse($filters, $context);
        }

        $query = $this->collectTerms($search->group->queries);

        $query = implode(' ', array_unique($query));

        $params['q'] = $query;

        $result = $this->index()->search(
            query: null,
            searchParams: $params
        );

        $documents = [];
        foreach ($result->getHits() as $hit) {
            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $hit,
                context: $context
            );
        }

        return new Result(
            elements: $documents
        );
    }

    public function aggregate(array $aggregations, Criteria $criteria, StorageContext $context): array
    {
        $params = [
            'page' => 0,
            'hitsPerPage' => 0,
        ];

        $filters = $criteria->filters;
        if ($criteria->primaries !== null) {
            $filters[] = new Any(field: 'key', value: $criteria->primaries);
        }
        if (!empty($filters)) {
            $params['filter'] = $this->parse($filters, $context);
        }

        $facets = [];
        foreach ($aggregations as $aggregation) {
            $translated = SchemaUtil::translated(collection: $this->collection, accessor: $aggregation->field);
            if ($translated) {
                throw new NotSupportedByEngine('', 'Meilisearch does not support aggregations on translated fields.');
            }

            $type = SchemaUtil::type(collection: $this->collection, accessor: $aggregation->field);
            if (in_array($type, [FieldType::TEXT, FieldType::STRING], true)) {
                if ($aggregation instanceof Sum || $aggregation instanceof Avg) {
                    throw new NotSupportedByEngine('', 'Meilisearch does not support sum/avg aggregations on string/text fields.');
                }
            }

            $facets[] = $aggregation->field;
        }

        $params['facets'] = $facets;

        $response = $this->index()->search(
            query: null,
            searchParams: $params
        );

        /** @var array<string, array{min: mixed, max: mixed}> $stats */
        $stats = $response->getFacetStats();

        /** @var array<string, array<string, mixed>> $distributions */
        $distributions = $response->getFacetDistribution();

        $result = [];
        foreach ($aggregations as $aggregation) {
            $type = SchemaUtil::type(collection: $this->collection, accessor: $aggregation->field);

            if ($type === FieldType::BOOL && $aggregation instanceof Min) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: !array_key_exists('false', self::distribution($distributions, $aggregation->field))
                );
                continue;
            }

            if ($type === FieldType::BOOL && $aggregation instanceof Max) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: array_key_exists('true', self::distribution($distributions, $aggregation->field))
                );
                continue;
            }

            if (in_array($type, [FieldType::STRING, FieldType::TEXT, FieldType::DATETIME], true) && $aggregation instanceof Min) {
                $values = array_keys(self::distribution($distributions, $aggregation->field));

                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: min($values)
                );

                continue;
            }

            if (in_array($type, [FieldType::STRING, FieldType::TEXT, FieldType::DATETIME], true) && $aggregation instanceof Max) {
                $values = array_keys(self::distribution($distributions, $aggregation->field));

                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: max($values)
                );

                continue;
            }

            if ($aggregation instanceof Min) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: self::stats($stats, $aggregation->field, 'min')
                );
                continue;
            }

            if ($aggregation instanceof Max) {
                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: self::stats($stats, $aggregation->field, 'max')
                );
                continue;
            }

            if ($aggregation instanceof Distinct) {
                $values = self::distribution($distributions, $aggregation->field);

                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: array_keys($values)
                );
                continue;
            }

            if ($aggregation instanceof Count) {
                $values = self::distribution($distributions, $aggregation->field);

                $values = array_map(fn($value) => ['key' => $value, 'count' => $values[$value]], array_keys($values));

                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $values
                );

                continue;
            }

            if ($aggregation instanceof Sum) {
                $values = self::distribution($distributions, $aggregation->field);

                $sum = 0;
                foreach ($values as $key => $value) {
                    if (!is_numeric($key) || !is_numeric($value)) {
                        throw new \RuntimeException('Meilisearch does not return a numeric value for aggregation field ' . $aggregation->field);
                    }
                    $sum += (float) $key * (float) $value;
                }

                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $sum
                );
                continue;
            }

            if ($aggregation instanceof Avg) {
                $values = self::distribution($distributions, $aggregation->field);

                $sum = 0;
                $count = 0;
                foreach ($values as $key => $value) {
                    if (!is_numeric($key) || !is_numeric($value)) {
                        throw new \RuntimeException('Meilisearch does not return a numeric value for aggregation field ' . $aggregation->field);
                    }
                    $sum += (float) $key * (float) $value;
                    $count += $value;
                }

                $result[$aggregation->name] = $this->caster->cast(
                    collection: $this->collection,
                    aggregation: $aggregation,
                    data: $sum / $count
                );
            }
        }

        return $result;
    }

    public function filter(Criteria $criteria, StorageContext $context): Result
    {
        $params = [];

        if ($criteria->paging instanceof Page) {
            $params['page'] = $criteria->paging->page;
            $params['hitsPerPage'] = $criteria->paging->limit;
        }

        $filters = $criteria->filters;
        if ($criteria->primaries !== null) {
            $filters[] = new Any(field: 'key', value: $criteria->primaries);
        }

        if (!empty($filters)) {
            $params['filter'] = $this->parse($filters, $context);
        }

        $result = $this->index()->search(
            query: null,
            searchParams: $params
        );

        $documents = [];
        foreach ($result->getHits() as $hit) {
            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $hit,
                context: $context
            );
        }

        return new Result(
            elements: $documents
        );
    }

    public function remove(array $keys): void
    {
        $this->index()->deleteDocuments($keys);
    }

    public function store(Documents $documents): void
    {
        $data = [];
        foreach ($documents as $document) {
            $data[] = $document->encode();
        }

        $this->index()->addDocuments($data);
    }

    public function index(): Indexes
    {
        return $this->client->index($this->collection->name);
    }

    /**
     * @param array<Operator|Filter> $filters
     */
    private function parse(array $filters, StorageContext $context): string
    {
        $parsed = [];

        foreach ($filters as $filter) {
            if ($filter instanceof Operator) {
                $parsed[] = $this->parseOperator($filter, $context);
            }

            if ($filter instanceof Filter) {
                $parsed[] = $this->parseFilter($filter, $context);
            }
        }

        if (count($parsed) === 1) {
            return $parsed[0];
        }

        return self::and($parsed);
    }

    private static function cast(mixed $value): mixed
    {
        if (is_string($value)) {
            return '"' . $value . '"';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (!is_array($value)) {
            return $value;
        }

        return array_map(fn($v) => self::cast($v), $value);
    }

    private function parseFilter(Filter $filter, StorageContext $context): string
    {
        $property = SchemaUtil::property($filter->field);

        $translated = SchemaUtil::translated(collection: $this->collection, accessor: $filter->field);

        $value = SchemaUtil::cast(collection: $this->collection, accessor: $filter->field, value: $filter->value);

        $value = self::cast($value);

        $factory = function (\Closure $closure) use ($filter, $value) {
            return $closure($filter->field, $value);
        };

        if ($translated) {
            $factory = function (\Closure $closure) use ($filter, $context, $value) {
                return $this->translatedQuery($closure, $filter, $value, $context);
            };
        }

        if (is_string($filter->value) && ($filter instanceof Lt || $filter instanceof Gt || $filter instanceof Lte || $filter instanceof Gte)) {
            throw new NotSupportedByEngine('34', 'Meilisearch does not support string comparison.');
        }
        if ($filter instanceof Contains) {
            throw new NotSupportedByEngine('33', ' Meilisearch contains/prefix/suffix filter are not supported.');
        }
        if ($filter instanceof Prefix) {
            throw new NotSupportedByEngine('33', ' Meilisearch contains/prefix/suffix filter are not supported.');
        }
        if ($filter instanceof Suffix) {
            throw new NotSupportedByEngine('33', ' Meilisearch contains/prefix/suffix filter are not supported.');
        }

        if ($filter->value === null) {
            if ($filter instanceof Equals) {
                return $this->equalsNull(
                    factory: $factory,
                    property: $property,
                    field: $filter->field
                );
            }

            if ($filter instanceof Not) {
                return $this->notNull(
                    factory: $factory,
                    property: $property,
                    field: $filter->field
                );
            }

            throw new \RuntimeException('Null filter values are only supported for Equals and Not filters');
        }

        if ($filter instanceof Equals) {
            return $factory(fn($field, $value) => $field . ' = ' . $value);
        }

        if ($filter instanceof Not) {
            return $factory(fn($field, $value) => $field . ' != ' . $value);
        }

        if ($filter instanceof Any) {
            return $factory(function (string $field, mixed $value) {
                return $field . ' IN [' . implode(',', $value) . ']';
            });
        }

        if ($filter instanceof Neither) {
            return $factory(function (string $field, mixed $value) {
                return $field . ' NOT IN [' . implode(',', $value) . ']';
            });
        }

        if ($filter instanceof Lt) {
            return $factory(fn($field, $value) => $field . ' < ' . $value);
        }

        if ($filter instanceof Gt) {
            return $factory(fn($field, $value) => $field . ' > ' . $value);
        }

        if ($filter instanceof Lte) {
            return $factory(fn($field, $value) => $field . ' <= ' . $value);
        }

        if ($filter instanceof Gte) {
            return $factory(fn($field, $value) => $field . ' >= ' . $value);
        }

        throw new \RuntimeException('Unknown filter: ' . get_class($filter));
    }

    private function equalsNull(\Closure $factory, string $property, string $field): string
    {
        if ($property === $field) {
            return $factory(fn($field, $value) => $field . ' IS NULL');
        }

        return self::or([
            $property . ' IS NULL',
            $factory(fn($field, $value) => $field . ' IS NULL'),
        ]);
    }

    private function notNull(\Closure $factory, string $property, string $field): string
    {
        if ($property === $field) {
            return $factory(fn($field, $value) => $field . ' IS NOT NULL');
        }

        return self::and([
            $property . ' IS NOT NULL',
            $factory(fn($field, $value) => $field . ' IS NOT NULL'),
        ]);
    }

    private function parseOperator(Operator $operator, StorageContext $context): string
    {
        $parsed = [];
        foreach ($operator->filters as $filter) {
            $parsed[] = $this->parse([$filter], $context);
        }

        if ($operator instanceof AndOperator) {
            return self::and($parsed);
        }

        if ($operator instanceof OrOperator) {
            return self::or($parsed);
        }

        if ($operator instanceof NandOperator) {
            return 'NOT ' . self::and($parsed);
        }

        if ($operator instanceof NorOperator) {
            return 'NOT ' . self::or($parsed);
        }

        throw new \RuntimeException('Unknown operator: ' . get_class($operator));
    }

    private function translatedQuery(\Closure $closure, Filter $filter, mixed $value, StorageContext $context): string
    {
        $queries = [];

        $before = [];

        foreach ($context->languages as $index => $languageId) {
            if (array_key_first($context->languages) === $index) {
                $queries[] = self::and([
                    $filter->field . '.' . $languageId . ' EXISTS',
                    $filter->field . '.' . $languageId . ' IS NOT NULL',
                    $closure($filter->field . '.' . $languageId, $value),
                ]);

                $before[] = $languageId;

                continue;
            }

            $nested = [];
            foreach ($before as $id) {
                $nested[] = self::or([
                    $filter->field . '.' . $id . ' NOT EXISTS',
                    $filter->field . '.' . $id . ' IS NULL',
                ]);
            }

            $nested[] = self::and([
                $filter->field . '.' . $languageId . ' EXISTS',
                $filter->field . '.' . $languageId . ' IS NOT NULL',
                $closure($filter->field . '.' . $languageId, $value),
            ]);

            $before[] = $languageId;

            $queries[] = self::and($nested);
        }

        return self::or($queries);
    }

    /**
     * @param array<string> $elements
     */
    private static function or(array $elements): string
    {
        return '(' . implode(' OR ', $elements) . ')';
    }

    /**
     * @param array<string> $elements
     */
    private static function and(array $elements): string
    {
        return '(' . implode(' AND ', $elements) . ')';
    }


    /**
     * @param array<string, array{min: mixed, max: mixed}> $stats
     */
    private static function stats(array $stats, string $field, string $key): mixed
    {
        if (!isset($stats[$field])) {
            throw new \RuntimeException(sprintf('Meilisearch does not return a stats value for aggregation field %s', $field));
        }

        return $stats[$field][$key];
    }

    /**
     * @param array<string, array<string, mixed>> $distributions
     * @return array<string, mixed>
     */
    private static function distribution(array $distributions, string $field): array
    {
        if (!isset($distributions[$field])) {
            throw new \RuntimeException(sprintf('Meilisearch does not return a distribution value for aggregation field %s', $field));
        }

        return $distributions[$field];
    }

    private function exists(): bool
    {
        try {
            $this->client->getIndex($this->collection->name);
        } catch (ApiException) {
            return false;
        }

        return true;
    }

    private function updateIndex(): void
    {
        $fields = array_map(fn($field) => $field->name, TestSchema::getCollection()->fields());

        $fields[] = 'key';

        $fields = array_values(array_filter($fields));

        $this->index()
            ->updateFilterableAttributes($fields);

        $this->index()
            ->updateSortableAttributes($fields);

        $this->wait();
    }

    private function wait(): void
    {
        $tasks = new TasksQuery();
        $tasks->setStatuses(['enqueued', 'processing']);

        $tasks = $this->client->getTasks($tasks);

        $ids = array_map(fn($task) => $task['uid'], $tasks->getResults());

        if (count($ids) === 0) {
            return;
        }

        $this->client->waitForTasks($ids);
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
                $nested = $this->collectTerms($query->queries);

                foreach($nested as $term) {
                    $terms[] = $term;
                }
            }

            if ($query instanceof Query) {
                $terms[] = $query->term;
            }
        }

        return $terms;
    }
}
