<?php

namespace Shopware\Storage\Common\Aggregation\Type;

use Shopware\Storage\Common\Filter\Type\Filter;

abstract class Aggregation
{
    /**
     * @param array<Filter> $filters
     */
    public function __construct(
        public readonly string $name,
        public readonly string $field,
        public readonly array $filters = [],
    ) {}
}
