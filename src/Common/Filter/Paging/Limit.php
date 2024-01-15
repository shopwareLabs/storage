<?php

namespace Shopware\Storage\Common\Filter\Paging;

class Limit implements Paging
{
    public function __construct(
        public int $limit
    ) {}
}
