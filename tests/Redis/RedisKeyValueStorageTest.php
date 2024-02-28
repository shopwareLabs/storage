<?php

namespace Shopware\StorageTests\Redis;

use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\KeyValue\KeyAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Redis\RedisKeyStorage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\Redis\RedisKeyStorage
 */
class RedisKeyValueStorageTest extends KeyValueStorageTestBase
{
    private function getClient(): \Redis
    {
        $client = new \Redis();
        $client->connect('localhost');

        return $client;
    }

    public function getStorage(): KeyAware&Storage
    {
        return new RedisKeyStorage(
            collection: TestSchema::getCollection(),
            hydrator: new Hydrator(),
            client: $this->getClient()
        );
    }
}
