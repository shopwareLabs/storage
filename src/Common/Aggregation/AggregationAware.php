<?php

namespace Shopware\Storage\Common\Aggregation;

use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\StorageContext;

interface AggregationAware
{
    /**
     * @param Aggregation[] $aggregations
     * @param Criteria $criteria
     * @param StorageContext $context
     * @return Aggregations
     */
    public function aggregate(
        array $aggregations,
        Criteria $criteria,
        StorageContext $context
    ): Aggregations;
}
