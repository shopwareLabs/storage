<?php

namespace Shopware\Storage\DynamoDB;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\BatchGetItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use AsyncAws\DynamoDb\ValueObject\KeysAndAttributes;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\KeyValue\KeyValueStorage;

class DynamoDBKeyValueStorage implements KeyValueStorage
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly string $source
    ) {
    }


    public function setup(): void
    {
        // todo@o.skroblin auto setup feature
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
                $this->source => $mapped
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
                        'value' => ['S' => json_encode($document->data, \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_IGNORE)],
                    ],
                ],
            ];
        }

        $this->client->batchWriteItem([
            'RequestItems' => [
                $this->source => $mapped
            ],
        ]);
    }

    public function mget(array $keys): Documents
    {
        $data = $this->client->batchGetItem(
            new BatchGetItemInput([
                'RequestItems' => [
                    $this->source => new KeysAndAttributes([
                        'Keys' => array_map(fn (string $key) => ['key' => new AttributeValue(['S' => $key])], $keys)
                    ]),
                ],
            ])
        );

        $documents = [];

        /** @var array{value: AttributeValue, key: AttributeValue} $row */
        foreach ($data->getResponses()[$this->source] as $row) {
            $documents[] = $this->hydrate($row);
        }

        return new Documents($documents);
    }

    public function get(string $key): ?Document
    {
        $data = $this->client->getItem([
            'TableName' => $this->source,
            'Key' => self::key($key),
        ]);

        if (empty($data->getItem())) {
            return null;
        }

        /** @var array{key: AttributeValue, value: AttributeValue} $item */
        $item = $data->getItem();

        return $this->hydrate($item);
    }

    /**
     * @param array{key: AttributeValue, value: AttributeValue} $item
     */
    private function hydrate(array $item): Document
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

        return new Document($item['key']->getS(), $data);
    }

    /**
     * @return array{"key": array{"S": string}}
     */
    private static function key(string $key): array
    {
        return ['key' => ['S' => $key]];
    }
}
