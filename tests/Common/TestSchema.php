<?php

namespace Shopware\StorageTests\Common;

use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\StorageTests\Common\Schema\Product;

class TestSchema
{
    public static function getCollection(): Collection
    {
        $schema = new Schema();
        $schema->add(Product::class);

        return $schema->get('product');
    }
}
