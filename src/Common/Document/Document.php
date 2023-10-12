<?php

namespace Shopware\Storage\Common\Document;

class Document
{
    public function __construct(
        public string $key,
        public array $data
    ) {
    }
}
