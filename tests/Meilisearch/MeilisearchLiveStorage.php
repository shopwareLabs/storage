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
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

class MeilisearchLiveStorage implements Storage, FilterAware, AggregationAware
{
    public function __construct(
        private readonly FilterAware&AggregationAware&Storage $storage,
        private readonly Client $client
    ) {}

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

    public function setup(): void {}

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
