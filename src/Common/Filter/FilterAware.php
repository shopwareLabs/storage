<?php

namespace Shopware\Storage\Common\Filter;

use Shopware\Storage\Common\StorageContext;

interface FilterAware
{
    public function filter(Criteria $criteria, StorageContext $context): Result;
}
