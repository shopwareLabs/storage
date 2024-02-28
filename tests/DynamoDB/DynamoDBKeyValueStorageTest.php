<?php

namespace Shopware\StorageTests\DynamoDB;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Exception\ResourceInUseException;
use AsyncAws\DynamoDb\Input\CreateTableInput;
use AsyncAws\DynamoDb\ValueObject\AttributeDefinition;
use AsyncAws\DynamoDb\ValueObject\KeySchemaElement;
use AsyncAws\DynamoDb\ValueObject\ProvisionedThroughput;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\KeyValue\KeyAware;
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

        $client = $this->getClient();

        $table = new CreateTableInput([
            'TableName' => TestSchema::getCollection()->name,
            'AttributeDefinitions' => [
                new AttributeDefinition(['AttributeName' => 'key', 'AttributeType' => 'S']),
            ],
            'KeySchema' => [
                new KeySchemaElement(['AttributeName' => 'key', 'KeyType' => 'HASH']),
            ],
            'ProvisionedThroughput' => new ProvisionedThroughput([
                'ReadCapacityUnits' => 5,
                'WriteCapacityUnits' => 5,
            ]),
        ]);

        try {
            $client->createTable($table);
        } catch (ResourceInUseException $e) {
            // table exists
        }
    }

    public function getStorage(): KeyAware&Storage
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
