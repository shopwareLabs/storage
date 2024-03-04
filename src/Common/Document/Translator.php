<?php

namespace Shopware\Storage\Common\Document;

use Shopware\Storage\Common\Schema\Field;
use Shopware\Storage\Common\Schema\ObjectField;
use Shopware\Storage\Common\Schema\ObjectListField;
use Shopware\Storage\Common\Schema\Translation\Translation;
use Shopware\Storage\Common\StorageContext;

class Translator
{
    /**
     * @param iterable<Document> $documents
     */
    public static function translate(
        \Shopware\Storage\Common\Schema\Collection $collection,
        iterable $documents,
        StorageContext $context
    ): void {
        foreach ($documents as $document) {
            self::_translate(
                fields: $collection->fields(),
                object: $document,
                context: $context
            );
        }
    }

    /**
     * @param array<Field> $fields
     * @param StorageContext $context
     */
    private static function _translate(array $fields, object $object, StorageContext $context): void
    {
        foreach ($fields as $field) {
            $value = $object->{$field->name};

            if ($value === null) {
                continue;
            }

            if ($value instanceof Translation) {
                $value->resolve($context);
                continue;
            }

            if ($field instanceof ObjectField) {
                self::_translate(fields: $field->fields(), object: $value, context: $context);
                continue;
            }

            if ($field instanceof ObjectListField) {
                foreach ($value as $item) {
                    self::_translate(fields: $field->fields(), object: $item, context: $context);
                }
            }
        }
    }
}
