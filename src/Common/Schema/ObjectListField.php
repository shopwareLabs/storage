<?php

namespace Shopware\Storage\Common\Schema;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ObjectListField extends Field
{
    use FieldsTrait;

    public function __construct(
        public string $class,
        public string $name = '',
    ) {
        parent::__construct(
            type: FieldType::OBJECT_LIST,
            name: $name
        );
    }
}
