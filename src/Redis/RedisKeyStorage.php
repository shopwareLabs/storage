<?php

namespace Shopware\Storage\Redis;

use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\KeyValue\KeyAware;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Storage;

class RedisKeyStorage implements Storage, KeyAware
{
    public function __construct(
        private readonly Collection $collection,
        private readonly \Redis $client,
        private readonly Hydrator $hydrator
    ) {}

    public function setup(): void
    {
        // todo@o.skroblin auto setup feature
    }

    public function remove(array $keys): void
    {
        $this->client->del($keys);
    }

    public function store(Documents $documents): void
    {
        $mapped = [];
        foreach ($documents as $document) {
            $mapped[$document->key] = json_encode($document->encode(), Document::JSON_OPTIONS);
        }

        if (empty($mapped)) {
            return;
        }

        $this->client->mSet($mapped);
    }

    public function mget(array $keys): Documents
    {
        $values = $this->client->mGet($keys);

        $documents = [];
        foreach ($values as $index => $value) {
            if ($value === false) {
                continue;
            }
            $key = $keys[$index];

            if (!is_string($value)) {
                throw new \RuntimeException(sprintf('Invalid data type for key %s', $key));
            }

            $data = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new \RuntimeException(sprintf('Invalid data type for key %s', $key));
            }

            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $data
            );
        }

        return new Documents($documents);
    }

    public function get(string $key): ?Document
    {
        $value = $this->client->get($key);

        if ($value === false) {
            return null;
        }

        $data = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Invalid data type for key %s', $key));
        }

        return $this->hydrator->hydrate(
            collection: $this->collection,
            data: $data
        );
    }
}
