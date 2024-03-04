<?php

namespace Shopware\Storage\Common\KeyValue;

use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\StorageContext;

interface KeyAware
{
    /**
     * @param array<string> $keys
     */
    public function mget(array $keys, StorageContext $context): Documents;

    public function get(string $key, StorageContext $context): ?Document;
}
