<?php

namespace Shopware\StorageTests\Array;

use Shopware\Storage\Array\ArrayKeyStorage;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;

/**
 * @covers \Shopware\Storage\Array\ArrayKeyStorage
 */
class ArrayKeyValueStorageTest extends KeyValueStorageTestBase
{
    public function getStorage(): Storage
    {
        return new ArrayKeyStorage();
    }
}
