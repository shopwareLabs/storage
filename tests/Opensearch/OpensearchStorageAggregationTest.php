<?php

namespace Shopware\StorageTests\Opensearch;

use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Opensearch\OpensearchStorage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;
use Shopware\StorageTests\Common\FilterStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\Opensearch\OpensearchStorage
 */
class OpensearchStorageAggregationTest extends AggregationStorageTestBase
{
    use OpensearchTestTrait;

    public function getStorage(): AggregationAware&Storage
    {
        return $this->createStorage(
            collection: TestSchema::getCollection(),
        );
    }
}
