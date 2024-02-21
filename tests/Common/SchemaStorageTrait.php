<?php

namespace Shopware\StorageTests\Common;

use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Schema\Field;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Schema;

trait SchemaStorageTrait
{
    protected string $storageName = 'test_storage';

    protected function getSchema(): Schema
    {
        return new Schema(
            source: $this->storageName,
            fields: [
                new Field('stringField', FieldType::STRING),
                new Field('textField', FieldType::TEXT),
                new Field('intField', FieldType::INT),
                new Field('floatField', FieldType::FLOAT),
                new Field('boolField', FieldType::BOOL),
                new Field('dateField', FieldType::DATETIME),
                new Field('listField', FieldType::LIST),

                new Field('translatedString', FieldType::STRING, ['translated' => true]),
                new Field('translatedText', FieldType::TEXT, ['translated' => true]),
                new Field('translatedInt', FieldType::INT, ['translated' => true]),
                new Field('translatedFloat', FieldType::FLOAT, ['translated' => true]),
                new Field('translatedBool', FieldType::BOOL, ['translated' => true]),
                new Field('translatedDate', FieldType::DATETIME, ['translated' => true]),
                new Field('translatedList', FieldType::LIST, ['translated' => true]),

                new Field('objectField', FieldType::OBJECT, [], [
                    new Field('stringField', FieldType::STRING),
                    new Field('textField', FieldType::TEXT),
                    new Field('intField', FieldType::INT),
                    new Field('floatField', FieldType::FLOAT),
                    new Field('boolField', FieldType::BOOL),
                    new Field('dateField', FieldType::DATETIME),
                    new Field('listField', FieldType::LIST),

                    new Field('translatedString', FieldType::STRING, ['translated' => true]),
                    new Field('translatedText', FieldType::TEXT, ['translated' => true]),
                    new Field('translatedInt', FieldType::INT, ['translated' => true]),
                    new Field('translatedFloat', FieldType::FLOAT, ['translated' => true]),
                    new Field('translatedBool', FieldType::BOOL, ['translated' => true]),
                    new Field('translatedDate', FieldType::DATETIME, ['translated' => true]),
                    new Field('translatedList', FieldType::LIST, ['translated' => true]),

                    new Field('fooObj', FieldType::OBJECT, [], [
                        new Field('bar', FieldType::STRING),
                    ]),
                ]),

                new Field('objectListField', FieldType::OBJECT_LIST, [], [
                    new Field('stringField', FieldType::STRING),
                    new Field('textField', FieldType::TEXT),
                    new Field('intField', FieldType::INT),
                    new Field('floatField', FieldType::FLOAT),
                    new Field('boolField', FieldType::BOOL),
                    new Field('dateField', FieldType::DATETIME),
                    new Field('listField', FieldType::LIST),

                    new Field('translatedString', FieldType::STRING, ['translated' => true]),
                    new Field('translatedText', FieldType::TEXT, ['translated' => true]),
                    new Field('translatedInt', FieldType::INT, ['translated' => true]),
                    new Field('translatedFloat', FieldType::FLOAT, ['translated' => true]),
                    new Field('translatedBool', FieldType::BOOL, ['translated' => true]),
                    new Field('translatedDate', FieldType::DATETIME, ['translated' => true]),
                    new Field('translatedList', FieldType::LIST, ['translated' => true]),

                    new Field('fooObj', FieldType::OBJECT, [], [
                        new Field('bar', FieldType::STRING),
                        new Field('translatedBar', FieldType::STRING, ['translated' => true]),
                    ]),
                ]),
            ]
        );
    }

    /**
     * @param array<string, mixed>|null $objectField
     * @param array<mixed>|null $listField
     * @param array<array<string, mixed>>|null $objectListField
     * @param array<string, string|null>|null $translatedString
     * @param array<string, int|null>|null $translatedInt
     * @param array<string, float|null>|null $translatedFloat
     * @param array<string, bool|null>|null $translatedBool
     * @param array<string, string|null>|null $translatedDate
     */
    protected static function document(
        string $key,
        ?string $stringField = null,
        ?bool $boolField = null,
        ?string $textField = null,
        ?string $dateField = null,
        ?int $intField = null,
        ?float $floatField = null,
        ?array $objectField = null,
        ?array $listField = null,
        ?array $objectListField = null,
        ?array $translatedString = null,
        ?array $translatedInt = null,
        ?array $translatedFloat = null,
        ?array $translatedBool = null,
        ?array $translatedDate = null,
    ): Document {
        return new Document(
            key: $key,
            data: [
                'stringField' => $stringField,
                'textField' => $textField,
                'dateField' => $dateField,
                'boolField' => $boolField,
                'intField' => $intField,
                'floatField' => $floatField,
                'objectField' => $objectField,
                'listField' => $listField,
                'objectListField' => $objectListField,
                'translatedString' => $translatedString,
                'translatedInt' => $translatedInt,
                'translatedFloat' => $translatedFloat,
                'translatedBool' => $translatedBool,
                'translatedDate' => $translatedDate,
            ]
        );
    }
}
