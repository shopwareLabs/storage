<?php

namespace Shopware\Storage\Common\Schema;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ObjectField extends Field implements FieldsAware
{
    use FieldsTrait;

    /**
     * @param class-string $class
     */
    public function __construct(
        public string $class,
        public string $name = ''
    ) {
        parent::__construct(
            type: FieldType::OBJECT,
            name: $name,
            // just to trigger storage recursions during setup and store
            searchable: true
        );
    }
}
