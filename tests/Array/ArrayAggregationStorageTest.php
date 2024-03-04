<?php

namespace Shopware\StorageTests\Array;

use Shopware\Storage\Array\ArrayStorage;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Storage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\Array\ArrayStorage
 */
class ArrayAggregationStorageTest extends AggregationStorageTestBase
{
    public function getStorage(): AggregationAware&Storage
    {
        return new ArrayStorage(
            caster: new AggregationCaster(),
            collection: TestSchema::getCollection()
        );
    }
}
