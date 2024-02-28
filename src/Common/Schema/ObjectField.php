<?php

namespace Shopware\Storage\Common\Schema;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ObjectField extends Field
{
    use FieldsTrait;

    public function __construct(
        public string $class,
        public string $name = '',
    ) {
        parent::__construct(
            type: FieldType::OBJECT,
            name: $name
        );
    }
}
