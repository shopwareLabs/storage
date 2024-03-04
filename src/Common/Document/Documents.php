<?php

namespace Shopware\Storage\Common\Document;

/**
 * @extends Collection<Document>
 */
class Documents extends Collection
{
    public function __construct(iterable $elements = [])
    {
        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    public function add($element): void
    {
        $this->elements[$element->key] = $element;
    }

    /**
     * @param array<string> $expected
     * @return array<array-key, Document>
     */
    public function list(array $expected): array
    {
        $result = [];
        foreach ($expected as $key) {
            $result[$key] = $this->elements[$key];
        }
        return $result;
    }
}
