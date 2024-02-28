<?php

namespace Shopware\StorageTests\MySQL;

use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;

class MySQLAggregationStorageTest extends AggregationStorageTestBase
{
    use MySQLTestTrait;

    public function getStorage(): AggregationAware&Storage
    {
        return $this->createStorage();
    }
}
