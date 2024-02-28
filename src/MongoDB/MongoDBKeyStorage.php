<?php

namespace Shopware\Storage\MongoDB;

use MongoDB\Client;
use MongoDB\Collection;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\KeyValue\KeyAware;
use Shopware\Storage\Common\Storage;

class MongoDBKeyStorage implements KeyAware, Storage
{
    public function __construct(
        private readonly string $database,
        private readonly Hydrator $hydrator,
        private readonly \Shopware\Storage\Common\Schema\Collection $collection,
        private readonly Client $client
    ) {}

    public function mget(array $keys): Documents
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

        $cursor = $this->collection()->findOne(['key' => $key], $options);

        if ($cursor === null) {
            return null;
        }

        if (!is_array($cursor)) {
            throw new \RuntimeException('Mongodb returned invalid data type');
        }

        return $this->hydrator->hydrate(
            collection: $this->collection,
            data: $cursor
        );
    }

    public function remove(array $keys): void
    {
        $this->collection()->deleteMany([
            'key' => ['$in' => $keys]
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

    public function setup(): void
    {
        // TODO: Implement setup() method.
    }

    private function collection(): Collection
    {
        return $this->client
            ->selectDatabase($this->database)
            ->selectCollection($this->collection->name);
    }
}
