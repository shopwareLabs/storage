<?php

namespace Shopware\Storage\Common\Filter;

use Shopware\Storage\Common\Document\Documents;

class Result extends Documents
{
    public function __construct(array $elements, public ?int $total = null)
    {
        parent::__construct($elements);
    }
}
