<?php

namespace Shopware\StorageTests\Meilisearch;

use Meilisearch\Client;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Meilisearch\MeilisearchStorage;
use Shopware\StorageTests\Common\TestSchema;

trait MeilisearchTestTrait
{
    private static ?Client $client = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createStorage()->setup();
    }

    public static function tearDownAfterClass(): void
    {
        self::createStorage()->destroy();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->getStorage()->clear();
    }

    private static function getClient(): Client
    {
        if (self::$client === null) {
            self::$client = new Client(
                url: 'http://localhost:7700',
                apiKey: 'UTbXxcv5T5Hq-nCYAjgPJ5lsBxf7PdhgiNexmoTByJk'
            );
        }

        return self::$client;
    }

    private static function createStorage(): MeilisearchLiveStorage
    {
        return new MeilisearchLiveStorage(
            storage: new MeilisearchStorage(
                caster: new AggregationCaster(),
                hydrator: new Hydrator(),
                client: self::getClient(),
                collection: TestSchema::getCollection()
            ),
            client: self::getClient(),
        );
    }
}
