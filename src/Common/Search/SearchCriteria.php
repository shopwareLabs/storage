<?php

namespace Shopware\Storage\Common\Search;

use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\Operator\Operator;
use Shopware\Storage\Common\Filter\Sorting;
use Shopware\Storage\Common\Filter\Type\Filter;

/**
 */
class SearchCriteria
{
    public function __construct(
        public SearchTerm $term,
        public ?int $page = null,
        public ?int $limit = null,
        /**
         * @var array<string>|null
         */
        public ?array $keys = null,
        public bool $total = false,
        /**
         * @var Sorting[]
         */
        public array $sorting = [],
        /**
         * @var array<Filter|Operator>
         */
        public array $filters = []
    ) {
    }
}
