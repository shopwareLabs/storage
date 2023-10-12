<?php

namespace Shopware\StorageTests\MongoDB;

use MongoDB\Client;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\MongoDB\MongoDBFilterStorage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

/**
 * @covers \Shopware\Storage\MongoDB\MongoDBFilterStorage
 */
class MongoDBFilterStorageTest extends FilterStorageTestBase
{
    private ?Client $client = null;

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client('mongodb://localhost:27017');
        }

        return $this->client;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->getClient()->dropDatabase('test');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->getClient()->dropDatabase('test');
    }

    public function getStorage(): FilterStorage
    {
        return new MongoDBFilterStorage(
            database: 'test',
            schema: $this->getSchema(),
            client: $this->getClient(),
        );
    }
}
