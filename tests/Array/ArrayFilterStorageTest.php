<?php

namespace Shopware\StorageTests\Array;

use Shopware\Storage\Array\ArrayStorage;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

/**
 * @covers \Shopware\Storage\Array\ArrayStorage
 */
class ArrayFilterStorageTest extends FilterStorageTestBase
{
    public function getStorage(): FilterAware&Storage
    {
        return new ArrayStorage(schema: $this->getSchema());
    }
}
