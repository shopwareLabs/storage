<?php

namespace Shopware\Storage\Common\Filter\Paging;

class Page extends Paging
{
    public function __construct(public int $page)
    {
    }
}
