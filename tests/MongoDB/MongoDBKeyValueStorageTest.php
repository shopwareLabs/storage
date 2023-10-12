<?php

namespace Shopware\StorageTests\MongoDB;

use MongoDB\Client;
use Shopware\Storage\Common\KeyValue\KeyValueStorage;
use Shopware\Storage\MongoDB\MongoDBKeyValueStorage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;

/**
 * @internal
 *
 * @covers \Shopware\Storage\MongoDB\MongoDBKeyValueStorage
 */
class MongoDBKeyValueStorageTest extends KeyValueStorageTestBase
{
    private ?Client $client = null;

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client('mongodb://localhost:27017');
        }

        return $this->client;
    }

    public function getStorage(): KeyValueStorage
    {
        return new MongoDBKeyValueStorage(
            database: 'test',
            collection: 'test_document',
            client: $this->getClient()
        );
    }
}
