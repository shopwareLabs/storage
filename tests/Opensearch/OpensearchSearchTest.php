<?php

namespace Opensearch;

use Shopware\Storage\Common\Search\SearchAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\SearchStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;
use Shopware\StorageTests\Opensearch\OpensearchTestTrait;

/**
 * @covers \Shopware\Storage\Opensearch\OpensearchStorage
 */
class OpensearchSearchTest extends SearchStorageTestBase
{
    use OpensearchTestTrait;

    /**
     * @return string[]|null
     */
    public function match(string $case): ?array
    {
        return match($case) {
            'number+ssd case' => ['ssd-164'],
            'clothes: full term provided' => ['blue-shirt', 'funny-shirt', 'red-shirt'],
            default => null
        };
    }

    public function getStorage(): SearchAware&Storage
    {
        return self::createStorage(
            collection: TestSchema::getCollection(),
        );
    }
}
