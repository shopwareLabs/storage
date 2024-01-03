<?php

namespace Shopware\Storage\Common\Document;

class Document
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $key,
        public array $data
    ) {
    }
}
