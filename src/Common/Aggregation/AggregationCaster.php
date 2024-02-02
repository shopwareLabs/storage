<?php

namespace Shopware\Storage\Common\Aggregation;

use Shopware\Storage\Common\Aggregation\Type\Aggregation;
use Shopware\Storage\Common\Aggregation\Type\Count;
use Shopware\Storage\Common\Aggregation\Type\Distinct;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;

class AggregationCaster
{
    public function cast(Schema $schema, Aggregation $aggregation, mixed $data): mixed
    {
        $type = SchemaUtil::type(schema: $schema, accessor: $aggregation->field);

        switch ($type) {
            case 'integer':
                $caster = fn($value) => (int) $value;
                break;
            case 'float':
                $caster = fn($value) => round((float) $value, 6);
                break;
            case 'bool':
                $caster = function ($value) {
                    if (is_bool($value)) {
                        return $value;
                    }

                    if (is_string($value)) {
                        return $value === 'true';
                    }

                    return (bool) $value;
                };
                break;
            case 'datetime':
                $caster = function ($value) {
                    if (is_string($value)) {
                        return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s.v');
                    }

                    if (is_int($value)) {
                        return (new \DateTimeImmutable('@' . $value))->format('Y-m-d H:i:s.v');
                    }

                    return $value;
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