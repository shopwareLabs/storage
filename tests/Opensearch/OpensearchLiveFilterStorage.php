<?php

namespace Shopware\StorageTests\Opensearch;

use OpenSearch\Client;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\StorageContext;

class OpensearchLiveFilterStorage implements FilterStorage
{
    public function __construct(
        private readonly Client $client,
        private readonly FilterStorage $decorated,
        private readonly Schema $schema
    ) {}

    public function remove(array $keys): void
    {
        $this->decorated->remove($keys);
        $this->client->indices()->refresh(['index' => $this->schema->source]);
    }

    public function store(Documents $documents): void
    {
        $this->decorated->store($documents);
        $this->client->indices()->refresh(['index' => $this->schema->source]);
    }

    public function filter(FilterCriteria $criteria, StorageContext $context): FilterResult
    {
        return $this->decorated->filter($criteria, $context);
    }

    public function setup(): void
    {
        $this->decorated->setup();
    }
}
