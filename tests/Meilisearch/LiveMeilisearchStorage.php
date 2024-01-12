<?php

namespace Shopware\StorageTests\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

class LiveMeilisearchStorage implements Storage, FilterStorage
{
    public function __construct(
        private readonly FilterStorage $storage,
        private readonly Client $client
    ) {}

    public function filter(FilterCriteria $criteria, StorageContext $context): FilterResult
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
