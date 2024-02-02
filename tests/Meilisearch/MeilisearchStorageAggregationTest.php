<?php

namespace Shopware\StorageTests\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Meilisearch\MeilisearchStorage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;

class MeilisearchStorageAggregationTest extends AggregationStorageTestBase
{
    private ?Client $client = null;

    private function exists(): bool
    {
        try {
            $this->getClient()->getIndex($this->getSchema()->source);
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

        $this->getClient()->deleteIndex($this->getSchema()->source);

        $this->wait();

        $this->getClient()->createIndex(
            uid: $this->getSchema()->source,
            options: ['primaryKey' => 'key']
        );

        $fields = array_map(fn($field) => $field->name, $this->getSchema()->fields);

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

    public function getStorage(): AggregationAware&Storage
    {
        return new MeilisearchLiveStorage(
            storage: new MeilisearchStorage(
                caster: new AggregationCaster(),
                client: $this->getClient(),
                schema: $this->getSchema()
            ),
            client: $this->getClient(),
        );
    }

    private function index(): Indexes
    {
        return $this->getClient()->index($this->getSchema()->source);
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
