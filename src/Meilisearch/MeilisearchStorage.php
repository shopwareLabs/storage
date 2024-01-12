<?php

namespace Shopware\Storage\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Exception\NotSupportedByEngine;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\Common\Filter\Operator\AndOperator;
use Shopware\Storage\Common\Filter\Operator\NandOperator;
use Shopware\Storage\Common\Filter\Operator\NorOperator;
use Shopware\Storage\Common\Filter\Operator\Operator;
use Shopware\Storage\Common\Filter\Operator\OrOperator;
use Shopware\Storage\Common\Filter\Paging\Page;
use Shopware\Storage\Common\Filter\Type\Any;
use Shopware\Storage\Common\Filter\Type\Contains;
use Shopware\Storage\Common\Filter\Type\Equals;
use Shopware\Storage\Common\Filter\Type\Filter;
use Shopware\Storage\Common\Filter\Type\Gt;
use Shopware\Storage\Common\Filter\Type\Gte;
use Shopware\Storage\Common\Filter\Type\Lt;
use Shopware\Storage\Common\Filter\Type\Lte;
use Shopware\Storage\Common\Filter\Type\Neither;
use Shopware\Storage\Common\Filter\Type\Not;
use Shopware\Storage\Common\Filter\Type\Prefix;
use Shopware\Storage\Common\Filter\Type\Suffix;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\StorageContext;

class MeilisearchStorage implements FilterStorage
{
    public function __construct(
        private readonly Client $client,
        private readonly Schema $schema
    ) {}

    public function filter(FilterCriteria $criteria, StorageContext $context): FilterResult
    {
        $params = [];

        if ($criteria->paging instanceof Page) {
            $params['page'] = $criteria->paging->page;
            $params['hitsPerPage'] = $criteria->limit;
        } elseif ($criteria->limit !== null) {
            $params['limit'] = $criteria->limit;
        }

        $filters = $criteria->filters;
        if ($criteria->keys !== null) {
            $filters[] = new Any(field: 'key', value: $criteria->keys);
        }

        if (!empty($filters)) {
            $params['filter'] = $this->parse($filters, $context);
        }

        $result = $this->index()->search(
            query: null,
            searchParams: $params
        );

        $documents = [];
        foreach ($result->getHits() as $hit) {
            $key = $hit['key'];
            unset($hit['key']);

            $documents[] = new Document(key: $key, data: $hit);
        }

        return new FilterResult(
            elements: $documents
        );
    }

    public function remove(array $keys): void
    {
        $this->index()->deleteDocuments($keys);
    }

    public function store(Documents $documents): void
    {
        $data = [];
        foreach ($documents as $document) {
            $record = $document->data;
            $record['key'] = $document->key;
            $data[] = $record;
        }

        $this->index()->addDocuments($data);
    }

    public function setup(): void {}

    public function index(): Indexes
    {
        return $this->client->index($this->schema->source);
    }

    /**
     * @param array<Operator|Filter> $filters
     */
    private function parse(array $filters, StorageContext $context): string
    {
        $parsed = [];

        foreach ($filters as $filter) {
            if ($filter instanceof Operator) {
                $parsed[] = $this->parseOperator($filter, $context);
            }

            if ($filter instanceof Filter) {
                $parsed[] = $this->parseFilter($filter, $context);
            }
        }

        if (count($parsed) === 1) {
            return $parsed[0];
        }

        return self::and($parsed);
    }

    private static function cast(mixed $value): mixed
    {
        if (is_string($value)) {
            return '"' . $value . '"';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (!is_array($value)) {
            return $value;
        }

        return array_map(fn($v) => self::cast($v), $value);
    }

    private function parseFilter(Filter $filter, StorageContext $context): string
    {
        $property = SchemaUtil::property($filter->field);

        $translated = SchemaUtil::translated(schema: $this->schema, accessor: $property);

        $value = SchemaUtil::cast($this->schema, $filter->field, $filter->value);

        $value = self::cast($value);

        $factory = function (\Closure $closure) use ($filter, $value) {
            return $closure($filter->field, $value);
        };

        if ($translated) {
            $factory = function (\Closure $closure) use ($filter, $context, $value) {
                return $this->translatedQuery($closure, $filter, $value, $context);
            };
        }

        if (is_string($filter->value) && ($filter instanceof Lt || $filter instanceof Gt || $filter instanceof Lte || $filter instanceof Gte)) {
            throw new NotSupportedByEngine('34', 'Meilisearch does not support string comparison.');
        }
        if ($filter instanceof Contains) {
            throw new NotSupportedByEngine('33', ' Meilisearch contains/prefix/suffix filter are not supported.');
        }
        if ($filter instanceof Prefix) {
            throw new NotSupportedByEngine('33', ' Meilisearch contains/prefix/suffix filter are not supported.');
        }
        if ($filter instanceof Suffix) {
            throw new NotSupportedByEngine('33', ' Meilisearch contains/prefix/suffix filter are not supported.');
        }

        if ($filter->value === null) {
            if ($filter instanceof Equals) {
                return $factory(fn($field, $value) => $field . ' IS NULL');
            }

            if ($filter instanceof Not) {
                return $factory(fn($field, $value) => $field . ' IS NOT NULL');
            }

            throw new \RuntimeException('Null filter values are only supported for Equals and Not filters');
        }

        if ($filter instanceof Equals) {
            return $factory(fn($field, $value) => $field . ' = ' . $value);
        }

        if ($filter instanceof Not) {
            return $factory(fn($field, $value) => $field . ' != ' . $value);
        }

        if ($filter instanceof Any) {
            return $factory(function (string $field, mixed $value) {
                return $field . ' IN [' . implode(',', $value) . ']';
            });
        }

        if ($filter instanceof Neither) {

            return $factory(function (string $field, mixed $value) {
                return $field . ' NOT IN [' . implode(',', $value) . ']';
            });
        }

        if ($filter instanceof Lt) {
            return $factory(fn($field, $value) => $field . ' < ' . $value);
        }

        if ($filter instanceof Gt) {
            return $factory(fn($field, $value) => $field . ' > ' . $value);
        }

        if ($filter instanceof Lte) {
            return $factory(fn($field, $value) => $field . ' <= ' . $value);
        }

        if ($filter instanceof Gte) {
            return $factory(fn($field, $value) => $field . ' >= ' . $value);
        }

        throw new \RuntimeException('Unknown filter: ' . get_class($filter));
    }

    private function parseOperator(Operator $operator, StorageContext $context): string
    {
        $parsed = [];
        foreach ($operator->filters as $filter) {
            $parsed[] = $this->parse([$filter], $context);
        }

        if ($operator instanceof AndOperator) {
            return self::and($parsed);
        }

        if ($operator instanceof OrOperator) {
            return self::or($parsed);
        }

        if ($operator instanceof NandOperator) {
            return 'NOT ' . self::and($parsed);
        }

        if ($operator instanceof NorOperator) {
            return 'NOT ' . self::or($parsed);
        }

        throw new \RuntimeException('Unknown operator: ' . get_class($operator));
    }

    private function translatedQuery(\Closure $closure, Filter $filter, mixed $value, StorageContext $context): string
    {
        $queries = [];

        $before = [];

        foreach ($context->languages as $index => $languageId) {
            if (array_key_first($context->languages) === $index) {
                $queries[] = self::and([
                    $filter->field . '.' . $languageId . ' EXISTS',
                    $filter->field . '.' . $languageId . ' IS NOT NULL',
                    $closure($filter->field . '.' . $languageId, $value),
                ]);

                $before[] = $languageId;

                continue;
            }

            $nested = [];
            foreach ($before as $id) {
                $nested[] = self::or([
                    $filter->field . '.' . $id . ' NOT EXISTS',
                    $filter->field . '.' . $id . ' IS NULL'
                ]);
            }

            $nested[] = self::and([
                $filter->field . '.' . $languageId . ' EXISTS',
                $filter->field . '.' . $languageId . ' IS NOT NULL',
                $closure($filter->field . '.' . $languageId, $value),
            ]);

            $before[] = $languageId;

            $queries[] = self::and($nested);
        }

        return self::or($queries);
    }

    /**
     * @param array<string> $elements
     */
    private static function or(array $elements): string
    {
        return '(' . implode(' OR ', $elements) . ')';
    }

    /**
     * @param array<string> $elements
     */
    private static function and(array $elements): string
    {
        return '(' . implode(' AND ', $elements) . ')';
    }
}
