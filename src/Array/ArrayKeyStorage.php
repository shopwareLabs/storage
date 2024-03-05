<?php

namespace Shopware\Storage\Array;

use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

class ArrayKeyStorage implements Storage
{
    /**
     * @var array<string, Document>
     */
    private array $storage = [];

    public function destroy(): void
    {
        $this->storage = [];
    }

    public function clear(): void
    {
        $this->storage = [];
    }

    public function store(Documents $documents): void
    {
        foreach ($documents as $document) {
            $this->storage[$document->key] = $document;
        }
    }

    public function remove(array $keys): void
    {
        foreach ($keys as $key) {
            unset($this->storage[$key]);
        }
    }

    public function get(string $key, StorageContext $context): ?Document
    {
        return $this->storage[$key] ?? null;
    }

    public function mget(array $keys, StorageContext $context): Documents
    {
        $documents = new Documents();

        foreach ($keys as $key) {
            if (!isset($this->storage[$key])) {
                continue;
            }

            $documents->set($key, $this->storage[$key]);
        }

        return $documents;
    }

    public function setup(): void
    {
        $this->storage = [];
    }
}
