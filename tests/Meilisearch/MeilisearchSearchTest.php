<?php

namespace Meilisearch;

use Shopware\Storage\Common\Search\SearchAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\SearchStorageTestBase;
use Shopware\StorageTests\Meilisearch\MeilisearchTestTrait;

class MeilisearchSearchTest extends SearchStorageTestBase
{
    use MeilisearchTestTrait;

    /**
     * @return string[]|null
     */
    public function match(string $case): ?array
    {
        return match($case) {
            'wheel, 9,5x19 5x120' => ['KV19020345C9-B2', 'KV1DC9519255Z2-B1', 'KV1DC9519305A-NP', 'KV1S9521385CZA-B3'],
            'wheel, 9,5x19"' => ['KV19020345C9-B2', 'KV1DC9519255Z2-B1', 'KV1DC9519305A-NP', 'KV1S9521385CZA-B3'],
            'wheel, 9,5 KV1S' => ['KV19020345C9-B2', 'KV1DC9519255Z2-B1', 'KV1DC9519305A-NP', 'KV1S9521385CZA-B3'],
            'hardware, number+ssd case' => ['hdd-164', 'ssd-164'],
            'hardware, number+gb+ssd case' => ['hdd-164', 'ssd-164'],
            'clothes: red cotton shirt' => ['dug-shirt', 'funny-shirt', 'red-shirt'],
            'single term, special characters' => ['electro-pioneer'],
            'multi term, match' => ['electro-pioneer', 'innovation-hub', 'mega-innovation'],
            'group-minimum-1' => ['electro-pioneer', 'innovation-hub', 'mega-innovation'],
            'group-minimum-2' => ['electro-pioneer', 'innovation-hub', 'mega-innovation'],
            'nested-group' => ['electro-pioneer', 'innovation-hub', 'mega-innovation'],
            //todo support umlauts
            'Umlauts' => [],
            default => null,
        };
    }

    public function getStorage(): SearchAware&Storage
    {
        return $this->createStorage();
    }
}
