<?php

namespace Shopware\StorageTests\Array;

use Shopware\Storage\Array\ArrayKeyValueStorage;
use Shopware\Storage\Common\KeyValue\KeyValueStorage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;

/**
 * @covers \Shopware\Storage\Array\ArrayKeyValueStorage
 */
class ArrayKeyValueStorageTest extends KeyValueStorageTestBase
{
    public function getStorage(): KeyValueStorage
    {
        return new ArrayKeyValueStorage();
    }
}
