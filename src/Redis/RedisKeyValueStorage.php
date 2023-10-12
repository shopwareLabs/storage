<?php

namespace Shopware\Storage\Redis;

use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\KeyValue\KeyValueStorage;

class RedisKeyValueStorage implements KeyValueStorage
{
    public function __construct(private readonly \Redis $client)
    {
    }

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
            $mapped[$document->key] = json_encode($document->data, \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_IGNORE);
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

            $documents[] = new Document($key, json_decode($value, true, 512, \JSON_THROW_ON_ERROR));
        }

        return new Documents($documents);
    }

    public function get(string $key): ?Document
    {
        $value = $this->client->get($key);

        if ($value === false) {
            return null;
        }

        return new Document($key, json_decode($value, true, 512, \JSON_THROW_ON_ERROR));
    }
}
