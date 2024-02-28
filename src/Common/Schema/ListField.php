<?php

namespace Shopware\Storage\Common\Schema;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ListField extends Field
{
    use FieldsTrait;

    public function __construct(
        public string $innerType = FieldType::STRING,
        public bool $translated = false,
        public string $name = '',
    ) {
        parent::__construct(
            type: FieldType::LIST,
            translated: $translated,
            name: $name
        );
    }
}