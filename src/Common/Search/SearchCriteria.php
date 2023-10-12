<?php

namespace Shopware\Storage\Common\Search;

class SearchCriteria
{
    public function __construct(
        public SearchTerm $term,
        public ?int $page = null,
        public ?int $limit = null,
        public ?array $keys = null,
        public bool $total = false,
        /**
         * @var array<array{field: string, direction: string}>
         */
        public array $sorting = [],
        /**
         * @var array<array{type: string, field: string, value: mixed}>
         */
        public array $filters = []
    ) {
    }
}
