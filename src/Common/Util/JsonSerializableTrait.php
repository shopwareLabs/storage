<?php

namespace Shopware\Storage\Common\Util;

trait JsonSerializableTrait
{
    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}