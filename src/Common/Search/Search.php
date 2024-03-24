<?php

namespace Shopware\Storage\Common\Search;

class Search
{
    /**
     * @param array<Boost> $boosts
     */
    public function __construct(
        public Group $group,
        public array $boosts = [],
    ) {}
}
