<?php

namespace Shopware\StorageTests\Common\Schema;

use Shopware\Storage\Common\Schema\Field;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Util\JsonSerializableTrait;

class Media
{
    use JsonSerializableTrait;

    public function __construct(
        #[Field(type: FieldType::STRING)]
        public ?string $url = null,
        #[Field(type: FieldType::STRING, translated: true)]
        public ?string $alt = null
    ) {}
}
