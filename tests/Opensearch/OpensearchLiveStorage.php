<?php

namespace Shopware\StorageTests\Opensearch;

use OpenSearch\Client;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Filter\Result;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

class OpensearchLiveStorage implements FilterAware, Storage
{
    public function __construct(
        private readonly Client $client,
        private readonly FilterAware&Storage $decorated,
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

    public function filter(Criteria $criteria, StorageContext $context): Result
    {
        return $this->decorated->filter($criteria, $context);
    }

    public function setup(): void
    {
        $this->decorated->setup();
    }
}
