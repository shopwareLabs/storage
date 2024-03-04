<?php

namespace Shopware\Storage\Common\Document;

use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Schema\FieldsAware;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Translation\TranslatedBool;
use Shopware\Storage\Common\Schema\Translation\TranslatedDate;
use Shopware\Storage\Common\Schema\Translation\TranslatedFloat;
use Shopware\Storage\Common\Schema\Translation\TranslatedInt;
use Shopware\Storage\Common\Schema\Translation\TranslatedString;
use Shopware\Storage\Common\Schema\Translation\TranslatedText;
use Shopware\Storage\Common\Schema\Translation\Translation;
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

        if (!is_string($data['key'])) {
            throw new \LogicException('Invalid data, missing key for document');
        }

        $document->key = $data['key'];

        return $document;
    }

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

                $translation = self::translation(field: $field, value: $value);

                $translation?->resolve($context);

                $instance->{$key} = $translation;
                continue;
            }

            if ($field->type === FieldType::OBJECT) {
                // some storages store this value as string
                $value = is_string($value) ? json_decode($value, true) : $value;

                $instance->{$key} = $this->nested(
                    class: $field->class,
                    fields: $field,
                    data: $value,
                    context: $context
                );
                continue;
            }

            if ($field->type === FieldType::OBJECT_LIST) {
                // some storages store this value as string
                $value = is_string($value) ? json_decode($value, true) : $value;

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

    private function instance(string $class)
    {
        return (new \ReflectionClass($class))
            ->newInstanceWithoutConstructor();
    }

    private static function translation($field, ?array $value): ?Translation
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
