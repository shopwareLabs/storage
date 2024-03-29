<?php

namespace Shopware\Storage\Common\Schema;

class Registry
{
    /**
     * @var array<string, \ReflectionClass<object>>
     */
    private array $reflections = [];

    /**
     * @var array<string, Collection>
     */
    private array $collections = [];

    /**
     * @param class-string ...$classes
     */
    public function add(string ...$classes): void
    {
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);

            $collection = $reflection->getAttributes(Collection::class);

            /** @var Collection $instance */
            $instance = $collection[0]->newInstance();

            $this->reflections[$instance->name] = $reflection;
        }
    }

    public function get(string $collection): Collection
    {
        if (!isset($this->collections[$collection])) {
            $this->collections[$collection] = $this->parse($collection);
        }

        return $this->collections[$collection];
    }

    private function parse(string $collection): Collection
    {
        $reflection = $this->reflections[$collection];

        $attribute = $reflection->getAttributes(Collection::class);

        /** @var Collection $collection */
        $collection = $attribute[0]->newInstance();

        $collection->class = $reflection->getName();

        $collection->add(
            ...$this->parseFields($reflection->name)
        );

        return $collection;
    }

    /**
     * @param class-string $class
     * @return array<string, Field|ObjectField|ObjectListField|ListField>
     */
    private function parseFields(string $class): array
    {
        $reflection = new \ReflectionClass($class);

        $properties = $reflection->getProperties();

        $fields = [];
        foreach ($properties as $property) {
            $attribute = $this->getPropertyAttribute($property);

            if ($attribute === null) {
                continue;
            }

            /** @var Field $field */
            $field = $attribute->newInstance();

            $field->name = !empty($field->name) ? $field->name : $property->getName();

            $field->nullable = $property->getType()?->allowsNull() ?? true;

            $fields[$property->getName()] = $field;

            if ($field instanceof ObjectField || $field instanceof ObjectListField) {
                $field->add(
                    ...$this->parseFields($field->class)
                );
            }
        }

        return $fields;
    }

    /**
     * @return \ReflectionAttribute<Field>|null
     */
    private function getPropertyAttribute(\ReflectionProperty $property): ?\ReflectionAttribute
    {
        $attribute = $property->getAttributes(Field::class);

        if (!empty($attribute)) {
            return $attribute[0];
        }

        $attribute = $property->getAttributes(ObjectField::class);
        if (!empty($attribute)) {
            return $attribute[0];
        }

        $attribute = $property->getAttributes(ObjectListField::class);
        if (!empty($attribute)) {
            return $attribute[0];
        }

        $attribute = $property->getAttributes(ListField::class);
        if (!empty($attribute)) {
            return $attribute[0];
        }

        return null;
    }
}
