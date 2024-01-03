<?php

namespace Shopware\Storage\MongoDB;

use MongoDB\Client;
use MongoDB\Collection;
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
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\StorageContext;

class MongoDBFilterStorage implements FilterStorage
{
    public function __construct(
        private readonly string $database,
        private readonly Schema $schema,
        private readonly Client $client
    ) {
    }

    public function setup(): void
    {
        // todo@o.skroblin auto setup feature
    }

    public function remove(array $keys): void
    {
        $this->collection()->deleteMany([
            '_key' => ['$in' => $keys]
        ]);
    }

    public function store(Documents $documents): void
    {
        $items = $documents->map(function (Document $document) {
            return array_merge($document->data, [
                '_key' => $document->key
            ]);
        });

        if (empty($items)) {
            return;
        }

        $this->collection()->insertMany(array_values($items));
    }

    public function read(FilterCriteria $criteria, StorageContext $context): FilterResult
    {
        $query = [];

        $options = [];

        if ($criteria->paging instanceof Page) {
            $options['skip'] = ($criteria->paging->page - 1) * $criteria->limit;
        }

        if ($criteria->limit) {
            $options['limit'] = $criteria->limit;
        }

        if ($criteria->sorting) {
            $options['sort'] = array_map(function (Sorting $sort) {
                return [
                    $sort->field => $sort->order === 'ASC' ? 1 : -1
                ];
            }, $criteria->sorting);
        }

        if ($criteria->keys) {
            $query['_key'] = ['$in' => $criteria->keys];
        }
        if ($criteria->filters) {
            $filters = $this->parseFilters($criteria->filters, $context);

            $query = array_merge($query, $filters);
        }

        $cursor = $this->collection()->find($query, $options);

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

            if (!isset($data['_key'])) {
                throw new \RuntimeException('Missing _key property in mongodb result');
            }

            $key = $data['_key'];
            unset($data['_key'], $data['_id']);

            $result[] = new Document(
                key: $key,
                data: $data
            );
        }

        return new FilterResult($result, null);
    }

    private function collection(): Collection
    {
        return $this->client
            ->selectDatabase($this->database)
            ->selectCollection($this->schema->source);
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
        $translated = SchemaUtil::translated(schema: $this->schema, accessor: $filter->field);

        $value = SchemaUtil::cast(
            schema: $this->schema,
            accessor: $filter->field,
            value: $filter->value
        );

        $field = $filter->field;

        $factory = fn (\Closure $function): array => $function($field, $value);

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
                    $gen($field . '.' . $language, $value)
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
}
