<?php

namespace Shopware\Storage\Array;

use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\StorageContext;

class ArrayFilterStorage implements FilterStorage
{
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
            $filtered = array_filter($filtered, fn($key) => in_array($key, $criteria->keys, true), ARRAY_FILTER_USE_KEY);
        }

        if ($criteria->filters) {
            $filtered = array_filter($filtered, fn(Document $document): bool => $this->match($document, $criteria->filters, $context));
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

    private function match(Document $document, array $filters, StorageContext $context): bool
    {
        foreach ($filters as $filter) {
            try {
                $docValue = $this->getDocValue($document, $filter, $context);
            } catch (\LogicException) {
                return false;
            }

            $value = SchemaUtil::castValue($this->schema, $filter, $filter['value']);

            $type = $filter['type'];

            switch(true) {
                case $type === 'equals':
                    if (!$this->equals($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'equals-any':
                    if (!$this->equalsAny($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'not':
                    if ($this->equals($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'not-any':
                    if ($this->equalsAny($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'contains':
                    if (!$this->contains($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'starts-with':
                    if (!$this->startsWith($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'ends-with':
                    if (!$this->endsWith($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'gte':
                    if (!$this->gte($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'lte':
                    if (!$this->lte($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'gt':
                    if (!$this->gt($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'lt':
                    if (!$this->lt($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'and':
                    foreach ($filter['queries'] as $query) {
                        if (!$this->match($document, $query, $context)) {
                            return false;
                        }
                    }
                    break;
                case $type === 'or':
                    foreach ($filter['queries'] as $query) {
                        if ($this->match($document, $query, $context)) {
                            return true;
                        }
                    }
                    break;
                case $type === 'nand':
                    foreach ($filter['queries'] as $query) {
                        if ($this->match($document, $query, $context)) {
                            return false;
                        }
                    }
                    break;
                case $type === 'nor':
                    foreach ($filter['queries'] as $query) {
                        if (!$this->match($document, $query, $context)) {
                            return true;
                        }
                    }
                    break;
            }
        }

        return true;
    }

    private function getDocValue(Document $document, array $filter, StorageContext $context)
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
            return array_map(fn($item) => $this->resolveAccessor($filter, $item), $value);
        }

        return $value;
    }

    private function resolveAccessor(array $filter, mixed $value): mixed
    {
        $parts = explode('.', $filter['field']);

        array_shift($parts);

        if (empty($parts) && $value === null) {
            return $value;
        }

        if ($value === null) {
            throw new \LogicException('Null accessor accessor');
        }

        foreach ($parts as $part) {
            $value = $value[$part];

            if ($value === null) {
                throw new \LogicException('Null accessor accessor');
            };
        }

        return SchemaUtil::castValue($this->schema, $filter, $value);
    }

    private function sort(array $filtered, FilterCriteria $criteria, StorageContext $context): array
    {
        $filtered = array_values($filtered);

        usort($filtered, function (Document $a, Document $b) use ($criteria, $context) {
            foreach ($criteria->sorting as $sorting) {
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

    private function equalsAny(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => in_array($item, $value, true))),
            default => in_array($docValue, $value, true),
        };
    }

    private function contains(mixed $docValue, mixed $value): bool
    {
        return match (true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => str_contains($item, $value))),
            default => str_contains($docValue, $value),
        };
    }

    private function startsWith(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => str_starts_with($item, $value))),
            default => str_starts_with($docValue, $value),
        };
    }

    private function endsWith(mixed $docValue, mixed $value): bool
    {
        return match(true) {
            is_array($docValue) => !empty(array_filter($docValue, fn($item) => str_ends_with($item, $value))),
            default => str_ends_with($docValue, $value),
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
