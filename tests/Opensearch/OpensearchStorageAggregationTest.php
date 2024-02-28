<?php

namespace Shopware\StorageTests\Opensearch;

use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;
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
