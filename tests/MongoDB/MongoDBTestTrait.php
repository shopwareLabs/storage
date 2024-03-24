<?php

namespace Shopware\StorageTests\MongoDB;

use MongoDB\Client;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\MongoDB\MongoDBStorage;
use Shopware\StorageTests\Common\TestSchema;

trait MongoDBTestTrait
{
    private ?Client $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createStorage()->destroy();
        $this->createStorage()->setup();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->createStorage()->destroy();
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client('mongodb://localhost:27017');
        }

        return $this->client;
    }

    private function createStorage(): MongoDBStorage
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
