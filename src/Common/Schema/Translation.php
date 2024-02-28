<?php

namespace Shopware\Storage\Common\Schema;

/**
 * @template T
 */
class Translation implements \JsonSerializable, \ArrayAccess
{
    /**
     * @var null|T $resolved
     */
    public mixed $resolved;

    /**
     * @param array<string, T> $translations
     */
    public function __construct(
        public array $translations = []
    ) {}

    public function __toString(): string
    {
        return (string) $this->resolved;
    }

    public function __invoke(): mixed
    {
        return $this->resolved;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->translations[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->translations[$offset];
    }

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
}
