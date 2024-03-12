<?php

namespace Shopware\Storage\Common;

use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;

interface Storage
{
    /**
     * Creates the storage
     * @return void
     */
    public function setup(): void;
    /**
     * @param array<string> $keys
     */
    public function mget(array $keys, StorageContext $context): Documents;

    public function get(string $key, StorageContext $context): ?Document;

    /**
     * @param array<string> $keys
     * @return void
     */
    public function remove(array $keys): void;

    /**
     * @param Documents $documents
     * @return void
     */
    public function store(Documents $documents): void;

    public function clear(): void;

    public function destroy(): void;
}
