<?php

namespace Shopware\StorageTests\MongoDB;

use MongoDB\Client;
use Shopware\Storage\Common\KeyValue\KeyAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\MongoDB\MongoDBKeyStorage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;

/**
 * @internal
 *
 * @covers \Shopware\Storage\MongoDB\MongoDBKeyStorage
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

    public function getStorage(): KeyAware&Storage
    {
        return new MongoDBKeyStorage(
            database: 'test',
            collection: 'test_document',
            client: $this->getClient()
        );
    }
}
