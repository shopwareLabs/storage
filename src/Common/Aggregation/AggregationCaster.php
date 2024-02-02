<?php

namespace Shopware\Storage\Common\Aggregation;

use Shopware\Storage\Common\Aggregation\Type\Aggregation;
use Shopware\Storage\Common\Aggregation\Type\Count;
use Shopware\Storage\Common\Aggregation\Type\Distinct;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;

class AggregationCaster
{
    public function cast(Schema $schema, Aggregation $aggregation, mixed $data): mixed
    {
        $type = SchemaUtil::type(schema: $schema, accessor: $aggregation->field);

        switch ($type) {
            case FieldType::INT:
                // cast to float, because of avg aggregation
                $caster = fn($value) => (float) $value;
                break;
            case FieldType::FLOAT:
                $caster = fn($value) => round((float) $value, 6);
                break;
            case FieldType::BOOL:
                $caster = function ($value) {
                    return match (true) {
                        $value === '1', $value === 'true' => true,
                        $value === '0', $value === 'false' => false,
                        default => (bool) $value
                    };
                };
                break;
            case FieldType::DATETIME:
                $caster = function ($value) {
                    return match (true) {
                        is_string($value) => (new \DateTimeImmutable($value))->format('Y-m-d H:i:s.v'),
                        is_int($value) => (new \DateTimeImmutable('@' . $value))->format('Y-m-d H:i:s.v'),
                        default => $value
                    };
                };
                break;
            default:
                $caster = fn($value) => $value;
        }

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
                'count' => (int) $value['count']
            ], $data);

            usort($values, fn($a, $b) => $a['key'] <=> $b['key']);

            return $values;
        }

        return $caster($data);
    }
}
