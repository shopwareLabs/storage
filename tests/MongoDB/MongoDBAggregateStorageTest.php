<?php

namespace Shopware\StorageTests\MongoDB;

use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;

/**
 * @covers \Shopware\Storage\MongoDB\MongoDBStorage
 */
class MongoDBAggregateStorageTest extends AggregationStorageTestBase
{
    use MongoDBTestTrait;

    public function getStorage(): AggregationAware&Storage
    {
        return $this->createStorage();
    }
}
