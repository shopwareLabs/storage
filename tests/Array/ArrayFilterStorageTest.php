<?php

namespace Shopware\StorageTests\Array;

use Shopware\Storage\Array\ArrayFilterStorage;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

/**
 * @covers \Shopware\Storage\Array\ArrayFilterStorage
 */
class ArrayFilterStorageTest extends FilterStorageTestBase
{
    public function getStorage(): FilterStorage
    {
        return new ArrayFilterStorage(schema: $this->getSchema());
    }
}
