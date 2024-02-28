<?php

namespace Shopware\StorageTests\Meilisearch;

use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

class MeilisearchStorageTest extends FilterStorageTestBase
{
    use MeilisearchTestTrait;

    public function getStorage(): FilterAware&Storage
    {
        return $this->createStorage();
    }
}
