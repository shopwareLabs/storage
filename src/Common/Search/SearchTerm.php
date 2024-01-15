<?php

namespace Shopware\Storage\Common\Search;

class SearchTerm
{
    /**
     * @param string[] $terms
     */
    public function __construct(public array $terms) {}
}
