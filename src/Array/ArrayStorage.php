<?php

namespace Shopware\Storage\Array;

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
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Filter\Paging\Limit;
use Shopware\Storage\Common\Filter\Result;
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
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Common\Total;

class ArrayStorage extends ArrayKeyStorage implements FilterAware, AggregationAware
{
    /**
     * @var array<string, Document>
     */
    private array $storage = [];

    public function __construct(
        private readonly AggregationCaster $caster,
        private readonly Schema            $schema
    ) {}

    public function setup(): void
    {
        $this->storage = [];
    }

    public function get(string $key): ?Document
    {
        return $this->storage[$key] ?? null;
    }

    public function mget(array $keys): Documents
    {
        $documents = new Documents();

        foreach ($keys as $key) {
            if (!isset($this->storage[$key])) {
                continue;
            }

            $documents->set($key, $this->storage[$key]);
        }

        return $documents;
    }

    public function remove(array $keys): void
    {
        foreach ($keys as $key) {
            unset($this->storage[$key]);
        }
    }

    public function store(Documents $documents): void
    {
        foreach ($documents as $document) {
            $this->storage[$document->key] = $document;
        }
    }

    public function aggregate(array $aggregations, Criteria $criteria, StorageContext $context): array
    {
        $filtered = $this->storage;

        if ($criteria->primaries) {
            $filtered = array_filter($filtered, fn($key) => in_array($key, $criteria->primaries, true), ARRAY_FILTER_USE_KEY);
        }

        if ($criteria->filters) {
            $filtered = array_filter($filtered, fn(Document $document): bool => $this->match($document, $criteria->filters, $context));
        }

        $result = [];
        foreach ($aggregations as $aggregation) {
            // reset to root filter to not apply aggregations specific filters
            $documents = $filtered;

            if ($aggregation->filters) {
                $documents = array_filter($documents, fn(Document $document): bool => $this->match($document, $aggregation->filters, $context));
            }

            $value = $this->parseAggregation(
                filtered: $documents,
                aggregation: $aggregation,
                context: $context
            );

            $result[$aggregation->name] = $this->caster->cast(
                schema: $this->schema,
                aggregation: $aggregation,
                data: $value
            );
        }

        return $result;
    }

    public function filter(Criteria $criteria, StorageContext $context): Result
    {
        $filtered = $this->storage;

        if ($criteria->primaries) {
            $filtered = array_filter($filtered, fn($key) => in_array($key, $criteria->primaries, true), ARRAY_FILTER_USE_KEY);
        }

        if ($criteria->filters) {
            $filtered = array_filter($filtered, fn(Document $document): bool => $this->match($document, $criteria->filters, $context));
        }

        if ($criteria->sorting) {
            $filtered = $this->sort($filtered, $criteria, $context);
        }

        $total = count($filtered);

        if ($criteria->paging instanceof Page) {
            $filtered = array_slice($filtered, ($criteria->paging->page - 1) * $criteria->paging->limit, $criteria->paging->limit);
        } elseif ($criteria->paging instanceof Limit) {
            $filtered = array_slice($filtered, 0, $criteria->paging->limit);
        }

        switch ($criteria->total) {
            case Total::NONE:
                $total = null;
                break;
            case Total::EXACT:
            case Total::MORE:
                break;
        }

        return new Result(elements: $filtered, total: $total);
    }

    /**
     * @param array<Document> $filtered
     */
    private function parseAggregation(array $filtered, Aggregation $aggregation, StorageContext $context): mixed
    {
        $values = [];
        foreach ($filtered as $document) {
            $nested = $this->getDocValue(
                document: $document,
                accessor: $aggregation->field,
                context: $context
            );
            if (!is_array($nested)) {
                $values[] = $nested;
                continue;
            }
            foreach ($nested as $item) {
                $values[] = $item;
            }
        }

        if ($aggregation instanceof Min) {
            return min($values);
        }

        if ($aggregation instanceof Max) {
            return max($values);
        }

        if ($aggregation instanceof Sum) {
            return array_sum($values);
        }

        if ($aggregation instanceof Avg) {
            return array_sum($values) / count($values);
        }

        if ($aggregation instanceof Distinct) {
            return array_unique($values);
        }

        if ($aggregation instanceof Count) {
            $mapped = [];
            assert(is_array($values), 'Count aggregation must return an array');
            foreach ($values as $value) {
                $key = (string) $value;

                if (!isset($mapped[$key])) {
                    $mapped[$key] = ['key' => $value, 'count' => 0];
                }

                $mapped[$key]['count']++;
            }

            return $mapped;
        }

        throw new \LogicException(sprintf('Unknown aggregation type %s', get_class($aggregation)));
    }

    /**
     * @param array<Operator|Filter> $filters
     */
    private function match(Document $document, array $filters, StorageContext $context): bool
    {
        foreach ($filters as $filter) {
            if ($filter instanceof Operator) {
                $match = $this->parseOperator($document, $filter, $context);

                if (!$match) {
                    return false;
                }

                continue;
            }

            $match = $this->parseFilter($document, $filter, $context);

            if (!$match) {
                return false;
            }
        }

        return true;
    }

    private function parseFilter(Document $document, Filter $filter, StorageContext $context): bool
    {
        try {
            $docValue = $this->getDocValue($document, $filter->field, $context);
        } catch (\LogicException) {
            return false;
        }

        $value = SchemaUtil::cast($this->schema, $filter->field, $filter->value);

        if ($filter instanceof Equals) {
            return $this->equals($docValue, $value);
        }

        if ($filter instanceof Any) {
            if (!is_array($value)) {
                throw new \LogicException('Value must be an array');
            }

            return $this->equalsAny($docValue, $value);
        }

        if ($filter instanceof Not) {
            return !$this->equals($docValue, $value);
        }

        if ($filter instanceof Neither) {
            if (!is_array($value)) {
                throw new \LogicException('Value must be an array');
            }

            return !$this->equalsAny($docValue, $value);
        }

        if ($filter instanceof Contains) {
            if (!is_array($docValue) && !is_string($docValue)) {
                throw new \LogicException('Doc value must be an array or string');
            }
            if (!is_string($value)) {
                throw new \LogicException('Value must be a string');
            }

            return $this->contains($docValue, $value);
        }

        if ($filter instanceof Prefix) {
            if (!is_array($docValue) && !is_string($docValue)) {
                throw new \LogicException('Doc value must be an array or string');
            }
            if (!is_string($value)) {
                throw new \LogicException('Value must be a string');
            }

            return $this->startsWith($docValue, $value);
        }

        if ($filter instanceof Suffix) {
            if (!is_array($docValue) && !is_string($docValue)) {
                throw new \LogicException('Doc value must be an array or string');
            }
            if (!is_string($value)) {
                throw new \LogicException('Value must be a string');
            }

            return $this->endsWith($docValue, $value);
        }

        if ($filter instanceof Gte) {
            return $this->gte($docValue, $value);
        }

        if ($filter instanceof Lte) {
            return $this->lte($docValue, $value);
        }

        if ($filter instanceof Gt) {
            return $this->gt($docValue, $value);
        }

        if ($filter instanceof Lt) {
            return $this->lt($docValue, $value);
        }

        throw new \LogicException('Unknown filter type');
    }

    private function parseOperator(Document $document, Operator $operator, StorageContext $context): bool
    {
        if ($operator instanceof AndOperator) {
            foreach ($operator->filters as $filter) {
                if (!$this->match($document, [$filter], $context)) {
                    return false;
                }
            }
        }

        if ($operator instanceof OrOperator) {
            foreach ($operator->filters as $filter) {
                if ($this->match($document, [$filter], $context)) {
                    return true;
                }
            }
        }

        if ($operator instanceof NandOperator) {
            foreach ($operator->filters as $filter) {
                if ($this->match($document, [$filter], $context)) {
                    return false;
                }
            }
        }

        if ($operator instanceof NorOperator) {
            foreach ($operator->filters as $filter) {
                if (!$this->match($document, [$filter], $context)) {
                    return true;
                }
            }
        }

        throw new \LogicException(sprintf('Unknown operator type %s', $operator::class));
    }

    private function getDocValue(Document $document, string $accessor, StorageContext $context): mixed
    {
        $property = SchemaUtil::property($accessor);

        $translated = SchemaUtil::translated($this->schema, $property);

        $value = $document->data[$property];

        if ($translated) {
            if ($value === null) {
                throw new \LogicException('Null accessor accessor');
            }

            foreach ($context->languages as $language) {
                if (!is_array($value)) {
                    throw new \LogicException('Translated field value is not an array');
                }
                if (isset($value[$language])) {
                    return SchemaUtil::cast($this->schema, $accessor, $value[$language]);
                }
            }

            return null;
        }

        $type = SchemaUtil::type($this->schema, $property);

        if ($type === FieldType::OBJECT) {
            return $this->resolveAccessor($accessor, $value);
        }

        if ($type === FieldType::OBJECT_LIST) {
            if (!is_array($value)) {
                throw new \LogicException('Accessor is not an array');
            }
            return array_map(fn($item) => $this->resolveAccessor($accessor, $item), $value);
        }

        return $value;
    }

    private function resolveAccessor(string $accessor, mixed $value): mixed
    {
        $parts = explode('.', $accessor);

        array_shift($parts);

        if (empty($parts) && $value === null) {
            return null;
        }

        if ($value === null) {
            throw new \LogicException('Null accessor accessor');
        }
        if (!is_array($value)) {
            throw new \LogicException('Accessor is not an array');
        }

        foreach ($parts as $part) {
            $value = $value[$part];

            if ($value === null) {
                throw new \LogicException('Null accessor accessor');
            }
        }

        return SchemaUtil::cast($this->schema, $accessor, $value);
    }

    /**
     * @param Document[] $filtered
     * @return Document[]
     */
    private function sort(array $filtered, Criteria $criteria, StorageContext $context): array
    {
        $filtered = array_values($filtered);

        usort($filtered, function (Document $a, Document $b) use ($criteria, $context) {
            foreach ($criteria->sorting as $sorting) {
                $direction = $sorting->order;

                try {
                    $aValue = $this->getDocValue($a, $sorting->field, $context);
                } catch (\LogicException) {
                    $aValue = null;
                }

                try {
                    $bValue = $this->getDocValue($b, $sorting->field, $context);
                } catch (\LogicException) {
                    $bValue = null;
                }

                if ($aValue === $bValue) {
                    continue;
                }

                if ($direction === 'ASC') {
                    return $aValue <=> $bValue;
                }

                return $bValue <=> $aValue;
            }
            return 0;
        });
        return $filtered;
    }

    private function equals(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => in_array($value, $docValue, true),
            default => $docValue === $value,
        };
    }

    /**
     * @param array<mixed> $value
     */
    private function equalsAny(mixed $docValue, array $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => in_array($item, $value, true))),
            default => in_array($docValue, $value, true),
        };
    }

    /**
     * @param string[]|string $docValue
     */
    private function contains(array|string $docValue, string $value): bool
    {
        return match (true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => str_contains($item, $value))),
            default => str_contains($docValue, $value),
        };
    }

    /**
     * @param string[]|string $docValue
     */
    private function startsWith(array|string $docValue, string $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => str_starts_with($item, $value))),
            default => str_starts_with((string) $docValue, $value),
        };
    }

    /**
     * @param string[]|string $docValue
     */
    private function endsWith(array|string $docValue, string $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => str_ends_with($item, $value))),
            default => str_ends_with((string) $docValue, $value),
        };
    }

    private function gte(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => $item >= $value)),
            default => $docValue >= $value,
        };
    }

    private function gt(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => $item > $value)),
            default => $docValue > $value,
        };
    }

    private function lt(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => $item < $value)),
            default => $docValue < $value,
        };
    }

    private function lte(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => $item <= $value)),
            default => $docValue <= $value,
        };
    }
}
