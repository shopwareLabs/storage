<?php

namespace Shopware\Storage\Common\Search;

use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Filter\Result;
use Shopware\Storage\Common\StorageContext;

interface SearchAware
{
    public function search(Criteria $criteria, StorageContext $context): Result;
}
