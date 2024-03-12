<?php

namespace Shopware\StorageTests\Opensearch;

use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Opensearch\OpensearchStorage;
use Shopware\StorageTests\Common\TestSchema;

trait OpensearchTestTrait
{
    private static ?Client $client = null;

    private static function getClient(): Client
    {
        if (self::$client === null) {
            $builder = ClientBuilder::create();
            $builder->setHosts(['http://localhost:9200']);

            self::$client = $builder->build();
        }

        return self::$client;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->getStorage()->clear();

        $this->getStorage()->setup();
    }

    private static function createStorage(Collection $collection): OpensearchLiveStorage
    {
        return new OpensearchLiveStorage(
            client: self::getClient(),
            decorated: new OpensearchStorage(
                caster: new AggregationCaster(),
                client: self::getClient(),
                hydrator: new Hydrator(),
                collection: $collection
            ),
            collection: $collection
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::createStorage(TestSchema::getCollection())
            ->destroy();
    }
}
