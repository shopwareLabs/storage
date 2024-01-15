<?php

namespace Shopware\Storage\Common\Schema;

class Schema
{
    /**
     * @param string $source
     * @param Field[] $fields
     */
    public function __construct(
        public string $source,
        public array $fields
    ) {
        // map fields to use the field name as array key
        $this->fields = array_combine(
            array_map(fn(Field $field) => $field->name, $fields),
            $fields
        );
    }
}
