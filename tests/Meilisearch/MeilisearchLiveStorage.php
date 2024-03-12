<?php

namespace Shopware\StorageTests\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Filter\Result;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Search\Search;
use Shopware\Storage\Common\Search\SearchAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Meilisearch\MeilisearchStorage;

class MeilisearchLiveStorage implements Storage, FilterAware, AggregationAware, SearchAware
{
    public function __construct(
        private readonly MeilisearchStorage $storage,
        private readonly Client $client
    ) {}

    public function setup(): void
    {
        $this->storage->setup();
    }

    public function destroy(): void
    {
        $this->storage->destroy();
        $this->wait();
    }

    public function clear(): void
    {
        $this->storage->clear();
        $this->wait();
    }

    public function search(Search $search, Criteria $criteria, StorageContext $context): Result
    {
        return $this->storage->search($search, $criteria, $context);
    }

    public function mget(array $keys, StorageContext $context): Documents
    {
        return $this->storage->mget($keys, $context);
    }

    public function get(string $key, StorageContext $context): ?Document
    {
        return $this->storage->get($key, $context);
    }

    public function aggregate(array $aggregations, Criteria $criteria, StorageContext $context): array
    {
        return $this->storage->aggregate($aggregations, $criteria, $context);
    }

    public function filter(Criteria $criteria, StorageContext $context): Result
    {
        return $this->storage->filter($criteria, $context);
    }

    public function remove(array $keys): void
    {
        $this->storage->remove($keys);

        $this->wait();
    }

    public function store(Documents $documents): void
    {
        $this->storage->store($documents);

        $this->wait();
    }

    private function wait(): void
    {
        $tasks = new TasksQuery();
        $tasks->setStatuses(['enqueued', 'processing']);

        $tasks = $this->client->getTasks($tasks);

        $ids = array_map(fn($task) => $task['uid'], $tasks->getResults());

        if (count($ids) === 0) {
            return;
        }

        $this->client->waitForTasks($ids);
    }
}
