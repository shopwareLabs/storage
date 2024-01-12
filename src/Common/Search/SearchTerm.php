<?php

namespace Shopware\Storage\Common\Search;

class SearchTerm
{
    /**
     * @param InterpretedTerm[] $terms
     */
    public function __construct(public array $terms) {}
}
