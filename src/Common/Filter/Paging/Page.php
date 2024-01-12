<?php

namespace Shopware\Storage\Common\Filter\Paging;

class Page implements Paging
{
    public function __construct(public int $page)
    {
    }
}
