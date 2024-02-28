<?php

namespace Shopware\Storage\Common\Schema;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Field
{
    public function __construct(
        public string $type,
        public bool $translated = false,
        public string $name = '',
    ) {}
}
