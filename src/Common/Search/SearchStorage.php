<?php

namespace Shopware\Storage\Common\Search;

use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Storage;

interface SearchStorage extends Storage
{
    public function search(SearchCriteria $criteria): FilterResult;
}
