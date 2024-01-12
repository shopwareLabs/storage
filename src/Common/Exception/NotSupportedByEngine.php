<?php

namespace Shopware\Storage\Common\Exception;

class NotSupportedByEngine extends \Exception
{
    public function __construct(string $issue, string $message)
    {
        parent::__construct(
            $message . "\n" .
            'Issue: https://github.com/shopwareLabs/storage/issues/' . $issue . "\n"
        );
    }
}
