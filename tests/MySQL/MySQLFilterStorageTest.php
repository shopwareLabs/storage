<?php

namespace Shopware\StorageTests\MySQL;

use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\FilterStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\MySQL\MySQLStorage
 */
class MySQLFilterStorageTest extends FilterStorageTestBase
{
    use MySQLTestTrait;

    public function getStorage(): FilterAware&Storage
    {
        return $this->createStorage(
            collection: TestSchema::getCollection(),
        );
    }
}
