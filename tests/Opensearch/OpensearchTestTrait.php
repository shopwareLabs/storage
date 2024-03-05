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

    private function getClient(): Client
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

        $exists = $this->getClient()
            ->indices()
            ->exists(['index' => TestSchema::getCollection()->name]);

        if ($exists) {
            //$this->getClient()->indices()->delete(['index' => TestSchema::getCollection()->name]);
            // delete all documents from index
            $this->getClient()->deleteByQuery([
                'index' => TestSchema::getCollection()->name,
                'body' => [
                    'query' => ['match_all' => new \stdClass()],
                ],
            ]);
        }

        $this->getStorage()->setup();
    }

    public function createStorage(Collection $collection): OpensearchLiveStorage
    {
        return new OpensearchLiveStorage(
            client: $this->getClient(),
            decorated: new OpensearchStorage(
                caster: new AggregationCaster(),
                client: $this->getClient(),
                hydrator: new Hydrator(),
                collection: $collection
            ),
            collection: $collection
        );
    }

    public static function tearDownAfterClass(): void
    {
        $builder = ClientBuilder::create();
        $builder->setHosts(['http://localhost:9200']);
        $client = $builder->build();

        $exists = $client->indices()
            ->exists(['index' => TestSchema::getCollection()->name]);

        if ($exists) {
            $client->indices()->delete(['index' => TestSchema::getCollection()->name]);
        }
    }
}
