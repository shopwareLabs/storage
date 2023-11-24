<?php

namespace Shopware\Storage\MongoDB;

use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Collection;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\KeyValue\KeyValueStorage;

class MongoDBKeyValueStorage implements KeyValueStorage
{
    public function __construct(
        private readonly string $database,
        private readonly string $collection,
        private readonly Client $client
    ) {
    }

    public function mget(array $keys): Documents
    {
        $query['_key'] = ['$in' => $keys];

        $cursor = $this->collection()->find($query);

        $cursor->setTypeMap([
            'root' => 'array',
            'document' => 'array',
            'array' => 'array',
        ]);

        $result = [];
        foreach ($cursor as $item) {
            $data = $item;

            if (!is_array($data)) {
                throw new \RuntimeException('Mongodb returned invalid data type');
            }

            if (!isset($data['_key'])) {
                throw new \RuntimeException('Missing _key property in mongodb result');
            }

            $key = $data['_key'];
            unset($data['_key'], $data['_id']);

            $result[] = new Document(
                key: $key,
                data: $data
            );
        }

        return new Documents($result);
    }

    public function get(string $key): ?Document
    {
        $options = [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ]
        ];

        $cursor = $this->collection()->findOne(['_key' => $key], $options);

        if ($cursor === null) {
            return null;
        }

        if (!is_array($cursor)) {
            throw new \RuntimeException('Mongodb returned invalid data type');
        }

        $key = $cursor['_key'];

        unset($cursor['_key'], $cursor['_id']);

        return new Document(
            key: $key,
            data: $cursor
        );
    }

    public function remove(array $keys): void
    {
        $this->collection()->deleteMany([
            '_key' => ['$in' => $keys]
        ]);
    }

    public function store(Documents $documents): void
    {
        $items = $documents->map(function (Document $document) {
            return array_merge($document->data, [
                '_key' => $document->key
            ]);
        });

        if (empty($items)) {
            return;
        }

        $this->collection()->insertMany(array_values($items));
    }

    public function setup(): void
    {
        // TODO: Implement setup() method.
    }

    private function collection(): Collection
    {
        return $this->client
            ->selectDatabase($this->database)
            ->selectCollection($this->collection);
    }
}
