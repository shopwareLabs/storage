<?php

namespace Shopware\StorageTests\Common\Schema;

use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Schema\Field;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\ListField;
use Shopware\Storage\Common\Schema\ObjectField;
use Shopware\Storage\Common\Schema\ObjectListField;
use Shopware\Storage\Common\Schema\Translation;

#[Collection(name: 'product')]
class Product extends Document
{
    public function __construct(
        #[Field(type: FieldType::STRING)]
        public string $key,
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
        #[Field(type: FieldType::LIST)]
        public ?array $keywords = null,
        #[Field(type: FieldType::STRING, translated: true)]
        public ?Translation $name = null,
        #[Field(type: FieldType::TEXT, translated: true)]
        public ?Translation $description = null,
        #[Field(type: FieldType::INT, translated: true)]
        public ?Translation $position = null,
        #[Field(type: FieldType::FLOAT, translated: true)]
        public ?Translation $weight = null,
        #[Field(type: FieldType::BOOL, translated: true)]
        public ?Translation $highlight = null,
        #[Field(type: FieldType::DATETIME, translated: true)]
        public ?Translation $release = null,
        #[ListField(innerType: FieldType::STRING, translated: true)]
        public ?Translation $tags = null,
        #[ObjectField(class: Category::class)]
        public ?Category $mainCategory = null,
        #[ObjectListField(class: Category::class)]
        public ?array $categories = null
    ) {}
}
