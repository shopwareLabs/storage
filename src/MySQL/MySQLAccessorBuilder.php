<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Common\Util\Uuid;

class MySQLAccessorBuilder
{
    public function build(Collection $collection, QueryBuilder $query, string $accessor, StorageContext $context): string
    {
        $parts = explode('.', $accessor);

        $field = array_shift($parts);

        $property = implode('.', $parts);

        $root = SchemaUtil::property(accessor: $accessor);

        $translated = SchemaUtil::translated(collection: $collection, accessor: $root);

        $type = SchemaUtil::type(collection: $collection, accessor: $accessor);

        $cast = match ($type) {
            FieldType::BOOL => ' RETURNING UNSIGNED',
            default => '',
        };

        $type = SchemaUtil::type(collection: $collection, accessor: $root);

        if ($translated) {
            return $this->translated(root: '`' . $root . '`', cast: $cast, context: $context);
        }

        $translated = SchemaUtil::translated(collection: $collection, accessor: $accessor);
        if ($type === FieldType::OBJECT && !empty($property)) {
            if (!$translated) {
                return 'JSON_VALUE(`' . $field . '`, "$.' . $property . '"' . $cast . ')';
            }

            return $this->translated(root: '`' . $field . '`', cast: $cast, context: $context, path: $property);
        }

        if ($type === FieldType::OBJECT_LIST) {
            $alias = $this->buildObjectListTable($collection, $query, $accessor);

            if ($translated) {
                return $this->translated(root: $alias . '.column_value', cast: $cast, context: $context);
            }

            return '`' . $alias . '`' . '.column_value';
        }

        return '`' . $accessor . '`';
    }

    private function translated(string $root, string $cast, StorageContext $context, string $path = ''): string
    {
        if (!empty($path)) {
            $path = '.' . $path;
        }

        $selects = [];

        $template = '   JSON_VALUE(#field#, "$#path#.#language#"' . $cast . ')';
        foreach ($context->languages as $language) {
            $selects[] = str_replace(['#field#', '#path#', '#language#'], [$root, $path, $language], $template);
        }

        return PHP_EOL . 'COALESCE(' . PHP_EOL . implode(', ' . PHP_EOL, $selects) . PHP_EOL . ')';
    }

    private function buildObjectListTable(Collection $collection, QueryBuilder $query, string $accessor): string
    {
        $parts = explode('.', $accessor);

        $field = array_shift($parts);

        $alias = 'jt_' . Uuid::randomHex();

        $sql = <<<SQL
JSON_TABLE(IFNULL(`#field#`, '[{}]'), '$[*]' COLUMNS (
    `column_value` #type# PATH '$.#property#'
))
SQL;

        $property = implode('.', $parts);

        $type = $this->getPropertyType($collection, $accessor);
        $sql = str_replace('#field#', $field, $sql);
        $sql = str_replace('#alias#', $alias, $sql);
        $sql = str_replace('#type#', $type, $sql);
        $sql = str_replace('#property#', $property, $sql);

        $query->from($sql, $alias);

        return $alias;
    }

    private function getPropertyType(Collection $collection, string $accessor): string
    {
        $type = SchemaUtil::type(collection: $collection, accessor: $accessor);

        if (!$type) {
            return 'VARCHAR(255)';
        }

        $translated = SchemaUtil::translated(collection: $collection, accessor: $accessor);
        if ($translated) {
            return 'JSON';
        }

        return match ($type) {
            FieldType::INT => 'INT(11)',
            FieldType::FLOAT => 'DECIMAL(10, 2)',
            FieldType::BOOL => 'TINYINT(1)',
            FieldType::DATETIME => 'DATETIME(3)',
            FieldType::LIST => 'JSON',
            default => 'VARCHAR(255)',
        };
    }
}
