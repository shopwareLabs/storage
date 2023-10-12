<?php

namespace Shopware\Storage\Common\Schema;

/**
 * @phpstan-type Fields=array<string, Field>
 * @phpstan-type Field=array{"type": FieldType, "fields"?: Fields}
 */
class Schema
{
    /**
     * @param string $source
     * @param Fields $fields
     */
    public function __construct(
        public string $source,
        public array $fields
    ) {
    }
}
