<?php

namespace Shopware\Storage\Common\Schema;

/**
 * @phpstan-type Field=array{type: string, translated?: bool, fields?: array<string, mixed>}
 */
class Schema
{
    /**
     * @param string $source
     * @param array<string, Field> $fields
     */
    public function __construct(
        public string $source,
        public array $fields
    ) {
    }
}
