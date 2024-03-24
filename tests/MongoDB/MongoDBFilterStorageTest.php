<?php

namespace Shopware\StorageTests\MongoDB;

use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

/**
 * @covers \Shopware\Storage\MongoDB\MongoDBStorage
 */
class MongoDBFilterStorageTest extends FilterStorageTestBase
{
    use MongoDBTestTrait;

    public function getStorage(): FilterAware&Storage
    {
        return $this->createStorage();
    }
}
