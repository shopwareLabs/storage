<?php

namespace Shopware\Storage\Common\Document;

use Traversable;

/**
 * @template TElement
 *
 * @implements \IteratorAggregate<array-key, TElement>
 */
class Collection implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @var array<array-key, TElement>
     */
    protected array $elements = [];

    /**
     * @param array<array-key, TElement> $elements
     */
    public function __construct(array $elements = [])
    {
        $this->elements = $elements;
    }

    /**
     * @return TElement|null
     */
    public function first()
    {
        $key = array_key_first($this->elements);

        return $key !== null ? $this->elements[$key] : null;
    }

    /**
     * @param TElement $element
     */
    public function add($element): void
    {
        $this->elements[] = $element;
    }

    /**
     * @param array-key $key
     * @param TElement $element
     */
    public function set($key, $element): void
    {
        $this->elements[$key] = $element;
    }

    /**
     * @param array-key $key
     *
     * @return TElement|null
     */
    public function get($key)
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    /**
     * @param array-key $key
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    public function clear(): void
    {
        $this->elements = [];
    }

    /**
     * @return array-key[]
     */
    public function keys(): array
    {
        return array_keys($this->elements);
    }

    /**
     * @return list<TElement>
     */
    public function map(\Closure $closure): array
    {
        return array_map($closure, $this->elements);
    }

    public function sort(\Closure $closure): void
    {
        uasort($this->elements, $closure);
    }

    /**
     * @param array-key $key
     */
    public function remove($key): void
    {
        unset($this->elements[$key]);
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return array_values($this->elements);
    }

    public function getIterator(): Traversable
    {
        yield from $this->elements;
    }

    public function count(): int
    {
        return \count($this->elements);
    }
}
