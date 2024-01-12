<?php

namespace Shopware\Storage\Common\Schema;

class SchemaUtil
{
    public static function cast(Schema $schema, string $accessor, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = self::type(schema: $schema, accessor: $accessor);

        if ($type === FieldType::INT) {
            return match (true) {
                is_array($value) => array_map(fn($v) => (int) $v, $value),
                // @phpstan-ignore-next-line
                default => (int) $value,
            };
        }

        if ($type === FieldType::FLOAT) {
            return match (true) {
                is_array($value) => array_map(fn($v) => (float) $v, $value),
                // @phpstan-ignore-next-line
                default => (float) $value,
            };
        }

        if ($type === FieldType::BOOL) {
            return match (true) {
                is_array($value) => array_map(fn($v) => (bool) $v, $value),
                default => (bool) $value,
            };
        }

        if ($type === FieldType::DATETIME) {
            return match (true) {
                is_array($value) => array_map(fn($v) => (new \DateTimeImmutable($v))->format('Y-m-d H:i:s.v'), $value),
                // @phpstan-ignore-next-line
                default => (new \DateTimeImmutable($value))->format('Y-m-d H:i:s.v'),
            };
        }

        return $value;
    }

    public static function property(string $accessor): string
    {
        $parts = explode('.', $accessor);

        return $parts[0];
    }

    public static function translated(Schema $schema, string $accessor): bool
    {
        $schema = self::fieldSchema(schema: $schema, accessor: $accessor);

        return $schema->translated;
    }

    public static function type(Schema $schema, string $accessor): string
    {
        $schema = self::fieldSchema(schema: $schema, accessor: $accessor);

        return $schema->type;
    }

    public static function fieldSchema(Schema $schema, string $accessor): Field
    {
        $property = self::property(accessor: $accessor);

        if ($property === 'key') {
            return new Field(
                name: 'key',
                type: FieldType::STRING,
                translated: false,
                fields: [],
            );
        }

        $field = $schema->fields[$property] ?? null;

        if (!$field) {
            throw new \RuntimeException(sprintf('Field %s not found in schema', $property));
        }

        if (!$field->type) {
            throw new \RuntimeException(sprintf('Field %s not found in schema', $accessor));
        }

        if (!in_array($field->type, [FieldType::OBJECT, FieldType::OBJECT_LIST], true)) {
            return $field;
        }

        $parts = explode('.', $accessor);

        array_shift($parts);

        foreach ($parts as $part) {
            $field = $field->fields[$part] ?? null;

            if (!$field instanceof Field) {
                throw new \RuntimeException(sprintf('Field %s not found in schema', $part));
            }
        }

        return $field;
    }
}
