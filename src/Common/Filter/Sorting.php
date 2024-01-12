<?php

namespace Shopware\Storage\Common\Filter;

class Sorting
{
    public function __construct(
        public readonly string $field,
        public readonly string $order
    ) {}
}
