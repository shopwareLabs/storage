<?php

namespace Shopware\Storage\Common\Aggregation;

use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\Operator\Operator;
use Shopware\Storage\Common\Filter\Type\Filter;
use Shopware\Storage\Common\Search\SearchTerm;

class AggregationCriteria
{
    /**
     * @param array<string> $keys
     * @param array<Operator|Filter> $filters
     */
    public function __construct(
        public ?SearchTerm $term = null,
        public array $filters = [],
        public array $keys = []
    ) {}
}
