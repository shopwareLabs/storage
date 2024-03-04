<?php

namespace Shopware\Storage\Common\Aggregation;

use Shopware\Storage\Common\Aggregation\Type\Aggregation;
use Shopware\Storage\Common\Exception\NotSupportedByEngine;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\StorageContext;

interface AggregationAware
{
    /**
     * @param Aggregation[] $aggregations
     * @return array<string, mixed>
     * @throws NotSupportedByEngine
     */
    public function aggregate(
        array $aggregations,
        Criteria $criteria,
        StorageContext $context
    ): array;
}
