<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Common\Util\Uuid;
use Shopware\Storage\MySQL\Util\MultiInsert;

class MySQLFilterStorage implements FilterStorage
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Schema $schema
    ) {
    }


    public function setup(): void
    {
        // todo@o.skroblin auto setup feature
    }

    public function remove(array $keys): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ' . $this->schema->source . ' WHERE `key` IN (:keys)',
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

    public function read(FilterCriteria $criteria, StorageContext $context): FilterResult
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('root.*');
        $query->from($this->schema->source, 'root');

        if ($criteria->limit) {
            $query->setMaxResults($criteria->limit);
        }

        if ($criteria->page) {
            $query->setFirstResult(($criteria->page - 1) * $criteria->limit);
        }

        if ($criteria->keys) {
            $query->andWhere('`key` IN (:keys)');
            $query->setParameter('keys', $criteria->keys, Connection::PARAM_STR_ARRAY);
        }

        if ($criteria->sorting) {
            foreach ($criteria->sorting as $sorting) {
                $accessor = $this->getAccessor($query, $sorting, $context);

                $query->addOrderBy($accessor, $sorting['direction']);
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

        return new FilterResult(
            elements: $documents,
            total: $this->getTotal($query, $criteria)
        );
    }

    private function addFilters(QueryBuilder $query, array $filters, StorageContext $context): array
    {
        $where = [];

        foreach ($filters as $filter) {
            $key = 'key' . Uuid::randomHex();

            $accessor = $this->getAccessor($query, $filter, $context);

            $type = $filter['type'];

            $schema = SchemaUtil::resolveRootFieldSchema($this->schema, $filter);

            $value = SchemaUtil::castValue($this->schema, $filter, $filter['value']);

            $json = $this->isJson($schema['name']);

            switch (true) {
                case $schema['type'] === FieldType::LIST:
                    $where[] = $this->handleListField($query, $filter);
                    break;
                case $type === 'equals' && $filter['value'] === null:
                    $value = $json ? "= 'null'" : 'IS NULL';

                    $where[] = $accessor . ' ' . $value;
                    break;
                case $type === 'not' && $filter['value'] === null:
                    $value = $json ? "!= 'null'" : 'IS NOT NULL';

                    $where[] = $accessor . ' ' . $value;
                    break;
                case $type === 'equals':
                    $where[] = $accessor . ' = :' . $key;
                    $query->setParameter($key, $value);
                    break;
                case $type === 'equals-any':
                    $where[] = $accessor . ' IN (:' . $key . ')';
                    $query->setParameter($key, $value, $this->getType($filter['value']));
                    break;
                case $type === 'not':
                    $where[] = $accessor . ' != :' . $key;
                    $query->setParameter($key, $value);
                    break;
                case $type === 'not-any':
                    $where[] = $accessor . ' NOT IN (:' . $key . ')';
                    $query->setParameter($key, $value, $this->getType($value));
                    break;
                case $type === 'contains':
                    $where[] = $accessor . ' LIKE :' . $key;
                    $query->setParameter($key, '%' . $value . '%');
                    break;
                case $type === 'starts-with':
                    $where[] = $accessor . ' LIKE :' . $key;
                    $query->setParameter($key, $value . '%');
                    break;
                case $type === 'ends-with':
                    $where[] = $accessor . ' LIKE :' . $key;
                    $query->setParameter($key, '%' . $value);
                    break;
                case $type === 'gte':
                    $where[] = $accessor . ' >= :' . $key;
                    $query->setParameter($key, $value);
                    break;
                case $type === 'lte':
                    $where[] = $accessor . ' <= :' . $key;
                    $query->setParameter($key, $value);
                    break;
                case $type === 'gt':
                    $where[] = $accessor . ' > :' . $key;
                    $query->setParameter($key, $value);
                    break;
                case $type === 'lt':
                    $where[] = $accessor . ' < :' . $key;
                    $query->setParameter($key, $value);
                    break;
                case $type === 'and':
                    $nested = $this->addFilters($query, $filter['queries'], $context);
                    $where[] = '(' . implode(' AND ', $nested) . ')';
                    break;
                case $type === 'or':
                    $nested = $this->addFilters($query, $filter['queries'], $context);
                    $where[] = '(' . implode(' OR ', $nested) . ')';
                    break;
                case $type === 'nand':
                    $nested = $this->addFilters($query, $filter['queries'], $context);
                    $where[] = 'NOT (' . implode(' AND ', $nested) . ')';
                    // no break
                case $type === 'nor':
                    $nested = $this->addFilters($query, $filter['queries'], $context);
                    $where[] = 'NOT (' . implode(' OR ', $nested) . ')';
                    break;
            }
        }

        return $where;
    }

    private function isJson(string $field): bool
    {
        $schema = $this->schema->fields[$field] ?? null;

        $translated = $schema['translated'] ?? false;

        return $translated || in_array($schema['type'], [FieldType::OBJECT, FieldType::LIST, FieldType::OBJECT_LIST], true);
    }

    private function getTotal(QueryBuilder $query, FilterCriteria $criteria): ?int
    {
        if (!$criteria->total) {
            return null;
        }

        $query->resetQueryPart('orderBy');

        $query->select('COUNT(*)');

        return (int) $query->executeQuery()->fetchOne();
    }

    private function getType(array $value): int
    {
        $first = reset($value);

        if (is_int($first)) {
            return ArrayParameterType::INTEGER;
        }

        return ArrayParameterType::STRING;
    }

    private function getAccessor(QueryBuilder $query, array $filter, StorageContext $context): string
    {
        $parts = explode('.', $filter['field']);

        if (!isset($this->schema->fields[$parts[0]])) {
            throw new \LogicException('Unknown field: ' . $parts[0]);
        }

        $fieldSchema = $this->schema->fields[$parts[0]];

        $field = array_shift($parts);

        $property = implode('.', $parts);

        $type = $fieldSchema['type'];

        $translated = $fieldSchema['translated'] ?? false;

        if ($translated) {
            $selects = [];

            $template = '(IF(JSON_TYPE(JSON_EXTRACT(#field#, "$.#property#")) != "NULL", JSON_EXTRACT(#field#, "$.#property#"), NULL))';
            foreach ($context->languages as $language) {
                $selects[] = str_replace(['#field#', '#property#'], [$filter['field'], $language], $template);
            }

            return 'COALESCE(' . implode(', ', $selects) . ')';
        }

        if ($type === FieldType::OBJECT) {
            return 'JSON_UNQUOTE(JSON_EXTRACT(`' . $field . '`, "$.' . $property . '"))';
        }

        if ($type === FieldType::OBJECT_LIST) {
            $alias = $this->buildObjectListTable($query, $filter);

            return '`'. $alias .'`' . '.column_value';
        }

        return '`' . $filter['field'] . '`';
    }

    private function handleListField(QueryBuilder $query, array $filter): string
    {
        $type = $filter['type'];

        $key = 'key' . Uuid::randomHex();

        $keyValue = is_string($filter['value']) ? 'JSON_QUOTE(:' . $key . ')' : ':' . $key;

        switch (true) {
            case ($filter['value'] === null && $type === 'not'):
                return $filter['field'] . ' IS NOT NULL';

            case ($filter['value'] === null && $type === 'equals'):
                return $filter['field'] . ' IS NULL';

            case ($type === 'equals'):
                $query->setParameter($key, $filter['value']);

                return 'JSON_CONTAINS(`' . $filter['field'] . '`, ' . $keyValue . ')';

            case ($type === 'not'):
                $query->setParameter($key, $filter['value']);

                return 'NOT JSON_CONTAINS(`' . $filter['field'] . '`, ' . $keyValue . ')';

            case ($type === 'equals-any'):
                $where = [];
                foreach ($filter['value'] as $value) {
                    $key = 'key' . Uuid::randomHex();

                    $keyValue = is_string($value) ? 'JSON_QUOTE(:' . $key . ')' : ':' . $key;

                    $query->setParameter($key, $value);

                    $where[] = 'JSON_CONTAINS(`' . $filter['field'] . '`, ' . $keyValue . ')';
                }
                return '(' . implode(' OR ', $where) . ')';

            case ($type === 'not-any'):
                $where = [];

                foreach ($filter['value'] as $value) {
                    $key = 'key' . Uuid::randomHex();

                    $keyValue = is_string($value) ? 'JSON_QUOTE(:' . $key . ')' : ':' . $key;

                    $query->setParameter($key, $value);

                    $where[] = 'JSON_CONTAINS(`' . $filter['field'] . '`, ' . $keyValue . ')';
                }

                return 'NOT (' . implode(' OR ', $where) . ')';

            case ($type === 'contains' && is_int($filter['value'])):
                $query->setParameter($key, $filter['value']);

                return "JSON_CONTAINS(`" . $filter['field'] . "`, :" . $key . ")";
            case ($type === 'contains'):
                $query->setParameter($key, '%' . $filter['value'] . '%');

                return "JSON_SEARCH(`" . $filter['field'] . "`, 'one', :" . $key . ", NULL, '$[*]')";
        }

        throw new \RuntimeException('Unknown filter type: ' . $type);
    }

    private function buildObjectListTable(QueryBuilder $query, array $filter): string
    {
        $parts = explode('.', $filter['field']);

        $field = array_shift($parts);

        $alias = 'jt_' . Uuid::randomHex();

        $sql = <<<SQL

JSON_TABLE(#field#, '$[*]' COLUMNS (
    `column_value` #type# PATH '$.#property#'
))
SQL;

        $property = implode('.', $parts);

        $type = $this->getPropertyType($field, $property);
        $sql = str_replace('#field#', $field, $sql);
        $sql = str_replace('#alias#', $alias, $sql);
        $sql = str_replace('#type#', $type, $sql);
        $sql = str_replace('#property#', $property, $sql);

        $query->from($sql, $alias);

        return $alias;
    }

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
            $key = $row['key'];
            unset($row['key']);

            foreach ($row as $k => $v) {
                if ($v === null) {
                    continue;
                }

                if (!in_array($k, $jsons, true)) {
                    continue;
                }

                $row[$k] = json_decode($v, true);
            }

            $documents[] = new Document($key, $row);
        }

        return $documents;
    }

    private function getPropertyType(string $field, string $property): string
    {
        $schema = $this->schema->fields[$field] ?? null;

        if (!$schema) {
            return 'VARCHAR(255)';
        }

        $parts = explode('.', $property);
        foreach ($parts as $part) {
            $schema = $schema['fields'][$part] ?? null;

            if (!$schema) {
                return 'VARCHAR(255)';
            }
        }

        switch ($schema['type']) {
            case FieldType::INT:
                return 'INT(11)';
            case FieldType::FLOAT:
                return 'DECIMAL(10, 2)';
            case FieldType::BOOL:
                return 'TINYINT(1)';
            case FieldType::DATETIME:
                return 'DATETIME(3)';
            default:
                return 'VARCHAR(255)';
        }
    }
}
