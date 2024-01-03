<?php

namespace Shopware\Storage\Common\Filter;

use Shopware\Storage\Common\Filter\Operator\Operator;
use Shopware\Storage\Common\Filter\Paging\Paging;
use Shopware\Storage\Common\Filter\Type\Filter;

class FilterCriteria
{
    /**
     * @param array<string> $keys
     * @param array<Sorting> $sorting
     * @param array<Operator|Filter> $filters
     */
    public function __construct(
        public ?Paging $paging = null,
        public ?int $limit = null,
        public ?array $keys = null,
        public bool $total = false,
        public array $sorting = [],
        public array $filters = []
    ) {
    }
}
