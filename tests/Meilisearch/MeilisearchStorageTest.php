<?php

namespace Shopware\StorageTests\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Exception\NotSupportedByEngine;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Filter\Result;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Filter\Paging\Page;
use Shopware\Storage\Common\Filter\Type\Any;
use Shopware\Storage\Common\Filter\Type\Contains;
use Shopware\Storage\Common\Filter\Type\Equals;
use Shopware\Storage\Common\Filter\Type\Gt;
use Shopware\Storage\Common\Filter\Type\Gte;
use Shopware\Storage\Common\Filter\Type\Lt;
use Shopware\Storage\Common\Filter\Type\Lte;
use Shopware\Storage\Common\Filter\Type\Neither;
use Shopware\Storage\Common\Filter\Type\Not;
use Shopware\Storage\Common\Filter\Type\Prefix;
use Shopware\Storage\Common\Filter\Type\Suffix;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Meilisearch\MeilisearchStorage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

class MeilisearchStorageTest extends FilterStorageTestBase
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

    public function getStorage(): FilterAware&Storage
    {
        return new LiveMeilisearchAware(
            storage: new MeilisearchStorage(
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
