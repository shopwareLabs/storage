<?php

namespace Shopware\Storage\Common\Document;

use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Schema\FieldsAware;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\ObjectField;
use Shopware\Storage\Common\Schema\ObjectListField;
use Shopware\Storage\Common\Schema\Translation\TranslatedBool;
use Shopware\Storage\Common\Schema\Translation\TranslatedDate;
use Shopware\Storage\Common\Schema\Translation\TranslatedFloat;
use Shopware\Storage\Common\Schema\Translation\TranslatedInt;
use Shopware\Storage\Common\Schema\Translation\TranslatedString;
use Shopware\Storage\Common\Schema\Translation\TranslatedText;
use Shopware\Storage\Common\StorageContext;

class Hydrator
{
    /**
     * @param array<string, mixed> $data
     */
    public function hydrate(Collection $collection, array $data, StorageContext $context): Document
    {
        $document = $this->nested(
            class: $collection->class,
            fields: $collection,
            data: $data,
            context: $context
        );

        if (!$document instanceof Document) {
            throw new \LogicException(sprintf('Invalid document class configured in collection %s. Expected to be an instance of %s but got %s', $collection->class, Document::class, get_class($document)));
        }

        if (!is_string($data['key'])) {
            throw new \LogicException('Invalid data, missing key for document');
        }

        $document->key = $data['key'];

        return $document;
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $data
     */
    private function nested(string $class, FieldsAware $fields, array $data, StorageContext $context): object
    {
        $instance = $this->instance($class);

        foreach ($data as $key => $value) {
            try {
                $field = $fields->get($key);
            } catch (\Exception $e) {
                continue;
            }

            if ($value === null) {
                $instance->{$key} = null;
                continue;
            }

            if ($field->type === FieldType::LIST) {
                // some storages store this value as json string
                $value = is_string($value) ? json_decode($value, true) : $value;
            }

            if ($field->translated) {
                // some storages store this value as string
                $value = is_string($value) ? json_decode($value, true) : $value;

                if (!is_array($value)) {
                    throw new \LogicException(sprintf('Invalid data for field %s, expected array but got %s', $field->name, gettype($value)));
                }

                $translation = self::translation(field: $field, value: $value);

                $translation?->resolve($context);

                $instance->{$key} = $translation;
                continue;
            }

            if ($field instanceof ObjectField) {
                // some storages store this value as string
                $value = is_string($value) ? json_decode($value, true) : $value;

                if (!is_array($value)) {
                    throw new \LogicException(sprintf('Invalid data for field %s, expected array but got %s', $field->name, gettype($value)));
                }

                $instance->{$key} = $this->nested(
                    class: $field->class,
                    fields: $field,
                    data: $value,
                    context: $context
                );
                continue;
            }

            if ($field instanceof ObjectListField) {
                // some storages store this value as string
                $value = is_string($value) ? json_decode($value, true) : $value;

                if (!is_array($value)) {
                    throw new \LogicException(sprintf('Invalid data for field %s, expected array but got %s', $field->name, gettype($value)));
                }

                $instance->{$key} = array_map(
                    fn($item) => $this->nested(
                        class: $field->class,
                        fields: $field,
                        data: $item,
                        context: $context
                    ),
                    $value
                );
                continue;
            }

            $instance->{$key} = $value;
        }

        return $instance;
    }

    /**
     * @param class-string $class
     */
    private function instance(string $class): object
    {
        return (new \ReflectionClass($class))
            ->newInstanceWithoutConstructor();
    }

    // @phpstan-ignore-next-line Otherwise phpstan will complain about the match statement
    private static function translation($field, ?array $value): TranslatedBool|TranslatedDate|TranslatedFloat|TranslatedInt|TranslatedString|TranslatedText|null
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            FieldType::BOOL => new TranslatedBool(translations: $value),
            FieldType::FLOAT => new TranslatedFloat(translations: $value),
            FieldType::INT => new TranslatedInt(translations: $value),
            FieldType::TEXT => new TranslatedText(translations: $value),
            FieldType::DATETIME => new TranslatedDate(translations: $value),
            default => new TranslatedString(translations: $value),
        };
    }
}
