<?php

namespace Shopware\StorageTests\DynamoDB;

use AsyncAws\DynamoDb\DynamoDbClient;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\DynamoDB\DynamoDBKeyStorage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\DynamoDB\DynamoDBKeyStorage
 */
class DynamoDBKeyValueStorageTest extends KeyValueStorageTestBase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->getStorage()->setup();
    }

    public function getStorage(): Storage
    {
        return new DynamoDBKeyStorage(
            client: $this->getClient(),
            hydrator: new Hydrator(),
            collection: TestSchema::getCollection()
        );
    }

    private function getClient(): DynamoDbClient
    {
        return new DynamoDbClient([
            'endpoint' => 'http://localhost:6000',
            'accessKeyId' => 'DUMMYIDEXAMPLE',
            'accessKeySecret' => 'DUMMYEXAMPLEKEY',
            'region' => 'eu-central-1',
        ]);
    }
}
