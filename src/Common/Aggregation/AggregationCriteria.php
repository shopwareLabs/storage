<?php

namespace Shopware\Storage\Common\Aggregation;

use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Search\SearchTerm;

/**
 * @phpstan-import-type Filter from FilterCriteria
 */
class AggregationCriteria
{
    public function __construct(
        public ?SearchTerm $term = null,
        /**
         * @var Filter[]
         */
        public array $filters = [],
        /**
         * @var string[]
         */
        public array $keys = []
    ) {
    }
}
