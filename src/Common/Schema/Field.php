<?php

namespace Shopware\Storage\Common\Schema;

class Field
{
    /**
     * @param array<Field> $fields
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $translated = false,
        public array $fields = []
    ) {
        // map fields to use the field name as array key
        $this->fields = array_combine(
            array_map(fn(Field $field) => $field->name, $fields),
            $fields
        );
    }
}
