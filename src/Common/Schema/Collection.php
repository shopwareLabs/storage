<?php

namespace Shopware\Storage\Common\Schema;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Collection implements FieldsAware
{
    use FieldsTrait;

    /**
     * @var class-string
     */
    public string $class;

    public function __construct(public string $name) {}
}
