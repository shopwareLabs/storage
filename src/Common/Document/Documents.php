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
}
