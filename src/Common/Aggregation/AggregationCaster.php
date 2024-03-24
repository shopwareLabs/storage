<?php

namespace Shopware\Storage\Common\Aggregation;

use Shopware\Storage\Common\Aggregation\Type\Aggregation;
use Shopware\Storage\Common\Aggregation\Type\Count;
use Shopware\Storage\Common\Aggregation\Type\Distinct;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\SchemaUtil;

class AggregationCaster
{
    public function cast(Collection $collection, Aggregation $aggregation, mixed $data): mixed
    {
        $type = SchemaUtil::type(collection: $collection, accessor: $aggregation->field);

        $caster = match ($type) {
            FieldType::INT => fn($value) => (float) $value,
            FieldType::FLOAT => fn($value) => round((float) $value, 6),
            FieldType::BOOL => function ($value) {
                return match (true) {
                    $value === '1', $value === 'true' => true,
                    $value === '0', $value === 'false' => false,
                    default => (bool) $value
                };
            },
            FieldType::DATETIME => function ($value) {
                return match (true) {
                    is_string($value) => (new \DateTimeImmutable($value))->format('Y-m-d H:i:s'),
                    is_int($value) => (new \DateTimeImmutable('@' . $value))->format('Y-m-d H:i:s'),
                    default => $value
                };
            },
            default => fn($value) => $value,
        };

        if ($aggregation instanceof Distinct) {
            assert(is_array($data), 'Distinct aggregation must return an array');

            $values = array_map($caster, $data);
            sort($values);
            return $values;
        }

        if ($aggregation instanceof Count) {
            assert(is_array($data), 'Count aggregation must return an array');

            $values = array_map(fn($value) => [
                'key' => $caster($value['key']),
                'count' => (int) $value['count'],
            ], $data);

            usort($values, fn($a, $b) => $a['key'] <=> $b['key']);

            return $values;
        }

        return $caster($data);
    }
}
