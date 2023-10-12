<?php

namespace Shopware\StorageTests\Redis;

use Shopware\Storage\Common\KeyValue\KeyValueStorage;
use Shopware\Storage\Redis\RedisKeyValueStorage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;

/**
 * @covers \Shopware\Storage\Redis\RedisKeyValueStorage
 */
class RedisKeyValueStorageTest extends KeyValueStorageTestBase
{
    private function getClient(): \Redis
    {
        $client = new \Redis();
        $client->connect('localhost');

        return $client;
    }

    public function getStorage(): KeyValueStorage
    {
        return new RedisKeyValueStorage(
            client: $this->getClient()
        );
    }
}
