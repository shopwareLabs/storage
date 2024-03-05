<?php

namespace Shopware\Storage\DynamoDB;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Exception\ResourceInUseException;
use AsyncAws\DynamoDb\Input\BatchGetItemInput;
use AsyncAws\DynamoDb\Input\CreateTableInput;
use AsyncAws\DynamoDb\ValueObject\AttributeDefinition;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use AsyncAws\DynamoDb\ValueObject\KeysAndAttributes;
use AsyncAws\DynamoDb\ValueObject\KeySchemaElement;
use AsyncAws\DynamoDb\ValueObject\ProvisionedThroughput;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

class DynamoDBKeyStorage implements Storage
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly Hydrator $hydrator,
        private readonly Collection $collection
    ) {}

    public function destroy(): void
    {
        $this->client->deleteTable(['TableName' => $this->collection->name]);
    }

    public function setup(): void
    {
        $table = new CreateTableInput([
            'TableName' => $this->collection->name,
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
            $this->client->createTable($table);
        } catch (ResourceInUseException) {
            // table exists
        }
    }

    public function clear(): void
    {
        $this->destroy();
        $this->setup();
    }

    public function remove(array $keys): void
    {
        $mapped = [];

        foreach ($keys as $key) {
            $mapped[] = [
                'DeleteRequest' => [
                    'Key' => self::key($key),
                ],
            ];
        }

        $this->client->batchWriteItem([
            'RequestItems' => [
                $this->collection->name => $mapped,
            ],
        ]);
    }

    public function store(Documents $documents): void
    {
        $mapped = [];

        foreach ($documents as $document) {
            $mapped[] = [
                'PutRequest' => [
                    'Item' => [
                        'key' => ['S' => $document->key],
                        'value' => ['S' => json_encode($document->encode(), Document::JSON_OPTIONS)],
                    ],
                ],
            ];
        }

        $this->client->batchWriteItem([
            'RequestItems' => [
                $this->collection->name => $mapped,
            ],
        ]);
    }

    public function mget(array $keys, StorageContext $context): Documents
    {
        $data = $this->client->batchGetItem(
            new BatchGetItemInput([
                'RequestItems' => [
                    $this->collection->name => new KeysAndAttributes([
                        'Keys' => array_map(fn(string $key) => ['key' => new AttributeValue(['S' => $key])], $keys),
                    ]),
                ],
            ])
        );

        $documents = [];

        /** @var array{value: AttributeValue, key: AttributeValue} $row */
        foreach ($data->getResponses()[$this->collection->name] as $row) {
            $documents[] = $this->hydrate(item: $row, context: $context);
        }

        return new Documents($documents);
    }

    public function get(string $key, StorageContext $context): ?Document
    {
        $data = $this->client->getItem([
            'TableName' => $this->collection->name,
            'Key' => self::key($key),
        ]);

        if (empty($data->getItem())) {
            return null;
        }

        /** @var array{key: AttributeValue, value: AttributeValue} $item */
        $item = $data->getItem();

        return $this->hydrate($item, $context);
    }

    /**
     * @param array{key: AttributeValue, value: AttributeValue} $item
     */
    private function hydrate(array $item, StorageContext $context): Document
    {
        if ($item['value']->getS() === null) {
            throw new \LogicException('Value is null');
        }
        if ($item['key']->getS() === null) {
            throw new \LogicException('Key is null');
        }

        $data = json_decode($item['value']->getS(), true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid data type');
        }

        return $this->hydrator->hydrate(
            collection: $this->collection,
            data: $data,
            context: $context
        );
    }

    /**
     * @return array{"key": array{"S": string}}
     */
    private static function key(string $key): array
    {
        return ['key' => ['S' => $key]];
    }
}
