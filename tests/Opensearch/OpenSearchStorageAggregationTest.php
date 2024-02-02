<?php

namespace Shopware\StorageTests\Opensearch;

use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Opensearch\OpenSearchStorage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;
use Shopware\StorageTests\Common\FilterStorageTestBase;

/**
 * @covers \Shopware\Storage\Opensearch\OpenSearchStorage
 */
class OpenSearchStorageAggregationTest extends AggregationStorageTestBase
{
    private ?Client $client = null;

    private function getClient(): Client
    {
        if ($this->client === null) {
            $builder = ClientBuilder::create();
            $builder->setHosts(['http://localhost:9200']);

            $this->client = $builder->build();
        }

        return $this->client;
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
            ->exists(['index' => $this->getSchema()->source]);

        if ($exists) {
            //            $this->getClient()->indices()->delete(['index' => $this->getSchema()->source]);
            // delete all documents from index
            $this->getClient()->deleteByQuery([
                'index' => $this->getSchema()->source,
                'body' => [
                    'query' => ['match_all' => new \stdClass()]
                ]
            ]);
            return;
        }

        $this->getClient()->indices()->create([
            'index' => $this->getSchema()->source,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'key' => ['type' => 'keyword'],
                        'stringField' => ['type' => 'keyword'],
                        'intField' => ['type' => 'integer'],
                        'floatField' => ['type' => 'double'],
                        'boolField' => ['type' => 'boolean'],
                        'dateField' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                            'ignore_malformed' => true,
                        ],
                        'listField' => ['type' => 'keyword'],
                        'translatedString' => [
                            'type' => 'object',
                            'properties' => [
                                'de' => ['type' => 'keyword'],
                                'en' => ['type' => 'keyword'],
                            ]
                        ],
                        'translatedInt' => [
                            'type' => 'object',
                            'properties' => [
                                'de' => ['type' => 'integer'],
                                'en' => ['type' => 'integer'],
                            ]
                        ],
                        'translatedFloat' => [
                            'type' => 'object',
                            'properties' => [
                                'de' => ['type' => 'double'],
                                'en' => ['type' => 'double'],
                            ]
                        ],
                        'translatedBool' => [
                            'type' => 'object',
                            'properties' => [
                                'de' => ['type' => 'boolean'],
                                'en' => ['type' => 'boolean'],
                            ]
                        ],
                        'translatedDate' => [
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
                            ]
                        ],
                        'objectField' => [
                            'type' => 'nested',
                            'properties' => [
                                'stringField' => ['type' => 'keyword'],
                                'intField' => ['type' => 'integer'],
                                'floatField' => ['type' => 'double'],
                                'boolField' => ['type' => 'boolean'],
                                'dateField' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                    'ignore_malformed' => true,
                                ]
                            ]
                        ],
                        'objectListField' => [
                            'type' => 'nested',
                            'properties' => [
                                'stringField' => ['type' => 'keyword'],
                                'intField' => ['type' => 'integer'],
                                'floatField' => ['type' => 'double'],
                                'boolField' => ['type' => 'boolean'],
                                'dateField' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                    'ignore_malformed' => true,
                                ]
                            ]
                        ],
                    ]
                ]
            ]
        ]);
    }

    private function createScripts(): void
    {
        $this->getClient()->putScript([
            'id' => 'translated',
            'body' => [
                'script' => [
                    'lang' => 'painless',
                    'source' => file_get_contents(__DIR__ . '/../../src/Opensearch/scripts/translated.groovy')
                ]
            ]
        ]);

    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        $builder = ClientBuilder::create();
        $builder->setHosts(['http://localhost:9200']);
        $client = $builder->build();

        $exists = $client->indices()
            ->exists(['index' => 'test_storage']);

        if ($exists) {
            $client->indices()
                ->delete(['index' => 'test_storage']);
        }
    }

    public function getStorage(): AggregationAware&Storage
    {
        return new OpensearchLiveStorage(
            $this->getClient(),
            new OpenSearchStorage(
                new AggregationCaster(),
                $this->getClient(),
                $this->getSchema()
            ),
            $this->getSchema()
        );
    }
}
