<?php

namespace Shopware\Storage\Common\Filter;

class FilterCriteria
{
    public function __construct(
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
