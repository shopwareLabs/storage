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

        $this->createIndex();

        $this->createScripts();
    }

    private function createIndex(): void
    {
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

            return;
        }

        $this->getClient()->indices()->create([
            'index' => TestSchema::getCollection()->name,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'key' => ['type' => 'keyword'],
                        'ean' => ['type' => 'keyword'],
                        'stock' => ['type' => 'integer'],
                        'price' => ['type' => 'double'],
                        'active' => ['type' => 'boolean'],
                        'changed' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                            'ignore_malformed' => true,
                        ],
                        'keywords' => ['type' => 'keyword'],
                        'name' => [
                            'type' => 'object',
                            'properties' => [
                                'de' => ['type' => 'keyword'],
                                'en' => ['type' => 'keyword'],
                            ],
                        ],
                        'position' => [
                            'type' => 'object',
                            'properties' => [
                                'de' => ['type' => 'integer'],
                                'en' => ['type' => 'integer'],
                            ],
                        ],
                        'weight' => [
                            'type' => 'object',
                            'properties' => [
                                'de' => ['type' => 'double'],
                                'en' => ['type' => 'double'],
                            ],
                        ],
                        'highlight' => [
                            'type' => 'object',
                            'properties' => [
                                'de' => ['type' => 'boolean'],
                                'en' => ['type' => 'boolean'],
                            ],
                        ],
                        'release' => [
                            'type' => 'object',
                            'properties' => [
                                'de' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                    'ignore_malformed' => true,
                                ],
                                'en' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                    'ignore_malformed' => true,
                                ],
                            ],
                        ],
                        'mainCategory' => [
                            'type' => 'object',
                            'properties' => [
                                'ean' => ['type' => 'keyword'],
                                'stock' => ['type' => 'integer'],
                                'price' => ['type' => 'double'],
                                'active' => ['type' => 'boolean'],
                                'changed' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                    'ignore_malformed' => true,
                                ],
                                'keywords' => ['type' => 'keyword'],
                                'name' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'de' => ['type' => 'keyword'],
                                        'en' => ['type' => 'keyword'],
                                    ],
                                ],
                                'position' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'de' => ['type' => 'integer'],
                                        'en' => ['type' => 'integer'],
                                    ],
                                ],
                                'weight' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'de' => ['type' => 'double'],
                                        'en' => ['type' => 'double'],
                                    ],
                                ],
                                'highlight' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'de' => ['type' => 'boolean'],
                                        'en' => ['type' => 'boolean'],
                                    ],
                                ],
                                'release' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'de' => [
                                            'type' => 'date',
                                            'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                            'ignore_malformed' => true,
                                        ],
                                        'en' => [
                                            'type' => 'date',
                                            'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                            'ignore_malformed' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'categories' => [
                            'type' => 'nested',
                            'properties' => [
                                'ean' => ['type' => 'keyword'],
                                'stock' => ['type' => 'integer'],
                                'price' => ['type' => 'double'],
                                'active' => ['type' => 'boolean'],
                                'changed' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                    'ignore_malformed' => true,
                                ],
                                'keywords' => ['type' => 'keyword'],
                                'name' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'de' => ['type' => 'keyword'],
                                        'en' => ['type' => 'keyword'],
                                    ],
                                ],
                                'position' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'de' => ['type' => 'integer'],
                                        'en' => ['type' => 'integer'],
                                    ],
                                ],
                                'weight' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'de' => ['type' => 'double'],
                                        'en' => ['type' => 'double'],
                                    ],
                                ],
                                'highlight' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'de' => ['type' => 'boolean'],
                                        'en' => ['type' => 'boolean'],
                                    ],
                                ],
                                'release' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'de' => [
                                            'type' => 'date',
                                            'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                            'ignore_malformed' => true,
                                        ],
                                        'en' => [
                                            'type' => 'date',
                                            'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                            'ignore_malformed' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function createScripts(): void
    {
        $this->getClient()->putScript([
            'id' => 'translated',
            'body' => [
                'script' => [
                    'lang' => 'painless',
                    'source' => file_get_contents(__DIR__ . '/../../src/Opensearch/scripts/translated.groovy'),
                ],
            ],
        ]);
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
