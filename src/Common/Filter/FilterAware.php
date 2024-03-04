<?php

namespace Shopware\Storage\Common\Filter;

use Shopware\Storage\Common\Exception\NotSupportedByEngine;
use Shopware\Storage\Common\StorageContext;

interface FilterAware
{
    /**
     * @throws NotSupportedByEngine
     */
    public function filter(Criteria $criteria, StorageContext $context): Result;
}
