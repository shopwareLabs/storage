<?php

namespace MongoDB;

use Shopware\Storage\Common\Search\SearchAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\SearchStorageTestBase;
use Shopware\StorageTests\MongoDB\MongoDBTestTrait;

/**
 * @covers \Shopware\Storage\MongoDB\MongoDBStorage
 */
class MongoDBSearchTest extends SearchStorageTestBase
{
    use MongoDBTestTrait;

    public function match(string $case): ?array
    {
        return match ($case) {
            'wheel, 9,5x19 5x120' => ['KV19020345C9-B2','KV1DC9519255Z2-B1','KV1DC9519305A-NP','KV1S9521385CZA-B3'],
            'wheel, 9,5x19"' => ['KV19020345C9-B2','KV1DC9519255Z2-B1','KV1DC9519305A-NP','KV1S9521385CZA-B3'],
            'wheel, KV1S' => ['KV1S8021355CZ-GO3','KV1S9521385CZA-B3','KV1SDC10521185RZ'],
            'wheel, 9,5 KV1S' => ['KV19020345C9-B2','KV1DC9519255Z2-B1','KV1DC9519305A-NP','KV1S8021355CZ-GO3','KV1S9521385CZA-B3','KV1SDC10521185RZ'],
            'hardware, number+ssd case' => ['hdd-164','ssd-164'],
            'hardware, ssd+number case' => ['hdd-164','ssd-164'],
            'hardware, ssd+number+gb case' => ['hdd-164','ssd-164'],
            'hardware, number+gb+ssd case' => ['hdd-164','ssd-164'],
            'clothes: red cotton shirt' => ['blue-shirt','dug-shirt','funny-shirt','green-shirt','jeans','red-shirt'],
            'single term, term phrase' => [],
            'multi term, match' => ['electro-pioneer','innovation-hub','mega-innovation'],
            'group-minimum-2' => ['electro-pioneer','innovation-hub','mega-innovation'],
            'nested-group' => ['electro-pioneer','innovation-hub','mega-innovation'],
            default => null,
        };
    }

    public function getStorage(): SearchAware&Storage
    {
        return $this->createStorage();
    }
}
