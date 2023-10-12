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
     * @param array<TElement> $elements
     */
    public function __construct(iterable $elements = [])
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
     * @param string|int|null $key
     * @param TElement $element
     */
    public function set(string|int|null $key, $element): void
    {
        if ($key === null) {
            $this->elements[] = $element;
        } else {
            $this->elements[$key] = $element;
        }
    }

    /**
     * @param string|int $key
     *
     * @return TElement|null
     */
    public function get(string|int $key)
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    public function has(string|int $key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    public function clear(): void
    {
        $this->elements = [];
    }

    /**
     * @return string[]|int[]
     */
    public function keys(): array
    {
        return array_keys($this->elements);
    }

    /**
     * @return list<mixed>
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
     * @param string|int $key
     */
    public function remove(string|int $key): void
    {
        unset($this->elements[$key]);
    }

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