<?php

namespace Shopware\StorageTests\MongoDB;

use MongoDB\Client;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\MongoDB\MongoDBStorage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\MongoDB\MongoDBStorage
 */
class MongoDBAggregateStorageTest extends AggregationStorageTestBase
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
        $this->getStorage()->destroy();
        $this->getStorage()->setup();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->getStorage()->destroy();
    }

    public function getStorage(): AggregationAware&Storage
    {
        return new MongoDBStorage(
            caster: new AggregationCaster(),
            hydrator: new Hydrator(),
            database: 'test',
            collection: TestSchema::getCollection(),
            client: $this->getClient(),
        );
    }
}
