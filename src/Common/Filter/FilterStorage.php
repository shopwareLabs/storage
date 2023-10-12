<?php

namespace Shopware\Storage\Common\Filter;

use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

interface FilterStorage extends Storage
{
    /**
     * @param FilterCriteria $criteria
     * @param StorageContext $context
     * @return FilterResult
     */
    public function read(FilterCriteria $criteria, StorageContext $context): FilterResult;
}
