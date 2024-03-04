<?php

namespace Shopware\Storage\Common\Schema\Translation;

use Shopware\Storage\Common\StorageContext;

/**
 * @template TType
 * @implements \ArrayAccess<string, TType|null>
 */
class Translation implements \JsonSerializable, \ArrayAccess
{
    /**
     * @param array<string, TType|null> $translations
     * @param TType|null $resolved
     */
    public function __construct(
        public array $translations = [],
        public mixed $resolved = null
    ) {}

    public function __toString(): string
    {
        return (string) $this->resolved;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->translations[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->translations[$offset];
    }

    /**
     * @param string $offset
     * @param TType|null $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->translations[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->translations[$offset]);
    }

    public function jsonSerialize(): mixed
    {
        return $this->translations;
    }

    public function resolve(StorageContext $context): mixed
    {
        foreach ($context->languages as $language) {
            if (isset($this->translations[$language])) {
                return $this->resolved = $this->translations[$language];
            }
        }
        return null;
    }
}
