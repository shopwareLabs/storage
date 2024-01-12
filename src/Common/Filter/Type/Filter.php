<?php

namespace Shopware\Storage\Common\Filter\Type;

abstract class Filter
{
    public function __construct(
        public readonly string $field,
        public readonly mixed $value
    ) {
    }
}
