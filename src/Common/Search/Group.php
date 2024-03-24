<?php

namespace Shopware\Storage\Common\Search;

class Group
{
    /**
     * @param array<Group|Query> $queries
     */
    public function __construct(
        public array $queries,
        public int $hits = 1
    ) {}
}
