<?php

namespace Shopware\Storage\Array;

use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
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
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\StorageContext;

/**
 * @phpstan-import-type Sorting from FilterCriteria
 */
class ArrayFilterStorage implements FilterStorage
{
    /**
     * @var array<string, Document>
     */
    private array $storage = [];

    public function __construct(private readonly Schema $schema)
    {
    }

    public function setup(): void
    {
        $this->storage = [];
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

    public function read(FilterCriteria $criteria, StorageContext $context): FilterResult
    {
        $filtered = $this->storage;

        if ($criteria->keys) {
            $filtered = array_filter($filtered, fn ($key) => in_array($key, $criteria->keys, true), ARRAY_FILTER_USE_KEY);
        }

        if ($criteria->filters) {
            $filtered = array_filter($filtered, fn (Document $document): bool => $this->match($document, $criteria->filters, $context));
        }

        if ($criteria->sorting) {
            $filtered = $this->sort($filtered, $criteria, $context);
        }

        $total = count($filtered);

        if ($criteria->page) {
            $filtered = array_slice($filtered, ($criteria->page - 1) * $criteria->limit);
        }

        if ($criteria->limit) {
            $filtered = array_slice($filtered, 0, $criteria->limit);
        }

        $total = $criteria->total ? $total : null;

        return new FilterResult(elements: $filtered, total: $total);
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
            $docValue = $this->getDocValue($document, $filter, $context);
        } catch (\LogicException) {
            return false;
        }

        $value = SchemaUtil::castValue($this->schema, $filter, $filter->value);

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

    /**
     * @param array{"field": string} $filter
     */
    private function getDocValue(Document $document, Filter|Operator $filter, StorageContext $context): mixed
    {
        $root = SchemaUtil::resolveRootFieldSchema($this->schema, $filter);

        $translated = $root['translated'] ?? false;

        $value = $document->data[$root['name']];

        if ($translated) {
            if ($value === null) {
                throw new \LogicException('Null accessor accessor');
            }

            foreach ($context->languages as $language) {
                if (isset($value[$language])) {
                    return SchemaUtil::castValue($this->schema, $filter, $value[$language]);
                }
            }

            return null;
        }

        if ($root['type'] === FieldType::OBJECT) {
            return $this->resolveAccessor($filter, $value);
        }

        if ($root['type'] === FieldType::OBJECT_LIST) {
            return array_map(fn ($item) => $this->resolveAccessor($filter, $item), $value);
        }

        return $value;
    }

    private function resolveAccessor(Filter $filter, mixed $value): mixed
    {
        $parts = explode('.', $filter->field);

        array_shift($parts);

        if (empty($parts) && $value === null) {
            return $value;
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
            };
        }

        return SchemaUtil::castValue($this->schema, $filter, $value);
    }

    /**
     * @param Document[] $filtered
     * @return Document[]
     */
    private function sort(array $filtered, FilterCriteria $criteria, StorageContext $context): array
    {
        $filtered = array_values($filtered);

        usort($filtered, function (Document $a, Document $b) use ($criteria, $context) {
            foreach ($criteria->sorting as $sorting) {
                /** @var Sorting $sorting */
                $direction = $sorting['direction'];

                try {
                    $aValue = $this->getDocValue($a, $sorting, $context);
                } catch (\LogicException) {
                    $aValue = null;
                }

                try {
                    $bValue = $this->getDocValue($b, $sorting, $context);
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
            is_array($docValue) => !empty(array_filter($docValue, fn ($item) => in_array($item, $value, true))),
            default => in_array($docValue, $value, true),
        };
    }

    /**
     * @param string[]|string $docValue
     */
    private function contains(array|string $docValue, string $value): bool
    {
        return match (true) {
            is_array($docValue) => !empty(array_filter($docValue, fn ($item) => str_contains($item, $value))),
            default => str_contains($docValue, $value),
        };
    }

    /**
     * @param string[]|string $docValue
     */
    private function startsWith(array|string $docValue, string $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn ($item) => str_starts_with($item, $value))),
            default => str_starts_with((string) $docValue, $value),
        };
    }

    /**
     * @param string[]|string $docValue
     */
    private function endsWith(array|string $docValue, string $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn ($item) => str_ends_with($item, $value))),
            default => str_ends_with((string) $docValue, $value),
        };
    }

    private function gte(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn ($item) => $item >= $value)),
            default => $docValue >= $value,
        };
    }

    private function gt(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn ($item) => $item > $value)),
            default => $docValue > $value,
        };
    }

    private function lt(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn ($item) => $item < $value)),
            default => $docValue < $value,
        };
    }

    private function lte(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn ($item) => $item <= $value)),
            default => $docValue <= $value,
        };
    }

}
