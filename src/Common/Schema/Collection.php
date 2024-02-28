<?php

namespace Shopware\Storage\Common\Schema;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Collection
{
    public string $class;

    use FieldsTrait;

    public function __construct(public string $name) {}

    public function getFields(): array
    {
        return $this->fields;
    }
}
