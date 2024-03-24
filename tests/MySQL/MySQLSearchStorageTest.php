<?php

namespace Shopware\StorageTests\MySQL;

use Shopware\Storage\Common\Search\SearchAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\SearchStorageTestBase;

class MySQLSearchStorageTest extends SearchStorageTestBase
{
    use MySQLTestTrait;

    /**
     * @return string[]|null
     */
    public function match(string $case): ?array
    {
        return match ($case) {
            'single term, term phrase' => ['innovation-hub'],
            'multi term, match' => ['electro-pioneer','innovation-hub','mega-innovation'],
            'wheel, 9,5x19 5x120' => ['KV1DC9519255Z2-B1', 'KV1DC9519305A-NP'],
            'wheel, 9,5 KV1S' => ['KV1S8021355CZ-GO3', 'KV1S9521385CZA-B3', 'KV1SDC10521185RZ'],
            'hardware, ssd+number+gb case' => ['ssd-164', 'hdd-164'],
            'hardware, number+gb+ssd case' => ['ssd-164', 'hdd-164'],
            default => null,
        };
    }

    public function getStorage(): SearchAware&Storage
    {
        return $this->createStorage();
    }
}
