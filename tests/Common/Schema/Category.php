<?php

namespace Shopware\StorageTests\Common\Schema;

use Shopware\Storage\Common\Schema\Field;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\ListField;
use Shopware\Storage\Common\Schema\ObjectField;
use Shopware\Storage\Common\Schema\Translation\TranslatedBool;
use Shopware\Storage\Common\Schema\Translation\TranslatedDate;
use Shopware\Storage\Common\Schema\Translation\TranslatedFloat;
use Shopware\Storage\Common\Schema\Translation\TranslatedInt;
use Shopware\Storage\Common\Schema\Translation\TranslatedString;
use Shopware\Storage\Common\Schema\Translation\TranslatedText;
use Shopware\Storage\Common\Schema\Translation\Translation;
use Shopware\Storage\Common\Util\JsonSerializableTrait;

class Category
{
    use JsonSerializableTrait;

    public function __construct(
        #[Field(type: FieldType::STRING)]
        public ?string $ean = null,

        #[Field(type: FieldType::TEXT)]
        public ?string $comment = null,

        #[Field(type: FieldType::INT)]
        public ?int $stock = null,

        #[Field(type: FieldType::FLOAT)]
        public ?float $price = null,

        #[Field(type: FieldType::BOOL)]
        public ?bool $active = null,

        #[Field(type: FieldType::DATETIME)]
        public ?string $changed = null,

        /** @var array<string> */
        #[ListField(innerType: FieldType::STRING)]
        public ?array $keywords = null,

        /** @var array<int> */
        #[ListField(innerType: FieldType::INT)]
        public ?array $states = null,

        /** @var array<float> */
        #[ListField(innerType: FieldType::FLOAT)]
        public ?array $dimensions = null,

        /** @var array<string> */
        #[ListField(innerType: FieldType::DATETIME)]
        public ?array $timestamps = null,

        #[Field(type: FieldType::STRING, translated: true)]
        public ?TranslatedString $name = null,

        #[Field(type: FieldType::TEXT, translated: true)]
        public ?TranslatedText $description = null,

        #[Field(type: FieldType::INT, translated: true)]
        public ?TranslatedInt $position = null,

        #[Field(type: FieldType::FLOAT, translated: true)]
        public ?TranslatedFloat $weight = null,

        #[Field(type: FieldType::BOOL, translated: true)]
        public ?TranslatedBool $highlight = null,

        #[Field(type: FieldType::DATETIME, translated: true)]
        public ?TranslatedDate $release = null,

        #[Field(type: FieldType::LIST, translated: true)]
        public ?Translation $tags = null,

        #[ObjectField(class: Media::class)]
        public ?Media $logo = null
    ) {}
}
