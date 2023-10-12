<?php

namespace Shopware\Storage\Common\Aggregation;

use Shopware\Storage\Common\Search\SearchTerm;

class AggregationCriteria
{
    public function __construct(
        public ?SearchTerm $term = null,
        /**
         * @var array<array{type: string, field: string, value: mixed}>
         */
        public array $filters = [],
        public array $keys = []
    ) {
    }
}
