<?php

namespace Shopware\StorageTests\Opensearch;

use OpenSearch\Client;
use Shopware\Storage\Common\Aggregation\AggregationAware;
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

    public function remove(array $keys): void
    {
        $this->decorated->remove($keys);

        $this->client->indices()->refresh(['index' => $this->collection->name]);
    }

    public function store(Documents $documents): void
    {
        $this->decorated->store($documents);

        $this->client->indices()->refresh(['index' => $this->collection->name]);
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
}
