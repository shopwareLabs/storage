<?php

namespace Shopware\Storage\Common\Document;

use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Translation;

class Hydrator
{
    /**
     * @param array<array<string, mixed>> $data
     * @return Document
     */
    public function hydrate(Collection $collection, array $data): Document
    {
        $document = $this->nested(
            class: $collection->class,
            fields: $collection,
            data: $data
        );

        if (!is_string($data['key'])) {
            throw new \LogicException('Invalid data, missing key for document');
        }

        $document->key = $data['key'];

        return $document;
    }

    private function nested(string $class, object $fields, array $data): object
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

                $instance->{$key} = new Translation($value);
                continue;
            }

            if ($field->type === FieldType::OBJECT) {
                // some storages store this value as string
                $value = is_string($value) ? json_decode($value, true) : $value;

                $instance->{$key} = $this->nested($field->class, $field, $value);
                continue;
            }

            if ($field->type === FieldType::OBJECT_LIST) {
                // some storages store this value as string
                $value = is_string($value) ? json_decode($value, true) : $value;

                $instance->{$key} = array_map(
                    fn($item) => $this->nested($field->class, $field, $item),
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
}