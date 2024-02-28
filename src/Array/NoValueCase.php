<?php

namespace Shopware\Storage\Array;

class NoValueCase extends \LogicException
{
    public function __construct()
    {
        parent::__construct('No value for this field');
    }
}