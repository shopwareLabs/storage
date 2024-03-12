<?php

namespace Shopware\Storage\Common\Schema;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ListField extends Field
{
    public function __construct(
        public string $innerType = FieldType::STRING,
        public bool $translated = false,
        public string $name = '',
        public bool $searchable = false,
    ) {
        parent::__construct(
            type: FieldType::LIST,
            translated: $translated,
            name: $name,
            searchable: $searchable
        );
    }
}
