<?php

namespace Shopware\Storage\MongoDB;

use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Collection;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\StorageContext;

class MongoDBFilterStorage implements FilterStorage
{
    public function __construct(
        private readonly string $database,
        private readonly Schema $schema,
        private readonly Client $client
    ) {
    }

    public function setup(): void
    {
        // todo@o.skroblin auto setup feature
    }

    public function remove(array $keys): void
    {
        $this->collection()->deleteMany([
            '_key' => ['$in' => $keys]
        ]);
    }

    public function store(Documents $documents): void
    {
        $items = $documents->map(function (Document $document) {
            return array_merge($document->data, [
                '_key' => $document->key
            ]);
        });

        if (empty($items)) {
            return;
        }

        $this->collection()->insertMany(array_values($items));
    }

    public function read(FilterCriteria $criteria, StorageContext $context): FilterResult
    {
        $query = [];

        $options = [];

        if ($criteria->page) {
            $options['skip'] = ($criteria->page - 1) * $criteria->limit;
        }

        if ($criteria->limit) {
            $options['limit'] = $criteria->limit;
        }

        if ($criteria->sorting) {
            $options['sort'] = array_map(function ($sort) {
                return [
                    $sort['field'] => $sort['direction'] === 'ASC' ? 1 : -1
                ];
            }, $criteria->sorting);
        }

        if ($criteria->keys) {
            $query['_key'] = ['$in' => $criteria->keys];
        }
        if ($criteria->filters) {
            $filters = $this->parseFilters($criteria->filters);

            $query = array_merge($query, $filters);
        }

        $cursor = $this->collection()->find($query, $options);

        $cursor->setTypeMap([
            'root' => 'array',
            'document' => 'array',
            'array' => 'array',
        ]);

        $result = [];
        foreach ($cursor as $item) {
            $data = $item;

            $key = $data['_key'];
            unset($data['_key'], $data['_id']);

            $result[] = new Document(
                key: $key,
                data: $data
            );
        }

        return new FilterResult($result, null);
    }

    private function collection(): Collection
    {
        return $this->client
            ->selectDatabase($this->database)
            ->selectCollection($this->schema->source);
    }

    private function parseFilters(array $filters): array
    {
        $queries = [];

        foreach ($filters as $filter) {
            $type = $filter['type'];

            $field = $filter['field'];

            $value = SchemaUtil::castValue(
                schema: $this->schema,
                filter: $filter,
                value: $filter['value']
            );

            switch (true) {
                case $type === 'equals':
                    $queries[$field]['$eq'] = $value;
                    break;
                case $type === 'equals-any':
                    $queries[$field]['$in'] = $value;
                    break;
                case $type === 'not':
                    $queries[$field]['$ne'] = $value;
                    break;
                case $type === 'not-any':
                    $queries[$field]['$nin'] = $value;
                    break;
                case $type === 'gt':
                    $queries[$field]['$gt'] = $value;
                    break;
                case $type === 'gte':
                    $queries[$field]['$gte'] = $value;
                    break;
                case $type === 'lt':
                    $queries[$field]['$lt'] = $value;
                    break;
                case $type === 'lte':
                    $queries[$field]['$lte'] = $value;
                    break;
                case $type === 'starts-with':
                    $queries[$field]['$regex'] = '^' . (string) $value;
                    break;
                case $type === 'ends-with':
                    $queries[$field]['$regex'] = (string) $value . '$';
                    break;
                case $type === 'contains':
                    $queries[$field]['$regex'] = (string) $value;
                    break;
                case $type === 'and':
                    $queries[] = $this->parseFilters($value);
                    break;
                case $type === 'or':
                    $queries[] = ['$or' => $this->parseFilters($value)];
                    break;
                case $type === 'nor':
                    $queries[] = ['$nor' => $this->parseFilters($value)];
                    break;
                case $type === 'nand':
                    $queries[] = ['$not' => $this->parseFilters($value)];
                    break;
                default:
                    throw new \RuntimeException(sprintf('Unsupported filter type %s', $type));
            }
        }

        return $queries;
    }
}