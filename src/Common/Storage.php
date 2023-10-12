<?php

namespace Shopware\Storage\Common;

use Shopware\Storage\Common\Document\Documents;

interface Storage
{
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

    /**
     * Creates the storage
     * @return void
     */
    public function setup(): void;
}