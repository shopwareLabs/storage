<?php

namespace Shopware\Storage\MongoDB;

use MongoDB\Client;
use MongoDB\Collection;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

class MongoDBKeyStorage implements Storage
{
    public function __construct(
        private readonly string $database,
        private readonly Hydrator $hydrator,
        private readonly \Shopware\Storage\Common\Schema\Collection $collection,
        private readonly Client $client
    ) {}

    public function setup(): void {}

    public function clear(): void
    {
        $this->collection()->deleteMany([]);
    }

    public function destroy(): void
    {
        $this->collection()->drop();
    }

    public function mget(array $keys, StorageContext $context): Documents
    {
        $query['key'] = ['$in' => $keys];

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

            $result[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $data,
                context: $context
            );
        }

        return new Documents($result);
    }

    public function get(string $key, StorageContext $context): ?Document
    {
        $options = [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ],
        ];

        $cursor = $this->collection()->findOne(['key' => $key], $options);

        if ($cursor === null) {
            return null;
        }

        if (!is_array($cursor)) {
            throw new \RuntimeException('Mongodb returned invalid data type');
        }

        return $this->hydrator->hydrate(
            collection: $this->collection,
            data: $cursor,
            context: $context
        );
    }

    public function remove(array $keys): void
    {
        $this->collection()->deleteMany([
            'key' => ['$in' => $keys],
        ]);
    }

    public function store(Documents $documents): void
    {
        $items = $documents->map(function (Document $document) {
            return $document->encode();
        });

        if (empty($items)) {
            return;
        }

        $this->collection()->insertMany(array_values($items));
    }

    private function collection(): Collection
    {
        return $this->client
            ->selectDatabase($this->database)
            ->selectCollection($this->collection->name);
    }
}
