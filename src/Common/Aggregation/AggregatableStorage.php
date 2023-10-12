<?php

namespace Shopware\Storage\Common\Aggregation;

use Shopware\Storage\Common\Storage;

interface AggregatableStorage extends Storage
{
    public function aggregate(AggregationCriteria $criteria): Aggregations;
}
