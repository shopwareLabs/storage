<?php

namespace Shopware\StorageTests\Opensearch;

use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\Opensearch\OpenSearchFilterStorage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

/**
 * @covers \Shopware\Storage\Opensearch\OpenSearchFilterStorage
 */
class OpenSearchStorageTest extends FilterStorageTestBase
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
            return;
        }

        $this->getClient()->indices()->create([
            'index' => $this->getSchema()->source,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'key' => ['type' => 'keyword'],
                        'stringField' => ['type' => 'keyword'],
                        'translatedString' => [
                            'type' => 'nested',
                            'properties' => [
                                'de' => ['type' => 'keyword'],
                                'en' => ['type' => 'keyword'],
                            ]
                        ],
                        'translatedInt' => [
                            'type' => 'nested',
                            'properties' => [
                                'de' => ['type' => 'integer'],
                                'en' => ['type' => 'integer'],
                            ]
                        ],
                        'translatedFloat' => [
                            'type' => 'nested',
                            'properties' => [
                                'de' => ['type' => 'float'],
                                'en' => ['type' => 'float'],
                            ]
                        ],
                        'translatedBool' => [
                            'type' => 'nested',
                            'properties' => [
                                'de' => ['type' => 'boolean'],
                                'en' => ['type' => 'boolean'],
                            ]
                        ],
                        'translatedDate' => [
                            'type' => 'nested',
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
                        'intField' => ['type' => 'integer'],
                        'floatField' => ['type' => 'float'],
                        'boolField' => ['type' => 'boolean'],
                        'dateField' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                            'ignore_malformed' => true,
                        ],
                        'listField' => ['type' => 'keyword'],
                        'objectField' => [
                            'type' => 'nested',
                            'properties' => [
                                'foo' => ['type' => 'keyword'],
                                'fooInt' => ['type' => 'integer'],
                                'fooFloat' => ['type' => 'float'],
                                'fooBool' => ['type' => 'boolean'],
                                'fooDate' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                                    'ignore_malformed' => true,
                                ]
                            ]
                        ],
                        'objectListField' => [
                            'type' => 'nested',
                            'properties' => [
                                'foo' => ['type' => 'keyword'],
                                'fooInt' => ['type' => 'integer'],
                                'fooFloat' => ['type' => 'float'],
                                'fooBool' => ['type' => 'boolean'],
                                'fooDate' => [
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

    protected function tearDown(): void
    {
        parent::tearDown();

        $exists = $this->getClient()
            ->indices()
            ->exists(['index' => $this->getSchema()->source]);

        if ($exists) {
            $this->getClient()
                ->indices()
                ->delete(['index' => $this->getSchema()->source]);
        }
    }

    public function getStorage(): FilterStorage
    {
        return new OpensearchLiveFilterStorage(
            $this->getClient(),
            new OpenSearchFilterStorage(
                $this->getClient(),
                $this->getSchema()
            ),
            $this->getSchema()
        );
    }
}
