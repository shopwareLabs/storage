<?php

namespace Shopware\StorageTests\DynamoDB;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Exception\ResourceInUseException;
use AsyncAws\DynamoDb\Input\CreateTableInput;
use AsyncAws\DynamoDb\ValueObject\AttributeDefinition;
use AsyncAws\DynamoDb\ValueObject\KeySchemaElement;
use AsyncAws\DynamoDb\ValueObject\ProvisionedThroughput;
use Shopware\Storage\Common\KeyValue\KeyValueStorage;
use Shopware\Storage\DynamoDB\DynamoDBKeyValueStorage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;

/**
 * @covers \Shopware\Storage\DynamoDB\DynamoDBKeyValueStorage
 */
class DynamoDBKeyValueStorageTest extends KeyValueStorageTestBase
{
    protected function setUp(): void
    {
        parent::setUp();

        $client = $this->getClient();

        $table = new CreateTableInput([
            'TableName' => 'test',
            'AttributeDefinitions' => [
                new AttributeDefinition(['AttributeName' => 'key', 'AttributeType' => 'S']),
            ],
            'KeySchema' => [
                new KeySchemaElement(['AttributeName' => 'key', 'KeyType' => 'HASH']),
            ],
            'ProvisionedThroughput' => new ProvisionedThroughput([
                'ReadCapacityUnits' => 5,
                'WriteCapacityUnits' => 5
            ]),
        ]);

        try {
            $client->createTable($table);
        } catch (ResourceInUseException $e) {
            // table exists
        }
    }

    public function getStorage(): KeyValueStorage
    {
        return new DynamoDBKeyValueStorage(
            $this->getClient(),
            'test'
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
