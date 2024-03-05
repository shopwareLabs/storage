<?php

namespace Shopware\StorageTests\Opensearch;

use OpenSearch\Client;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Filter\Result;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

class OpensearchLiveStorage implements FilterAware, Storage, AggregationAware
{
    public function __construct(
        private readonly Client $client,
        private readonly FilterAware&AggregationAware&Storage $decorated,
        private readonly \Shopware\Storage\Common\Schema\Collection $collection
    ) {}

    public function destroy(): void
    {
        $this->decorated->destroy();
        $this->wait();
    }

    public function clear(): void
    {
        $this->decorated->clear();
        $this->wait();
    }

    public function mget(array $keys, StorageContext $context): Documents
    {
        return $this->decorated->mget($keys, $context);
    }

    public function get(string $key, StorageContext $context): ?Document
    {
        return $this->decorated->get($key, $context);
    }

    public function remove(array $keys): void
    {
        $this->decorated->remove($keys);
        $this->wait();
    }

    public function store(Documents $documents): void
    {
        $this->decorated->store($documents);
        $this->wait();
    }

    public function filter(Criteria $criteria, StorageContext $context): Result
    {
        return $this->decorated->filter($criteria, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function aggregate(array $aggregations, Criteria $criteria, StorageContext $context): array
    {
        return $this->decorated->aggregate($aggregations, $criteria, $context);
    }

    public function setup(): void
    {
        $this->decorated->setup();
    }

    private function wait(): void
    {
        if (!$this->exists()) {
            return;
        }
        $this->client->indices()->refresh(['index' => $this->collection->name]);
    }

    private function exists(): bool
    {
        return $this->client->indices()->exists(['index' => $this->collection->name]);
    }
}
