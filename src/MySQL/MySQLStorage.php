<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
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
use Shopware\Storage\Common\Filter\Paging\Page;
use Shopware\Storage\Common\Filter\Type\Any;
use Shopware\Storage\Common\Filter\Type\Equals;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Schema\Field;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\ListField;
use Shopware\Storage\Common\Schema\ObjectField;
use Shopware\Storage\Common\Schema\ObjectListField;
use Shopware\Storage\Common\Search\Search;
use Shopware\Storage\Common\Search\SearchAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Common\Total;
use Shopware\Storage\MySQL\Util\MultiInsert;

class MySQLStorage implements Storage, FilterAware, AggregationAware, SearchAware
{
    public function __construct(
        private readonly MySQLParser $parser,
        private readonly Hydrator $hydrator,
        private readonly MySQLSearchInterpreter $interpreter,
        private readonly MySQLAccessorBuilder $accessor,
        private readonly AggregationCaster $caster,
        private readonly Connection $connection,
        private readonly Collection $collection,
    ) {}

    private static function escape(string $source): string
    {
        return '`' . $source . '`';
    }

    public function destroy(): void
    {
        $this->connection->executeStatement(
            sql: 'DROP TABLE IF EXISTS ' . self::escape($this->collection->name)
        );
    }

    public function clear(): void
    {
        $this->connection->executeStatement(
            sql: 'DELETE FROM ' . self::escape($this->collection->name)
        );
    }

    public function setup(): void
    {
        $table = new Table(name: $this->collection->name);

        foreach ($this->collection->fields() as $field) {
            $table->addColumn(
                name: $field->name,
                typeName: $this->getType($field),
                options: $this->getOptions($field),
            );
        }

        $this->createFullTextIndices(
            fields: $this->collection->fields(),
            table: $table,
            prefix: null
        );

        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collate', 'utf8mb4_unicode_ci');

        $table->setPrimaryKey(columnNames: ['key']);

        $manager = $this->connection->createSchemaManager();
        try {
            $current = $manager->introspectTable(name: $this->collection->name);
        } catch (TableDoesNotExist $e) {
            $manager->createTable($table);
            return;
        }

        $diff = $manager
            ->createComparator()
            ->compareTables(fromTable: $current, toTable: $table);

        $manager->alterTable($diff);
    }

    public function mget(array $keys, StorageContext $context): Documents
    {
        $criteria = new Criteria();
        $criteria->filters = [new Any('key', $keys)];

        $query = $this->buildQuery($criteria, $context);

        $data = $query->executeQuery()->fetchAllAssociative();

        if (!$data) {
            return new Documents([]);
        }

        /** @var array<array<string, mixed>> $data */
        $documents = [];
        foreach ($data as $row) {
            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $row,
                context: $context
            );
        }

        return new Documents($documents);
    }

    public function get(string $key, StorageContext $context): ?Document
    {
        $criteria = new Criteria();
        $criteria->filters = [new Equals('key', $key)];

        $query = $this->buildQuery($criteria, $context);

        $data = $query->executeQuery()->fetchAssociative();

        if (!$data) {
            return null;
        }

        /** @var array<array<string, mixed>> $data */
        return $this->hydrator->hydrate(
            collection: $this->collection,
            data: $data,
            context: $context
        );
    }

    public function remove(array $keys): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `' . $this->collection->name . '` WHERE `key` IN (:keys)',
            ['keys' => $keys],
            ['keys' => ArrayParameterType::STRING]
        );
    }

    public function store(Documents $documents): void
    {
        $queue = new MultiInsert(
            connection: $this->connection,
            replace: true,
        );

        foreach ($documents as $document) {
            $data = $document->encode();

            $fulltext = $this->extractFullText(
                fields: $this->collection->fields(),
                data: $data,
                prefix: null
            );

            $data = array_merge($data, $fulltext);

            $queue->add($this->collection->name, $data);
        }

        $queue->execute();
    }

    public function search(Search $search, Criteria $criteria, StorageContext $context): Result
    {
        $query = $this->buildQuery($criteria, $context);

        $this->interpreter->interpret(
            collection: $this->collection,
            builder: $query,
            search: $search,
            context: $context
        );

        /** @var array<array<string, mixed>> $data */
        $data = $query->executeQuery()->fetchAllAssociative();

        $documents = [];
        foreach ($data as $row) {
            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $row,
                context: $context
            );
        }

        return new Result(
            elements: $documents,
            total: $this->getTotal($query, $criteria)
        );
    }

    public function filter(Criteria $criteria, StorageContext $context): Result
    {
        $query = $this->buildQuery($criteria, $context);

        /** @var array<array<string, mixed>> $data */
        $data = $query->executeQuery()->fetchAllAssociative();

        $documents = [];
        foreach ($data as $row) {
            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $row,
                context: $context
            );
        }

        return new Result(
            elements: $documents,
            total: $this->getTotal($query, $criteria)
        );
    }

    public function aggregate(array $aggregations, Criteria $criteria, StorageContext $context): array
    {
        $result = [];

        foreach ($aggregations as $aggregation) {
            $data = $this->loadAggregation(
                aggregation: $aggregation,
                criteria: $criteria,
                context: $context
            );

            $data = $this->caster->cast(
                collection: $this->collection,
                aggregation: $aggregation,
                data: $data
            );

            $result[$aggregation->name] = $data;
        }

        return $result;
    }

    private function loadAggregation(Aggregation $aggregation, Criteria $criteria, StorageContext $context): mixed
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->collection->name, 'root');

        $filters = array_merge(
            $criteria->filters,
            $aggregation->filters
        );

        //todo@skroblin support of post filters?
        if (!empty($filters)) {
            $parsed = $this->parser->parseFilters(
                collection: $this->collection,
                query: $query,
                filters: $filters,
                context: $context
            );

            foreach ($parsed as $filter) {
                $query->andWhere($filter);
            }
        }

        $accessor = $this->accessor->build(
            collection: $this->collection,
            query: $query,
            accessor: $aggregation->field,
            context: $context
        );

        if ($aggregation instanceof Min) {
            $query->select('MIN(' . $accessor . ')');
            return $query->executeQuery()->fetchOne();
        }

        if ($aggregation instanceof Max) {
            $query->select('MAX(' . $accessor . ')');
            return $query->executeQuery()->fetchOne();
        }

        if ($aggregation instanceof Avg) {
            $query->select('AVG(' . $accessor . ')');
            return $query->executeQuery()->fetchOne();
        }

        if ($aggregation instanceof Sum) {
            $query->select('SUM(' . $accessor . ')');
            return $query->executeQuery()->fetchOne();
        }

        if ($aggregation instanceof Count) {
            $query->select([
                $accessor . ' as `key`',
                'COUNT(' . $accessor . ') as count',
            ]);
            $query->groupBy($accessor);

            return $query->executeQuery()->fetchAllAssociative();
        }

        if ($aggregation instanceof Distinct) {
            $query->select('DISTINCT ' . $accessor);
            return $query->executeQuery()->fetchFirstColumn();
        }

        throw new \LogicException(sprintf('Unsupported aggregation type %s', get_class($aggregation)));
    }

    private function buildQuery(Criteria $criteria, StorageContext $context): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('root.*');
        $query->from(self::escape($this->collection->name), 'root');

        if ($criteria->paging instanceof Page) {
            $query->setFirstResult(($criteria->paging->page - 1) * $criteria->paging->limit);
            $query->setMaxResults($criteria->paging->limit);
        } elseif ($criteria->paging instanceof Limit) {
            $query->setMaxResults($criteria->paging->limit);
        }

        if ($criteria->primaries) {
            $query->andWhere('`key` IN (:keys)');
            $query->setParameter('keys', $criteria->primaries, ArrayParameterType::STRING);
        }

        if ($criteria->sorting) {
            foreach ($criteria->sorting as $sorting) {
                $accessor = $this->accessor->build(
                    collection: $this->collection,
                    query: $query,
                    accessor: $sorting->field,
                    context: $context
                );

                $query->addOrderBy($accessor, $sorting->order);
            }
        }

        if ($criteria->filters) {
            $filters = $this->parser->parseFilters(
                collection: $this->collection,
                query: $query,
                filters: $criteria->filters,
                context: $context
            );

            foreach ($filters as $filter) {
                $query->andWhere($filter);
            }
        }

        return $query;
    }

    private function getTotal(QueryBuilder $query, Criteria $criteria): ?int
    {
        if ($criteria->total === Total::NONE) {
            return null;
        }

        $query->resetOrderBy();

        $query->select('COUNT(*)');

        $total = $query->executeQuery()->fetchOne();

        if ($total === false) {
            throw new \RuntimeException('Could not fetch total');
        }

        if (!is_string($total) && !is_int($total)) {
            throw new \RuntimeException('Invalid total type');
        }

        return (int) $total;
    }

    private function getType(Field $field): string
    {
        if ($field->translated) {
            return Types::JSON;
        }

        return match($field->type) {
            FieldType::STRING => Types::STRING,
            FieldType::TEXT => Types::TEXT,
            FieldType::INT => Types::INTEGER,
            FieldType::FLOAT => Types::DECIMAL,
            FieldType::BOOL => Types::BOOLEAN,
            FieldType::DATETIME => Types::DATETIME_MUTABLE,
            FieldType::OBJECT, FieldType::OBJECT_LIST, FieldType::LIST => Types::JSON,
            default => throw new \RuntimeException('Unsupported field type ' . $field->type),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getOptions(Field $field): array
    {
        $options = [];

        if ($field->nullable) {
            $options['notnull'] = false;
        }

        if ($field->translated) {
            return $options;
        }

        if ($field->type === FieldType::FLOAT) {
            $options['precision'] = 10;
            $options['scale'] = 4;
        }

        return $options;
    }

    /**
     * @param array<Field> $fields
     */
    private function createFullTextIndices(array $fields, Table $table, ?string $prefix): void
    {
        foreach ($fields as $field) {
            if (!$field->searchable) {
                continue;
            }

            $name = implode('_', array_filter([$prefix, $field->name]));

            $table->addColumn(
                name: '_' . $name . '_search',
                typeName: Types::TEXT,
                options: ['notnull' => false]
            );

            $table->addIndex(
                columnNames: ['_' . $name . '_search'],
                indexName: 'idx_' . $name . '_search',
                flags: ['FULLTEXT']
            );

            if ($field instanceof ObjectField || $field instanceof ObjectListField) {
                $this->createFullTextIndices(
                    fields: $field->fields(),
                    table: $table,
                    prefix: $name
                );
            }
        }
    }

    /**
     * @param array<Field> $fields
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function extractFullText(array $fields, array $data, ?string $prefix): array
    {
        $mapped = [];
        foreach ($fields as $field) {
            if (!$field->searchable) {
                continue;
            }

            $value = $data[$field->name] ?? null;
            if ($value === null) {
                continue;
            }

            $name = implode('_', array_filter([$prefix, $field->name]));

            $key = '_' . $name . '_search';

            if ($field->translated || $field instanceof ListField) {
                if (!is_array($value)) {
                    continue;
                }
                $mapped[$key] = implode(' ', $value);
                continue;
            }

            $native = in_array($field->type, [FieldType::STRING, FieldType::TEXT], true);
            if ($native) {
                if (!is_string($value)) {
                    continue;
                }

                $mapped[$key] = $data[$field->name];
                continue;
            }

            if ($field instanceof ObjectField) {
                if (!is_array($value)) {
                    continue;
                }
                /** @var array<string, mixed> $value */
                $nested = $this->extractFullText(
                    fields: $field->fields(),
                    data: $value,
                    prefix: $name
                );

                foreach ($nested as $key => $value) {
                    $mapped[$key] = $value;
                }

                continue;
            }

            if ($field instanceof ObjectListField) {
                if (!is_array($value)) {
                    continue;
                }

                /** @var array<string, mixed> $item */
                foreach ($value as $item) {
                    $nested = $this->extractFullText(
                        fields: $field->fields(),
                        data: $item,
                        prefix: $name
                    );

                    foreach ($nested as $key => $i) {
                        $mapped[$key] .= $i . ' ';
                    }
                }
                continue;
            }
        }

        return $mapped;
    }
}
