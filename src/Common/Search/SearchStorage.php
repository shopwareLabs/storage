<?php

namespace Shopware\Storage\Common\Search;

use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

interface SearchStorage extends Storage
{
    public function search(SearchCriteria $criteria, StorageContext $context): FilterResult;
}
