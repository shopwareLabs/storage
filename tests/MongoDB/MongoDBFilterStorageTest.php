<?php

namespace Shopware\StorageTests\MongoDB;

use MongoDB\Client;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\MongoDB\MongoDBHydrator;
use Shopware\Storage\MongoDB\MongoDBStorage;
use Shopware\StorageTests\Common\FilterStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\MongoDB\MongoDBStorage
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
        $this->getClient()->dropDatabase(TestSchema::getCollection()->name);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->getClient()->dropDatabase(TestSchema::getCollection()->name);
    }

    public function getStorage(): FilterAware&Storage
    {
        return new MongoDBStorage(
            caster: new AggregationCaster(),
            database: 'test',
            hydrator: new Hydrator(),
            collection: TestSchema::getCollection(),
            client: $this->getClient(),
        );
    }
}
