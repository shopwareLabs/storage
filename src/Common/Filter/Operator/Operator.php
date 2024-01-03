<?php

namespace Shopware\Storage\Common\Filter\Operator;

use Shopware\Storage\Common\Filter\Type\Filter;

class Operator
{
    /**
     * @param array<Operator|Filter> $filters
     */
    public function __construct(public array $filters = [])
    {
    }
}
