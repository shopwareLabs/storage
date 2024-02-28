<?php

namespace Shopware\StorageTests\Meilisearch;

use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;

class MeilisearchStorageAggregationTest extends AggregationStorageTestBase
{
    use MeilisearchTestTrait;

    public function getStorage(): AggregationAware&Storage
    {
        return $this->createStorage();
    }
}
