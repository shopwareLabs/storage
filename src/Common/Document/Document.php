<?php

namespace Shopware\Storage\Common\Document;

use Shopware\Storage\Common\Schema\Field;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Util\JsonSerializableTrait;

#[\AllowDynamicProperties]
abstract class Document implements \JsonSerializable
{
    public const JSON_OPTIONS = \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_IGNORE;

    use JsonSerializableTrait;

    public function __construct(
        #[Field(type: FieldType::STRING)]
        public string $key
    ) {}

    public function encode(): array
    {
        $data = json_decode(json_encode($this, self::JSON_OPTIONS), true);

        $data['key'] = $this->key;

        return $data;
    }
}
