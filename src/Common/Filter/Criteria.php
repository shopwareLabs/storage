<?php

namespace Shopware\Storage\Common\Filter;

use Shopware\Storage\Common\Filter\Operator\Operator;
use Shopware\Storage\Common\Filter\Paging\Paging;
use Shopware\Storage\Common\Filter\Type\Filter;
use Shopware\Storage\Common\Total;

class Criteria
{
    /**
     * @param array<string> $primaries
     * @param array<Sorting> $sorting
     * @param array<Operator|Filter> $filters
     */
    public function __construct(
        public ?Paging $paging = null,
        public ?array $primaries = null,
        public Total $total = Total::NONE,
        public array $sorting = [],
        public array $filters = []
    ) {}
}
