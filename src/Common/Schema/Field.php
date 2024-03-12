<?php

namespace Shopware\Storage\Common\Schema;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Field
{
    public bool $nullable;

    public function __construct(
        public string $type,
        public bool $translated = false,
        public string $name = '',
        public bool $searchable = false
    ) {}
}
