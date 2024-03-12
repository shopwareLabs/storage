<?php

namespace MongoDB;

use Shopware\Storage\Common\Search\SearchAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\SearchStorageTestBase;
use Shopware\StorageTests\MongoDB\MongoDBTestTrait;

/**
 * @covers \Shopware\Storage\MongoDB\MongoDBStorage
 */
class MongoDBSearchTest extends SearchStorageTestBase
{
    use MongoDBTestTrait;

    public function getStorage(): SearchAware&Storage
    {
        return $this->createStorage();
    }
}
