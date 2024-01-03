<?php

namespace Shopware\Storage\Common\Schema;

use Shopware\Storage\Common\Filter\Type\Filter;

/**
 * @phpstan-import-type Field from Schema
 */
class SchemaUtil
{
    public static function castValue(Schema $schema, Filter $filter, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $field = self::resolveFieldSchema($schema, $filter);

        if ($field['type'] === FieldType::INT) {
            return match (true) {
                is_array($value) => array_map(fn ($v) => (int) $v, $value),
                default => (int) $value,
            };
        }

        if ($field['type'] === FieldType::FLOAT) {
            return match (true) {
                is_array($value) => array_map(fn ($v) => (float) $v, $value),
                default => (float) $value,
            };
        }

        if ($field['type'] === FieldType::BOOL) {
            return match (true) {
                is_array($value) => array_map(fn ($v) => (bool) $v, $value),
                default => (bool) $value,
            };
        }

        if ($field['type'] === FieldType::DATETIME) {
            return match (true) {
                is_array($value) => array_map(fn ($v) => (new \DateTimeImmutable($v))->format('Y-m-d H:i:s.v'), $value),
                default => (new \DateTimeImmutable($value))->format('Y-m-d H:i:s.v'),
            };
        }

        return $value;
    }

    /**
     * @return Field&array{"name": string}
     */
    public static function resolveRootFieldSchema(Schema $schema, Filter $filter): array
    {
        $parts = explode('.', $filter->field);

        $field = $schema->fields[$parts[0]] ?? null;

        if (!$field) {
            throw new \RuntimeException(sprintf('Root field %s not found in schema', $filter->field));
        }

        $field['name'] = $parts[0];

        return $field;
    }

    /**
     * @return Field
     */
    public static function resolveFieldSchema(Schema $schema, Filter $filter): array
    {
        $field = self::resolveRootFieldSchema($schema, $filter);

        if (!$field) {
            throw new \RuntimeException(sprintf('Field %s not found in schema', $filter->field));
        }

        if (!in_array($field['type'], [FieldType::OBJECT, FieldType::OBJECT_LIST], true)) {
            return $field;
        }

        $parts = explode('.', $filter->field);

        // remove first element
        array_shift($parts);

        foreach ($parts as $part) {
            $field = $field['fields'][$part] ?? null;

            if (!$field) {
                throw new \RuntimeException(sprintf('Field %s not found in schema', $part));
            }
        }

        return $field;
    }
}
