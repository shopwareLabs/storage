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

/**
 * @phpstan-import-type Sorting from FilterCriteria
 * @phpstan-import-type Filter from FilterCriteria
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
     * @param Filter[] $filters
     */
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
                    if (!is_array($value)) {
                        throw new \LogicException('Value must be an array');
                    }
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
                    if (!is_array($value)) {
                        throw new \LogicException('Value must be an array');
                    }
                    if ($this->equalsAny($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'contains':
                    if (!is_array($docValue) && !is_string($docValue)) {
                        throw new \LogicException('Doc value must be an array or string');
                    }
                    if (!is_string($value)) {
                        throw new \LogicException('Value must be a string');
                    }

                    if (!$this->contains($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'starts-with':
                    if (!is_array($docValue) && !is_string($docValue)) {
                        throw new \LogicException('Doc value must be an array or string');
                    }
                    if (!is_string($value)) {
                        throw new \LogicException('Value must be a string');
                    }
                    if (!$this->startsWith($docValue, $value)) {
                        return false;
                    }
                    break;
                case $type === 'ends-with':
                    if (!is_array($docValue) && !is_string($docValue)) {
                        throw new \LogicException('Doc value must be an array or string');
                    }
                    if (!is_string($value)) {
                        throw new \LogicException('Value must be a string');
                    }
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
                    if (!isset($filter['queries'])) {
                        throw new \LogicException('Missing queries in and query');
                    }

                    /** @var Filter $query */
                    foreach ($filter['queries'] as $query) {
                        if (!$this->match($document, [$query], $context)) {
                            return false;
                        }
                    }
                    break;
                case $type === 'or':
                    if (!isset($filter['queries'])) {
                        throw new \LogicException('Missing queries');
                    }
                    /** @var Filter $query */
                    foreach ($filter['queries'] as $query) {
                        if ($this->match($document, [$query], $context)) {
                            return true;
                        }
                    }
                    break;
                case $type === 'nand':
                    if (!isset($filter['queries'])) {
                        throw new \LogicException('Missing queries');
                    }
                    /** @var Filter $query */
                    foreach ($filter['queries'] as $query) {
                        if ($this->match($document, [$query], $context)) {
                            return false;
                        }
                    }
                    break;
                case $type === 'nor':
                    if (!isset($filter['queries'])) {
                        throw new \LogicException('Missing queries');
                    }
                    /** @var Filter $query */
                    foreach ($filter['queries'] as $query) {
                        if (!$this->match($document, [$query], $context)) {
                            return true;
                        }
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * @param array{"field": string} $filter
     */
    private function getDocValue(Document $document, array $filter, StorageContext $context): mixed
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

    /**
     * @param array{"field": string} $filter
     */
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
