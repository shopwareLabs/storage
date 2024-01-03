<?php

namespace Shopware\Storage\Common\Filter;

use Shopware\Storage\Common\Filter\Operator\Operator;
use Shopware\Storage\Common\Filter\Type\Filter;

/**
 * @phpstan-type Sorting=array{"field": string, "direction": string}
 */
class FilterCriteria
{
    public function __construct(
        public ?int $page = null,
        public ?int $limit = null,
        public ?array $keys = null,
        public bool $total = false,
        /**
         * @var Sorting[]
         */
        public array $sorting = [],
        /**
         * @var Operator|Filter[]
         */
        public array $filters = []
    ) {
    }
}
