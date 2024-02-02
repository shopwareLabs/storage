<?php

namespace Shopware\StorageTests\Opensearch;

use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Opensearch\OpensearchStorage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

/**
 * @covers \Shopware\Storage\Opensearch\OpensearchStorage
 */
class OpensearchStorageFilterTest extends FilterStorageTestBase
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

        $exists = $this->getClient()
            ->indices()
            ->exists(['index' => $this->getSchema()->source]);

        if ($exists) {
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

    public function getStorage(): FilterAware&Storage
    {
        return new OpensearchLiveStorage(
            $this->getClient(),
            new OpensearchStorage(
                new AggregationCaster(),
                $this->getClient(),
                $this->getSchema()
            ),
            $this->getSchema()
        );
    }
}