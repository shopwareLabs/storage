<?php

namespace Shopware\Storage\Common\KeyValue;

use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;

interface KeyAware
{
    /**
     * @param array<string> $keys
     * @return Documents
     */
    public function mget(array $keys): Documents;

    /**
     * @param string $key
     * @return Document|null
     */
    public function get(string $key): ?Document;
}
