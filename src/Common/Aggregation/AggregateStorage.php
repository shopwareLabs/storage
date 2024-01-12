<?php

namespace Shopware\Storage\Common\Aggregation;

use Shopware\Storage\Common\Storage;

interface AggregateStorage extends Storage
{
    public function aggregate(AggregationCriteria $criteria): Aggregations;
}
