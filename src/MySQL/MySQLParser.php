<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Storage\Common\Filter\Operator\AndOperator;
use Shopware\Storage\Common\Filter\Operator\NandOperator;
use Shopware\Storage\Common\Filter\Operator\NorOperator;
use Shopware\Storage\Common\Filter\Operator\Operator;
use Shopware\Storage\Common\Filter\Operator\OrOperator;
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
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Common\Util\Uuid;

class MySQLParser
{
    public function __construct(private readonly MySQLAccessorBuilder $accessor) {}

    /**
     * @param array<Filter|Operator> $filters
     * @return array<string>
     */
    public function parseFilters(Collection $collection, QueryBuilder $query, array $filters, StorageContext $context): array
    {
        $where = [];

        foreach ($filters as $filter) {
            if ($filter instanceof Operator) {
                $where[] = $this->parseOperator($collection, $query, $filter, $context);
                continue;
            }

            $where[] = $this->parseFilter($collection, $query, $filter, $context);
        }

        return $where;
    }

    private function parseFilter(Collection $collection, QueryBuilder $query, Filter $filter, StorageContext $context): string
    {
        $key = 'p' . Uuid::randomHex();

        $accessor = $this->accessor->build(collection: $collection, query: $query, accessor: $filter->field, context: $context);

        $value = SchemaUtil::cast(collection: $collection, accessor: $filter->field, value: $filter->value);

        $type = SchemaUtil::type(collection: $collection, accessor: $filter->field);

        if ($type === FieldType::LIST) {
            return $this->handleListField(query: $query, filter: $filter, accessor: $accessor);
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

    private function parseOperator(Collection $collection, QueryBuilder $query, Operator $operator, StorageContext $context): string
    {
        if ($operator instanceof AndOperator) {
            $nested = $this->parseFilters($collection, $query, $operator->filters, $context);

            return '(' . implode(' AND ', $nested) . ')';
        }

        if ($operator instanceof OrOperator) {
            $nested = $this->parseFilters($collection, $query, $operator->filters, $context);

            return '(' . implode(' OR ', $nested) . ')';
        }

        if ($operator instanceof NandOperator) {
            $nested = $this->parseFilters($collection, $query, $operator->filters, $context);

            return 'NOT (' . implode(' AND ', $nested) . ')';
        }

        if ($operator instanceof NorOperator) {
            $nested = $this->parseFilters($collection, $query, $operator->filters, $context);

            return 'NOT (' . implode(' OR ', $nested) . ')';
        }

        throw new \LogicException(sprintf('Unsupported operator type %s', $operator::class));
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

    private function handleListField(QueryBuilder $query, Filter $filter, string $accessor): string
    {
        $key = 'key' . Uuid::randomHex();

        $keyValue = is_string($filter->value) ? 'JSON_QUOTE(:' . $key . ')' : ':' . $key;

        $property = SchemaUtil::property(accessor: $filter->field);

        if ($filter->value === null && $filter instanceof Not) {
            return $accessor . ' IS NOT NULL';
        }

        if ($filter->value === null && $filter instanceof Equals) {
            if ($property !== $filter->field) {
                // property is null or accessor is null
                return '(' . $accessor . ' IS NULL OR `' . $property . '` IS NULL)';
            }

            return $accessor . ' IS NULL';
        }

        if ($filter instanceof Equals) {
            $query->setParameter($key, $filter->value);

            return 'JSON_CONTAINS(' . $accessor . ', ' . $keyValue . ')';
        }

        if ($filter instanceof Not) {
            $query->setParameter($key, $filter->value);

            return 'NOT JSON_CONTAINS(' . $accessor . ', ' . $keyValue . ')';
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

                $where[] = 'JSON_CONTAINS(' . $accessor . ', ' . $keyValue . ')';
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

                $where[] = 'JSON_CONTAINS(' . $accessor . ', ' . $keyValue . ')';
            }

            return 'NOT (' . implode(' OR ', $where) . ')';
        }

        if ($filter instanceof Contains && is_int($filter->value)) {
            $query->setParameter($key, $filter->value);

            return "JSON_CONTAINS(" . $accessor . ", :" . $key . ")";
        }

        if ($filter instanceof Contains) {
            $query->setParameter($key, '%' . $filter->value . '%');

            return "JSON_SEARCH(" . $accessor . ", 'one', :" . $key . ", NULL, '$[*]')";
        }

        if ($filter instanceof Prefix) {
            $query->setParameter($key, $filter->value . '%');

            return "JSON_SEARCH(" . $accessor . ", 'one', :" . $key . ", NULL, '$[*]')";
        }

        if ($filter instanceof Suffix) {
            $query->setParameter($key, '%' . $filter->value);

            return "JSON_SEARCH(" . $accessor . ", 'one', :" . $key . ", NULL, '$[*]')";
        }

        throw new \LogicException(sprintf('Unsupported filter type %s', $filter::class));
    }
}
