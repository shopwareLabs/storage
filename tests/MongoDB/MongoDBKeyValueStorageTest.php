<?php

namespace Shopware\StorageTests\MongoDB;

use MongoDB\Client;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\MongoDB\MongoDBKeyStorage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @internal
 *
 * @covers \Shopware\Storage\MongoDB\MongoDBKeyStorage
 */
class MongoDBKeyValueStorageTest extends KeyValueStorageTestBase
{
    private ?Client $client = null;

    public function getStorage(): Storage
    {
        return new MongoDBKeyStorage(
            database: 'test',
            collection: TestSchema::getCollection(),
            hydrator: new Hydrator(),
            client: $this->getClient()
        );
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client('mongodb://localhost:27017');
        }

        return $this->client;
    }
}
