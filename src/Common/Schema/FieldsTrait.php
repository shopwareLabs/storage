<?php

namespace Shopware\Storage\Common\Schema;

trait FieldsTrait
{
    private array $fields = [];

    public function add(Field ...$fields): static
    {
        foreach ($fields as $field) {
            $this->fields[$field->name] = $field;
        }

        return $this;
    }

    public function get(string $field): Field|ObjectField|ObjectListField|ListField
    {
        if (!isset($this->fields[$field])) {
            throw new \InvalidArgumentException(sprintf('Field %s not found', $field));
        }
        return $this->fields[$field];
    }
}