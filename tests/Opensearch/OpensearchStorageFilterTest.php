<?php

namespace Shopware\StorageTests\Opensearch;

use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\FilterStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\Opensearch\OpensearchStorage
 */
class OpensearchStorageFilterTest extends FilterStorageTestBase
{
    use OpensearchTestTrait;

    public function getStorage(): FilterAware&Storage
    {
        return $this->createStorage(
            collection: TestSchema::getCollection(),
        );
    }
}
