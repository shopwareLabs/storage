<?php

namespace Shopware\Storage\Common\Search;

use Shopware\Storage\Common\Filter\FilterCriteria;

/**
 * @phpstan-import-type Sorting from FilterCriteria
 * @phpstan-import-type Filter from FilterCriteria
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
         * @var Filter[]
         */
        public array $filters = []
    ) {
    }
}
