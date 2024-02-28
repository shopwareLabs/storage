<?php

namespace Shopware\StorageTests\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Meilisearch\MeilisearchStorage;
use Shopware\StorageTests\Common\TestSchema;

trait MeilisearchTestTrait
{
    private ?Client $client = null;

    private function exists(): bool
    {
        try {
            $this->getClient()->getIndex(TestSchema::getCollection()->name);
        } catch (ApiException) {
            return false;
        }

        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->exists()) {
            $this->index()->deleteAllDocuments();

            $this->wait();

            return;
        }

        $this->getClient()->deleteIndex(TestSchema::getCollection()->name);

        $this->wait();

        $this->getClient()->createIndex(
            uid: TestSchema::getCollection()->name,
            options: ['primaryKey' => 'key']
        );

        $fields = array_map(fn($field) => $field->name, TestSchema::getCollection()->fields());

        $fields[] = 'key';

        $fields = array_values(array_filter($fields));

        $this->index()
            ->updateFilterableAttributes($fields);

        $this->index()
            ->updateSortableAttributes($fields);

        $this->wait();
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client(
                url: 'http://localhost:7700',
                apiKey: 'UTbXxcv5T5Hq-nCYAjgPJ5lsBxf7PdhgiNexmoTByJk'
            );
        }

        return $this->client;
    }

    public function createStorage(): MeilisearchLiveStorage
    {
        return new MeilisearchLiveStorage(
            storage: new MeilisearchStorage(
                caster: new AggregationCaster(),
                hydrator: new Hydrator(),
                client: $this->getClient(),
                collection: TestSchema::getCollection()
            ),
            client: $this->getClient(),
        );
    }

    private function index(): Indexes
    {
        return $this->getClient()->index(TestSchema::getCollection()->name);
    }

    private function wait(): void
    {
        $tasks = new TasksQuery();
        $tasks->setStatuses(['enqueued', 'processing']);

        $tasks = $this->getClient()->getTasks($tasks);

        $ids = array_map(fn($task) => $task['uid'], $tasks->getResults());

        if (count($ids) === 0) {
            return;
        }

        $this->getClient()->waitForTasks($ids);
    }
}
