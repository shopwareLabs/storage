<?php

namespace Shopware\Storage\Common\Search;

class Query
{
    public function __construct(
        public string $field,
        public string $term,
        public float $boost = 1.0
    ) {}
}
