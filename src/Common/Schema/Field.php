<?php

namespace Shopware\Storage\Common\Schema;

class Field
{
    /**
     * @param array<Field> $fields
     * @param array{translated?: bool, searchable?: bool, sortable?: bool, filterable?: bool} $options
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $options = [],
        public array $fields = [],
    ) {
        // map fields to use the field name as array key
        $this->fields = array_combine(
            array_map(fn(Field $field) => $field->name, $fields),
            $fields
        );
    }
}
