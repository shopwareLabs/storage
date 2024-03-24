<?php

namespace Shopware\StorageTests\Opensearch;

use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\FilterStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\Opensearch\OpensearchStorage
 */
class OpensearchFilterTest extends FilterStorageTestBase
{
    use OpensearchTestTrait;

    public function getStorage(): FilterAware&Storage
    {
        return self::createStorage(
            collection: TestSchema::getCollection(),
        );
    }
}
