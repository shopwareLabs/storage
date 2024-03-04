<?php

namespace Shopware\StorageTests\Common;

use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Schema\Registry;
use Shopware\StorageTests\Common\Schema\Product;

class TestSchema
{
    public static function getCollection(): Collection
    {
        $registry = new Registry();
        $registry->add(Product::class);

        return $registry->get('product');
    }
}
