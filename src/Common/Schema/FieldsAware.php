<?php

namespace Shopware\Storage\Common\Schema;

interface FieldsAware
{
    public function add(Field ...$fields): static;

    public function get(string $field): Field|ObjectField|ObjectListField|ListField;

    /**
     * @return array<string, Field>
     */
    public function fields(): array;
}
