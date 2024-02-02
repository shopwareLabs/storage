<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
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
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Common\Total;
use Shopware\Storage\Common\Util\Uuid;
use Shopware\Storage\MySQL\Util\MultiInsert;

class MySQLStorage implements Storage, FilterAware, AggregationAware
{
    public function __construct(
        private readonly AggregationCaster $caster,
        private readonly Connection        $connection,
        private readonly Schema            $schema
    ) {}

    private static function escape(string $source): string
    {
        return '`' . $source . '`';
    }

    public function setup(): void
    {
        // todo@o.skroblin auto setup feature
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
                schema: $this->schema,
                aggregation: $aggregation,
                data: $data
            );

            $result[$aggregation->name] = $data;
        }

        return $result;
    }

    public function remove(array $keys): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `' . $this->schema->source . '` WHERE `key` IN (:keys)',
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
            $queue->add($this->schema->source, array_merge($document->data, ['key' => $document->key]));
        }

        $queue->execute();
    }

    private function loadAggregation(Aggregation $aggregation, Criteria $criteria, StorageContext $context): mixed
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->schema->source, 'root');

        $filters = array_merge(
            $criteria->filters,
            $aggregation->filter
        );

        //todo@skroblin support of post filters?
        if (!empty($filters)) {
            $parsed = $this->addFilters($query, $filters, $context);

            foreach ($parsed as $filter) {
                $query->andWhere($filter);
            }
        }

        $accessor = $this->getAccessor($query, $aggregation->field, $context);

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
                'COUNT(' . $accessor . ') as count'
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

    public function filter(Criteria $criteria, StorageContext $context): Result
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('root.*');
        $query->from(self::escape($this->schema->source), 'root');

        if ($criteria->paging instanceof Page) {
            $query->setFirstResult(($criteria->paging->page - 1) * $criteria->paging->limit);
            $query->setMaxResults($criteria->paging->limit);
        } elseif ($criteria->paging instanceof Limit) {
            $query->setMaxResults($criteria->paging->limit);
        }

        if ($criteria->primaries) {
            $query->andWhere('`key` IN (:keys)');
            $query->setParameter('keys', $criteria->primaries, Connection::PARAM_STR_ARRAY);
        }

        if ($criteria->sorting) {
            foreach ($criteria->sorting as $sorting) {
                $accessor = $this->getAccessor($query, $sorting->field, $context);

                $query->addOrderBy($accessor, $sorting->order);
            }
        }

        if ($criteria->filters) {
            $filters = $this->addFilters($query, $criteria->filters, $context);

            foreach ($filters as $filter) {
                $query->andWhere($filter);
            }
        }

        $data = $query->executeQuery()->fetchAllAssociative();

        $documents = $this->hydrate($data);

        return new Result(
            elements: $documents,
            total: $this->getTotal($query, $criteria)
        );
    }

    /**
     * @param array<Filter|Operator> $filters
     * @return array<string>
     */
    private function addFilters(QueryBuilder $query, array $filters, StorageContext $context): array
    {
        $where = [];

        foreach ($filters as $filter) {
            if ($filter instanceof Operator) {
                $where[] = $this->parseOperator($query, $filter, $context);
                continue;
            }

            $where[] = $this->parseFilter($query, $filter, $context);
        }

        return $where;
    }

    private function parseFilter(QueryBuilder $query, Filter $filter, StorageContext $context): string
    {
        $key = 'p' . Uuid::randomHex();

        $accessor = $this->getAccessor(query: $query, accessor: $filter->field, context: $context);

        $value = SchemaUtil::cast(schema: $this->schema, accessor: $filter->field, value: $filter->value);

        $property = SchemaUtil::property(accessor: $filter->field);

        $type = SchemaUtil::type(schema: $this->schema, accessor: $property);

        if ($type === FieldType::LIST) {
            return $this->handleListField($query, $filter);
        }

        if ($filter instanceof Equals && $filter->value === null) {
            return $accessor . ' IS NULL';
        }

        if ($filter instanceof Not && $filter->value === null) {
            return $accessor . ' IS NOT NULL';
        }

        if ($filter instanceof Equals) {
            $query->setParameter($key, $value);
            return $accessor . ' = :' . $key;
        }

        if ($filter instanceof Any) {
            if (!is_array($filter->value)) {
                throw new \RuntimeException(sprintf('Filter value has to be an array for any filter types. Miss match for field filter %s', $filter->field));
            }
            $query->setParameter($key, $value, $this->getType($filter->value));
            return $accessor . ' IN (:' . $key . ')';
        }

        if ($filter instanceof Not) {
            $query->setParameter($key, $value);
            return $accessor . ' != :' . $key;
        }

        if ($filter instanceof Neither) {
            if (!is_array($filter->value)) {
                throw new \RuntimeException(sprintf('Filter value has to be an array for not-any filter types. Miss match for field filter %s', $filter->field));
            }

            $query->setParameter($key, $value, $this->getType($filter->value));
            return $accessor . ' NOT IN (:' . $key . ')';
        }

        if ($filter instanceof Gt) {
            $query->setParameter($key, $value);
            return $accessor . ' > :' . $key;
        }

        if ($filter instanceof Lt) {
            $query->setParameter($key, $value);
            return $accessor . ' < :' . $key;
        }

        if ($filter instanceof Gte) {
            $query->setParameter($key, $value);
            return $accessor . ' >= :' . $key;
        }

        if ($filter instanceof Lte) {
            $query->setParameter($key, $value);
            return $accessor . ' <= :' . $key;
        }

        if ($filter instanceof Contains) {
            $query->setParameter($key, '%' . $value . '%');
            return $accessor . ' LIKE :' . $key;
        }

        if ($filter instanceof Prefix) {
            $query->setParameter($key, $value . '%');
            return $accessor . ' LIKE :' . $key;
        }

        if ($filter instanceof Suffix) {
            $query->setParameter($key, '%' . $value);
            return $accessor . ' LIKE :' . $key;
        }

        throw new \LogicException(sprintf('Unsupported filter type %s', $filter::class));
    }

    private function parseOperator(QueryBuilder $query, Operator $operator, StorageContext $context): string
    {
        if ($operator instanceof AndOperator) {
            $nested = $this->addFilters($query, $operator->filters, $context);

            return '(' . implode(' AND ', $nested) . ')';
        }

        if ($operator instanceof OrOperator) {
            $nested = $this->addFilters($query, $operator->filters, $context);

            return '(' . implode(' OR ', $nested) . ')';
        }

        if ($operator instanceof NandOperator) {
            $nested = $this->addFilters($query, $operator->filters, $context);

            return 'NOT (' . implode(' AND ', $nested) . ')';
        }

        if ($operator instanceof NorOperator) {
            $nested = $this->addFilters($query, $operator->filters, $context);

            return 'NOT (' . implode(' OR ', $nested) . ')';
        }

        throw new \LogicException(sprintf('Unsupported operator type %s', $operator::class));
    }

    private function isJson(string $field): bool
    {
        $type = SchemaUtil::type(schema: $this->schema, accessor: $field);

        $translated = SchemaUtil::translated(schema: $this->schema, accessor: $field);

        return $translated || in_array($type, [FieldType::OBJECT, FieldType::LIST, FieldType::OBJECT_LIST], true);
    }

    private function getTotal(QueryBuilder $query, Criteria $criteria): ?int
    {
        if ($criteria->total === Total::NONE) {
            return null;
        }

        $query->resetQueryPart('orderBy');

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

    /**
     * @param array<mixed> $value
     */
    private function getType(array $value): int
    {
        $first = reset($value);

        if (is_int($first)) {
            return ArrayParameterType::INTEGER;
        }

        return ArrayParameterType::STRING;
    }

    private function getAccessor(QueryBuilder $query, string $accessor, StorageContext $context): string
    {
        $parts = explode('.', $accessor);

        $field = array_shift($parts);

        $property = implode('.', $parts);

        $root = SchemaUtil::property(accessor: $accessor);


        $translated = SchemaUtil::translated(schema: $this->schema, accessor: $root);

        $type = SchemaUtil::type(schema: $this->schema, accessor: $accessor);

        $cast = match ($type) {
            FieldType::BOOL => ' RETURNING UNSIGNED',
            default => '',
        };

        $type = SchemaUtil::type(schema: $this->schema, accessor: $root);

        if ($translated) {
            $selects = [];

            $template = 'JSON_VALUE(`#field#`, "$.#property#" ' . $cast . ')';

            foreach ($context->languages as $language) {
                $selects[] = str_replace(['#field#', '#property#'], [$accessor, $language], $template);
            }

            return 'COALESCE(' . implode(', ', $selects) . ')';
        }

        if ($type === FieldType::OBJECT && !empty($property)) {
            return 'JSON_VALUE(`' . $field . '`, "$.' . $property . '"' . $cast . ')';
        }

        if ($type === FieldType::OBJECT_LIST) {
            $alias = $this->buildObjectListTable($query, $accessor);

            return '`' . $alias . '`' . '.column_value';
        }

        return '`' . $accessor . '`';
    }

    private function handleListField(QueryBuilder $query, Filter $filter): string
    {
        $key = 'key' . Uuid::randomHex();

        $keyValue = is_string($filter->value) ? 'JSON_QUOTE(:' . $key . ')' : ':' . $key;

        if ($filter->value === null && $filter instanceof Not) {
            return $filter->field . ' IS NOT NULL';
        }

        if ($filter->value === null && $filter instanceof Equals) {
            return $filter->field . ' IS NULL';
        }

        if ($filter instanceof Equals) {
            $query->setParameter($key, $filter->value);

            return 'JSON_CONTAINS(`' . $filter->field . '`, ' . $keyValue . ')';
        }

        if ($filter instanceof Not) {
            $query->setParameter($key, $filter->value);

            return 'NOT JSON_CONTAINS(`' . $filter->field . '`, ' . $keyValue . ')';
        }

        if ($filter instanceof Any) {
            $where = [];
            if (!is_array($filter->value)) {
                throw new \RuntimeException(sprintf('Filter value has to be an array for any filter types. Miss match for field filter %s', $filter->field));
            }

            foreach ($filter->value as $value) {
                $key = 'key' . Uuid::randomHex();

                $keyValue = is_string($value) ? 'JSON_QUOTE(:' . $key . ')' : ':' . $key;

                $query->setParameter($key, $value);

                $where[] = 'JSON_CONTAINS(`' . $filter->field . '`, ' . $keyValue . ')';
            }
            return '(' . implode(' OR ', $where) . ')';
        }

        if ($filter instanceof Neither) {
            $where = [];

            if (!is_array($filter->value)) {
                throw new \RuntimeException(sprintf('Filter value has to be an array for not-any filter types. Miss match for field filter %s', $filter->field));
            }

            foreach ($filter->value as $value) {
                $key = 'key' . Uuid::randomHex();

                $keyValue = is_string($value) ? 'JSON_QUOTE(:' . $key . ')' : ':' . $key;

                $query->setParameter($key, $value);

                $where[] = 'JSON_CONTAINS(`' . $filter->field . '`, ' . $keyValue . ')';
            }

            return 'NOT (' . implode(' OR ', $where) . ')';
        }

        if ($filter instanceof Contains && is_int($filter->value)) {
            $query->setParameter($key, $filter->value);

            return "JSON_CONTAINS(`" . $filter->field . "`, :" . $key . ")";
        }

        if ($filter instanceof Contains) {
            $query->setParameter($key, '%' . $filter->value . '%');

            return "JSON_SEARCH(`" . $filter->field . "`, 'one', :" . $key . ", NULL, '$[*]')";
        }

        if ($filter instanceof Prefix) {
            $query->setParameter($key, $filter->value . '%');

            return "JSON_SEARCH(`" . $filter->field . "`, 'one', :" . $key . ", NULL, '$[*]')";
        }

        if ($filter instanceof Suffix) {
            $query->setParameter($key, '%' . $filter->value);

            return "JSON_SEARCH(`" . $filter->field . "`, 'one', :" . $key . ", NULL, '$[*]')";
        }

        throw new \LogicException(sprintf('Unsupported filter type %s', $filter::class));
    }

    private function buildObjectListTable(QueryBuilder $query, string $accessor): string
    {
        $parts = explode('.', $accessor);

        $field = array_shift($parts);

        $alias = 'jt_' . Uuid::randomHex();

        $sql = <<<SQL
JSON_TABLE(#field#, '$[*]' COLUMNS (
    `column_value` #type# PATH '$.#property#'
))
SQL;

        $property = implode('.', $parts);

        $type = $this->getPropertyType($accessor);
        $sql = str_replace('#field#', $field, $sql);
        $sql = str_replace('#alias#', $alias, $sql);
        $sql = str_replace('#type#', $type, $sql);
        $sql = str_replace('#property#', $property, $sql);

        $query->from($sql, $alias);

        return $alias;
    }

    /**
     * @param array<array<string, mixed>> $data
     * @return array<Document>
     */
    private function hydrate(array $data): array
    {
        $jsons = [];
        foreach ($this->schema->fields as $field => $schema) {
            if ($this->isJson($field)) {
                $jsons[] = $field;
            }
        }

        $documents = [];
        foreach ($data as $row) {
            if (!is_string($row['key'])) {
                throw new \LogicException('Invalid data, missing key for document');
            }

            $key = $row['key'];
            unset($row['key']);

            foreach ($row as $k => $v) {
                if ($v === null) {
                    continue;
                }

                if (!in_array($k, $jsons, true)) {
                    continue;
                }
                if (!is_string($v)) {
                    throw new \RuntimeException('Invalid data type for key "' . $key . '"');
                }

                $row[$k] = json_decode($v, true);
            }

            $documents[] = new Document($key, $row);
        }

        return $documents;
    }

    private function getPropertyType(string $accessor): string
    {
        $type = SchemaUtil::type(schema: $this->schema, accessor: $accessor);

        if (!$type) {
            return 'VARCHAR(255)';
        }

        return match ($type) {
            FieldType::INT => 'INT(11)',
            FieldType::FLOAT => 'DECIMAL(10, 2)',
            FieldType::BOOL => 'TINYINT(1)',
            FieldType::DATETIME => 'DATETIME(3)',
            default => 'VARCHAR(255)',
        };
    }
}
