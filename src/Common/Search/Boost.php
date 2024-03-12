<?php

namespace Shopware\Storage\Common\Search;

use Shopware\Storage\Common\Filter\Operator\Operator;
use Shopware\Storage\Common\Filter\Type\Filter;

class Boost
{
    public function __construct(
        public Filter|Operator|Query $query,
        public float $boost = 1.0
    ) {}
}
