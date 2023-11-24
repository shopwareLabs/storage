<?php

namespace Shopware\Storage\Common\Filter;

/**
 * @phpstan-type Sorting=array{"field": string, "direction": string}
 * @phpstan-type Filter=array{"type": string, "field": string, "value": mixed, "queries"?: array<mixed>}
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
         * @var Filter[]
         */
        public array $filters = []
    ) {
    }
}
