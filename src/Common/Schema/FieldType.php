<?php

namespace Shopware\Storage\Common\Schema;

enum FieldType: string
{
    public const STRING = 'string';
    public const TEXT = 'text';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const BOOL = 'bool';
    public const DATETIME = 'datetime';
    public const LIST = 'list';
    public const OBJECT = 'object';
    public const OBJECT_LIST = 'object_list';
}
