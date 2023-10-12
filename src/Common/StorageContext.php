<?php

namespace Shopware\Storage\Common;

class StorageContext
{
    /**
     * @param string[] $languages
     */
    public function __construct(public array $languages = ['default'])
    {
    }
}
