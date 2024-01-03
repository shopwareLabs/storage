<?php

namespace Shopware\Storage\Common\Filter\Type;

class Filter
{
    public function __construct(
        public readonly string $field,
        public readonly mixed $value
    ) {
    }
}
