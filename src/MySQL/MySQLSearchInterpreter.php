<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Search\Search;
use Shopware\Storage\Common\StorageContext;

abstract class MySQLSearchInterpreter
{
    abstract public function interpret(
        Collection $collection,
        QueryBuilder $builder,
        Search $search,
        StorageContext $context
    ): void;
}
