<?php

namespace Shopware\StorageTests\Opensearch;

use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\Opensearch\OpensearchStorage
 */
class OpensearchAggregationTest extends AggregationStorageTestBase
{
    use OpensearchTestTrait;

    public function getStorage(): AggregationAware&Storage
    {
        return self::createStorage(
            collection: TestSchema::getCollection(),
        );
    }
}
