<?php

namespace Shopware\StorageTests\Opensearch;

use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Opensearch\OpensearchStorage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

/**
 * @covers \Shopware\Storage\Opensearch\OpensearchStorage
 */
class OpensearchStorageFilterTest extends FilterStorageTestBase
{
    use OpensearchTestTrait;

    public function getStorage(): FilterAware&Storage
    {
        return $this->createStorage(
            collection: TestSchema::getCollection(),
        );
    }
}
