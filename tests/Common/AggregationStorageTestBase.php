<?php

namespace Shopware\StorageTests\Common;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\Type\Aggregation;
use Shopware\Storage\Common\Aggregation\Type\Avg;
use Shopware\Storage\Common\Aggregation\Type\Count;
use Shopware\Storage\Common\Aggregation\Type\Distinct;
use Shopware\Storage\Common\Aggregation\Type\Max;
use Shopware\Storage\Common\Aggregation\Type\Min;
use Shopware\Storage\Common\Aggregation\Type\Sum;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Exception\NotSupportedByEngine;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Schema\Translation;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\StorageTests\Common\Schema\Category;
use Shopware\StorageTests\Common\Schema\Product;

abstract class AggregationStorageTestBase extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('stringCases')]
    public function testDebug(
        Aggregation $aggregations,
        Documents $input,
        array $expected
    ): void {
        $this->testStorage($aggregations, $input, $expected);
    }

    abstract public function getStorage(): AggregationAware&Storage;

    public static function stringCases(): \Generator
    {
        yield 'Min, string field' => [
            new Min(name: 'min', field: 'ean'),
            new Documents([
                new Product(key: 'key1', ean: 'a'),
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'c'),
            ]),
            ['min' => 'a'],
        ];
        yield 'Max, string field' => [
            new Max(name: 'max', field: 'ean'),
            new Documents([
                new Product(key: 'key1', ean: 'a'),
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'c'),
            ]),
            ['max' => 'c'],
        ];
        yield 'Distinct, string field' => [
            new Distinct(name: 'distinct', field: 'ean'),
            new Documents([
                new Product(key: 'key1', ean: 'a'),
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'c'),
                new Product(key: 'key4', ean: 'c'),
            ]),
            ['distinct' => ['a', 'b', 'c']],
        ];
        yield 'Count, string field' => [
            new Count(name: 'count', field: 'ean'),
            new Documents([
                new Product(key: 'key1', ean: 'a'),
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'b'),
            ]),
            [
                'count' => [
                    ['key' => 'a', 'count' => 1],
                    ['key' => 'b', 'count' => 2],
                ],
            ],
        ];
    }

    public static function textCases(): \Generator
    {
        yield 'Min, text field' => [
            new Min(name: 'min', field: 'comment'),
            new Documents([
                new Product(key: 'key1', comment: 'a'),
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'c'),
            ]),
            ['min' => 'a'],
        ];
        yield 'Max, text field' => [
            new Max(name: 'max', field: 'comment'),
            new Documents([
                new Product(key: 'key1', comment: 'a'),
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'c'),
            ]),
            ['max' => 'c'],
        ];
        yield 'Distinct, text field' => [
            new Distinct(name: 'distinct', field: 'comment'),
            new Documents([
                new Product(key: 'key1', comment: 'a'),
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'c'),
                new Product(key: 'key4', comment: 'c'),
            ]),
            ['distinct' => ['a', 'b', 'c']],
        ];
        yield 'Count, text field' => [
            new Count(name: 'count', field: 'comment'),
            new Documents([
                new Product(key: 'key1', comment: 'a'),
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'b'),
            ]),
            [
                'count' => [
                    ['key' => 'a', 'count' => 1],
                    ['key' => 'b', 'count' => 2],
                ],
            ],
        ];
    }

    public static function intCases(): \Generator
    {
        yield 'Min, int field' => [
            new Min(name: 'min', field: 'stock'),
            new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            ['min' => 1],
        ];
        yield 'Avg, int field' => [
            new Avg(name: 'avg', field: 'stock'),
            new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            ['avg' => 2],
        ];
        yield 'Max, int field' => [
            new Max(name: 'max', field: 'stock'),
            new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            ['max' => 3],
        ];
        yield 'Sum, int field' => [
            new Sum(name: 'sum', field: 'stock'),
            new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            ['sum' => 6],
        ];
        yield 'Count, int field' => [
            new Count(name: 'count', field: 'stock'),
            new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 2),
                new Product(key: 'key4', stock: 2),
                new Product(key: 'key5', stock: 3),
            ]),
            [
                'count' => [
                    ['key' => 1, 'count' => 1],
                    ['key' => 2, 'count' => 3],
                    ['key' => 3, 'count' => 1],
                ],
            ],
        ];
        yield 'Distinct, int field' => [
            new Distinct(name: 'distinct', field: 'stock'),
            new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
                new Product(key: 'key4', stock: 3),
            ]),
            ['distinct' => [1, 2, 3]],
        ];
    }

    public static function floatCases(): \Generator
    {
        yield 'Avg, float field' => [
            new Avg(name: 'avg', field: 'price'),
            new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            ['avg' => 2.2],
        ];
        yield 'Min, float field' => [
            new Min(name: 'min', field: 'price'),
            new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            ['min' => 1.1],
        ];
        yield 'Max, float field' => [
            new Max(name: 'max', field: 'price'),
            new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            ['max' => 3.3],
        ];
        yield 'Sum, float field' => [
            new Sum(name: 'sum', field: 'price'),
            new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            ['sum' => 6.6],
        ];
        yield 'Count, float field' => [
            new Count(name: 'count', field: 'price'),
            new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 2.2),
                new Product(key: 'key4', price: 2.2),
                new Product(key: 'key5', price: 3.3),
            ]),
            [
                'count' => [
                    ['key' => 1.1, 'count' => 1],
                    ['key' => 2.2, 'count' => 3],
                    ['key' => 3.3, 'count' => 1],
                ],
            ],
        ];
        yield 'Distinct, float field' => [
            new Distinct(name: 'distinct', field: 'price'),
            new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
                new Product(key: 'key4', price: 3.3),
                new Product(key: 'key5', price: 3.31),
            ]),
            ['distinct' => [1.1, 2.2, 3.3, 3.31]],
        ];
    }

    public static function boolCases(): \Generator
    {
        yield 'Min, bool field' => [
            new Min(name: 'min', field: 'active'),
            new Documents([
                new Product(key: 'key1', active: true),
                new Product(key: 'key2', active: false),
                new Product(key: 'key3', active: true),
            ]),
            ['min' => false],
        ];
        yield 'Max, bool field' => [
            new Max(name: 'max', field: 'active'),
            new Documents([
                new Product(key: 'key1', active: true),
                new Product(key: 'key2', active: false),
                new Product(key: 'key3', active: true),
            ]),
            ['max' => true],
        ];
        yield 'Distinct, bool field' => [
            new Distinct(name: 'distinct', field: 'active'),
            new Documents([
                new Product(key: 'key1', active: true),
                new Product(key: 'key2', active: false),
                new Product(key: 'key3', active: true),
                new Product(key: 'key4', active: true),
                new Product(key: 'key5', active: false),
            ]),
            ['distinct' => [false, true]],
        ];
        yield 'Count, bool field' => [
            new Count(name: 'count', field: 'active'),
            new Documents([
                new Product(key: 'key1', active: true),
                new Product(key: 'key2', active: false),
                new Product(key: 'key3', active: false),
                new Product(key: 'key4', active: false),
                new Product(key: 'key5', active: true),
            ]),
            [
                'count' => [
                    ['key' => false, 'count' => 3],
                    ['key' => true, 'count' => 2],
                ],
            ],
        ];
    }

    public static function dateCases(): \Generator
    {
        yield 'Min, date field' => [
            new Min(name: 'min', field: 'changed'),
            new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
            ['min' => '2021-01-01 00:00:00.000'],
        ];
        yield 'Max, date field' => [
            new Max(name: 'max', field: 'changed'),
            new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
            ['max' => '2021-01-03 00:00:00.000'],
        ];
        yield 'Count, date field' => [
            new Count(name: 'count', field: 'changed'),
            new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key4', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key5', changed: '2021-01-03 00:00:00.000'),
            ]),
            [
                'count' => [
                    ['key' => '2021-01-01 00:00:00.000', 'count' => 1],
                    ['key' => '2021-01-02 00:00:00.000', 'count' => 3],
                    ['key' => '2021-01-03 00:00:00.000', 'count' => 1],
                ],
            ],
        ];
        yield 'Distinct, date field' => [
            new Distinct(name: 'distinct', field: 'changed'),
            new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
                new Product(key: 'key4', changed: '2021-01-03 00:00:00.000'),
                new Product(key: 'key5', changed: '2021-01-03 00:00:00.000'),
            ]),
            ['distinct' => ['2021-01-01 00:00:00.000', '2021-01-02 00:00:00.000', '2021-01-03 00:00:00.000']],
        ];
    }

    public static function listStringCases(): \Generator
    {
        yield 'Min, list string field' => [
            new Min(name: 'min', field: 'listField'),
            new Documents([
                new Product('key1', keywords: ['b', 'c']),
                new Product('key2', keywords: ['d', 'e', 'f']),
                new Product('key3', keywords: ['g', 'h', 'i']),
            ]),
            ['min' => 'b'],
        ];
        yield 'Max, list string field' => [
            new Max(name: 'max', field: 'listField'),
            new Documents([
                new Product('key1', keywords: ['b', 'c']),
                new Product('key2', keywords: ['d', 'e', 'i']),
                new Product('key3', keywords: ['g', 'h', 'f']),
            ]),
            ['max' => 'i'],
        ];
        yield 'Distinct, list string field' => [
            new Max(name: 'distinct', field: 'listField'),
            new Documents([
                new Product('key1', keywords: ['b', 'c']),
                new Product('key2', keywords: ['d', 'e', 'i']),
                new Product('key3', keywords: ['g', 'h', 'f']),
            ]),
            ['distinct' => ['b', 'c', 'd', 'e', 'f', 'g', 'h', 'i']],
        ];
    }

    public static function listFloatCases(): \Generator
    {
        yield 'Avg, list float field' => [];
        yield 'Min, list float field' => [];
        yield 'Max, list float field' => [];
        yield 'Sum, list float field' => [];
        yield 'Count, list float field' => [];
        yield 'Distinct, list float field' => [];
    }

    public static function listIntCases(): \Generator
    {
        yield 'Avg, list int field' => [
            new Avg(name: 'avg', field: 'listField'),
            new Documents([
                new Product('key1', keywords: [1, 2, 3]),
                new Product('key2', keywords: [2, 4, 3]),
                new Product('key3', keywords: [2, 5, 5]),
            ]),
            ['avg' => 3],
        ];
        yield 'Min, list int field' => [
            new Min(name: 'min', field: 'listField'),
            new Documents([
                new Product('key1', keywords: [1, 2, 3]),
                new Product('key2', keywords: [2, 4, 3]),
                new Product('key3', keywords: [2, 5, 4]),
            ]),
            ['min' => 1],
        ];
        yield 'Max, list int field' => [
            new Max(name: 'max', field: 'listField'),
            new Documents([
                new Product('key1', keywords: [1, 2, 3]),
                new Product('key2', keywords: [2, 4, 3]),
                new Product('key3', keywords: [2, 5, 4]),
            ]),
            ['max' => 5],
        ];
        yield 'Sum, list int field' => [
            new Sum(name: 'sum', field: 'listField'),
            new Documents([
                new Product('key1', keywords: [1, 2, 3]),
                new Product('key2', keywords: [2, 4, 3]),
                new Product('key3', keywords: [2, 5, 4]),
            ]),
            ['sum' => 26],
        ];
        yield 'Count, list int field' => [
            new Count(name: 'count', field: 'listField'),
            new Documents([
                new Product('key1', keywords: [1, 2, 3]),
                new Product('key2', keywords: [2, 4, 3]),
                new Product('key3', keywords: [2, 5, 3]),
            ]),
            [
                'count' => [
                    ['key' => 1, 'count' => 1],
                    ['key' => 2, 'count' => 3],
                    ['key' => 3, 'count' => 3],
                    ['key' => 4, 'count' => 1],
                    ['key' => 5, 'count' => 1],
                ],
            ],
        ];
        yield 'Distinct, list int field' => [
            new Distinct(name: 'distinct', field: 'listField'),
            new Documents([
                new Product('key1', keywords: [1, 2, 3]),
                new Product('key2', keywords: [2, 4, 3]),
                new Product('key3', keywords: [2, 5, 3]),
            ]),
            ['distinct' => [1, 2, 3, 4, 5]],
        ];
    }

    public static function listDateCases(): \Generator
    {
        yield 'Avg, list date field' => [];
        yield 'Min, list date field' => [];
        yield 'Max, list date field' => [];
        yield 'Sum, list date field' => [];
        yield 'Count, list date field' => [];
        yield 'Distinct, list date field' => [];
    }

    public static function objectStringCases(): \Generator
    {
        yield 'Min, object string field' => [
            new Min(name: 'min', field: 'mainCategory.ean'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'a')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'b')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'c')),
            ]),
            ['min' => 'a'],
        ];
        yield 'Max, object string field' => [
            new Max(name: 'max', field: 'mainCategory.ean'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'a')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'b')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'c')),
            ]),
            ['max' => 'c'],
        ];
        yield 'Count, object string field' => [
            new Count(name: 'count', field: 'mainCategory.ean'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'a')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'b')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'b')),
            ]),
            [
                'count' => [
                    ['key' => 'a', 'count' => 1],
                    ['key' => 'b', 'count' => 2],
                ],
            ],
        ];
        yield 'Distinct, object string field' => [
            new Distinct(name: 'distinct', field: 'mainCategory.ean'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'a')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'b')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'c')),
                new Product(key: 'key4', mainCategory: new Category(ean: 'c')),
            ]),
            ['distinct' => ['a', 'b', 'c']],
        ];
    }

    public static function objectFloatCases(): \Generator
    {
        yield 'Avg, object float field' => [
            new Avg(name: 'avg', field: 'mainCategory.price'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            ['avg' => 2.2],
        ];
        yield 'Min, object float field' => [
            new Min(name: 'min', field: 'mainCategory.price'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            ['min' => 1.1],
        ];
        yield 'Max, object float field' => [
            new Max(name: 'max', field: 'mainCategory.price'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            ['max' => 3.3],
        ];
        yield 'Sum, object float field' => [
            new Sum(name: 'sum', field: 'mainCategory.price'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            ['sum' => 6.6],
        ];
        yield 'Count, object float field' => [
            new Count(name: 'count', field: 'mainCategory.price'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key4', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key5', mainCategory: new Category(price: 3.3)),
            ]),
            [
                'count' => [
                    ['key' => 1.1, 'count' => 1],
                    ['key' => 2.2, 'count' => 3],
                    ['key' => 3.3, 'count' => 1],
                ],
            ],
        ];
        yield 'Distinct, object float field' => [
            new Distinct(name: 'distinct', field: 'mainCategory.price'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
                new Product(key: 'key4', mainCategory: new Category(price: 3.3)),
            ]),
            ['distinct' => [1.1, 2.2, 3.3]],
        ];
    }

    public static function objectIntCases(): \Generator
    {
        yield 'Avg, object int field' => [
            new Avg(name: 'avg', field: 'mainCategory.stock'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            ['avg' => 2],
        ];
        yield 'Min, object int field' => [
            new Min(name: 'min', field: 'mainCategory.stock'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            ['min' => 1],
        ];
        yield 'Max, object int field' => [
            new Max(name: 'max', field: 'mainCategory.stock'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            ['max' => 3],
        ];
        yield 'Sum, object int field' => [
            new Sum(name: 'sum', field: 'mainCategory.stock'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            ['sum' => 6],
        ];
        yield 'Count, object int field' => [
            new Count(name: 'count', field: 'mainCategory.stock'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 2)),
                new Product(key: 'key4', mainCategory: new Category(stock: 2)),
                new Product(key: 'key5', mainCategory: new Category(stock: 3)),
            ]),
            [
                'count' => [
                    ['key' => 1, 'count' => 1],
                    ['key' => 2, 'count' => 3],
                    ['key' => 3, 'count' => 1],
                ],
            ],
        ];
        yield 'Distinct, object int field' => [
            new Distinct(name: 'distinct', field: 'mainCategory.stock'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
                new Product(key: 'key4', mainCategory: new Category(stock: 3)),
            ]),
            ['distinct' => [1, 2, 3]],
        ];
    }

    public static function objectBoolCases(): \Generator
    {
        yield 'Min, object bool field' => [
            new Min(name: 'min', field: 'mainCategory.active'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(active: true)),
                new Product(key: 'key2', mainCategory: new Category(active: false)),
                new Product(key: 'key3', mainCategory: new Category(active: true)),
            ]),
            ['min' => false],
        ];
        yield 'Max, object bool field' => [
            new Max(name: 'max', field: 'mainCategory.active'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(active: true)),
                new Product(key: 'key2', mainCategory: new Category(active: false)),
                new Product(key: 'key3', mainCategory: new Category(active: true)),
            ]),
            ['max' => true],
        ];
        yield 'Count, object bool field' => [
            new Count(name: 'count', field: 'mainCategory.active'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(active: true)),
                new Product(key: 'key2', mainCategory: new Category(active: false)),
                new Product(key: 'key3', mainCategory: new Category(active: false)),
                new Product(key: 'key4', mainCategory: new Category(active: false)),
                new Product(key: 'key5', mainCategory: new Category(active: true)),
            ]),
            [
                'count' => [
                    ['key' => false, 'count' => 3],
                    ['key' => true, 'count' => 2],
                ],
            ],
        ];
        yield 'Distinct, object bool field' => [
            new Distinct(name: 'distinct', field: 'mainCategory.active'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(active: true)),
                new Product(key: 'key2', mainCategory: new Category(active: false)),
                new Product(key: 'key3', mainCategory: new Category(active: true)),
                new Product(key: 'key4', mainCategory: new Category(active: true)),
                new Product(key: 'key5', mainCategory: new Category(active: false)),
            ]),
            ['distinct' => [false, true]],
        ];
    }

    public static function objectDateCases(): \Generator
    {
        yield 'Min, object date field' => [
            new Min(name: 'min', field: 'mainCategory.changed'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            ['min' => '2021-01-01 00:00:00.000'],
        ];
        yield 'Max, object date field' => [
            new Max(name: 'max', field: 'mainCategory.changed'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            ['max' => '2021-01-03 00:00:00.000'],
        ];

        yield 'Count, object date field' => [
            new Count(name: 'count', field: 'mainCategory.changed'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key4', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key5', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            [
                'count' => [
                    ['key' => '2021-01-01 00:00:00.000', 'count' => 1],
                    ['key' => '2021-01-02 00:00:00.000', 'count' => 3],
                    ['key' => '2021-01-03 00:00:00.000', 'count' => 1],
                ],
            ],
        ];
        yield 'Distinct, object date field' => [
            new Distinct(name: 'distinct', field: 'mainCategory.changed'),
            new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
                new Product(key: 'key4', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
                new Product(key: 'key5', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            ['distinct' => ['2021-01-01 00:00:00.000', '2021-01-02 00:00:00.000', '2021-01-03 00:00:00.000']],
        ];
    }

    public static function objectListStringCases(): \Generator
    {
        yield 'Min, object list string field' => [
            new Min(name: 'min', field: 'categories.ean'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(ean: 'c'), new Category(ean: 'b')]),
                new Product(key: 'key2', categories: [new Category(ean: 'a'), new Category(ean: 'd')]),
                new Product(key: 'key3', categories: [new Category(ean: 'e'), new Category(ean: 'f')]),
            ]),
            ['min' => 'a'],
        ];
        yield 'Max, object list string field' => [
            new Max(name: 'max', field: 'categories.ean'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(ean: 'c'), new Category(ean: 'b')]),
                new Product(key: 'key2', categories: [new Category(ean: 'a'), new Category(ean: 'd')]),
                new Product(key: 'key3', categories: [new Category(ean: 'e'), new Category(ean: 'f')]),
            ]),
            ['max' => 'f'],
        ];
        yield 'Count, object list string field' => [
            new Count(name: 'count', field: 'categories.ean'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(ean: 'a'), new Category(ean: 'b')]),
                new Product(key: 'key2', categories: [new Category(ean: 'a'), new Category(ean: 'c')]),
                new Product(key: 'key3', categories: [new Category(ean: 'c'), new Category(ean: 'd')]),
            ]),
            [
                'count' => [
                    ['key' => 'a', 'count' => 2],
                    ['key' => 'b', 'count' => 1],
                    ['key' => 'c', 'count' => 2],
                    ['key' => 'd', 'count' => 1],
                ],
            ],
        ];
        yield 'Distinct, object list string field' => [
            new Distinct(name: 'distinct', field: 'categories.ean'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(ean: 'a'), new Category(ean: 'b')]),
                new Product(key: 'key2', categories: [new Category(ean: 'a'), new Category(ean: 'c')]),
                new Product(key: 'key3', categories: [new Category(ean: 'c'), new Category(ean: 'd')]),
            ]),
            ['distinct' => ['a', 'b', 'c', 'd']],
        ];
    }

    public static function objectListFloatCases(): \Generator
    {
        yield 'Avg, object list float field' => [
            new Avg(name: 'avg', field: 'categories.price'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(price: 1.1), new Category(price: 2.2)]),
                new Product(key: 'key2', categories: [new Category(price: 3.3), new Category(price: 4.4)]),
                new Product(key: 'key3', categories: [new Category(price: 5.5), new Category(price: 6.6)]),
            ]),
            ['avg' => 3.85],
        ];
        yield 'Min, object list float field' => [
            new Min(name: 'min', field: 'categories.price'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(price: 1.1), new Category(price: 2.2)]),
                new Product(key: 'key2', categories: [new Category(price: 3.3), new Category(price: 4.4)]),
                new Product(key: 'key3', categories: [new Category(price: 5.5), new Category(price: 6.6)]),
            ]),
            ['min' => 1.1],
        ];
        yield 'Max, object list float field' => [
            new Max(name: 'max', field: 'categories.price'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(price: 1.1), new Category(price: 2.2)]),
                new Product(key: 'key2', categories: [new Category(price: 3.3), new Category(price: 4.4)]),
                new Product(key: 'key3', categories: [new Category(price: 5.5), new Category(price: 6.6)]),
            ]),
            ['max' => 6.6],
        ];
        yield 'Sum, object list float field' => [
            new Sum(name: 'sum', field: 'categories.price'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(price: 1.1), new Category(price: 2.2)]),
                new Product(key: 'key2', categories: [new Category(price: 3.3), new Category(price: 4.4)]),
                new Product(key: 'key3', categories: [new Category(price: 5.5), new Category(price: 6.6)]),
            ]),
            ['sum' => 23.1],
        ];
        yield 'Count, object list float field' => [
            new Count(name: 'count', field: 'categories.price'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(price: 1.1), new Category(price: 2.2)]),
                new Product(key: 'key2', categories: [new Category(price: 3.3), new Category(price: 4.4)]),
                new Product(key: 'key3', categories: [new Category(price: 5.5), new Category(price: 6.6)]),
            ]),
            [
                'count' => [
                    ['key' => 1.1, 'count' => 1],
                    ['key' => 2.2, 'count' => 1],
                    ['key' => 3.3, 'count' => 1],
                    ['key' => 4.4, 'count' => 1],
                    ['key' => 5.5, 'count' => 1],
                    ['key' => 6.6, 'count' => 1],
                ],
            ],
        ];
        yield 'Distinct, object list float field' => [
            new Distinct(name: 'distinct', field: 'categories.price'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(price: 1.1), new Category(price: 2.2)]),
                new Product(key: 'key2', categories: [new Category(price: 3.3), new Category(price: 4.4)]),
                new Product(key: 'key3', categories: [new Category(price: 5.5), new Category(price: 6.6)]),
            ]),
            ['distinct' => [1.1, 2.2, 3.3, 4.4, 5.5, 6.6]],
        ];
    }

    public static function objectListIntCases(): \Generator
    {
        yield 'Avg, object list int field' => [
            new Avg(name: 'avg', field: 'categories.stock'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(stock: 1), new Category(stock: 2)]),
                new Product(key: 'key2', categories: [new Category(stock: 3), new Category(stock: 4)]),
                new Product(key: 'key3', categories: [new Category(stock: 5), new Category(stock: 6)]),
            ]),
            ['avg' => 3.5],
        ];
        yield 'Min, object list int field' => [
            new Min(name: 'min', field: 'categories.stock'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(stock: 1), new Category(stock: 2)]),
                new Product(key: 'key2', categories: [new Category(stock: 3), new Category(stock: 4)]),
                new Product(key: 'key3', categories: [new Category(stock: 5), new Category(stock: 6)]),
            ]),
            ['min' => 1],
        ];
        yield 'Max, object list int field' => [
            new Max(name: 'max', field: 'categories.stock'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(stock: 1), new Category(stock: 2)]),
                new Product(key: 'key2', categories: [new Category(stock: 3), new Category(stock: 4)]),
                new Product(key: 'key3', categories: [new Category(stock: 5), new Category(stock: 6)]),
            ]),
            ['max' => 6],
        ];
        yield 'Sum, object list int field' => [
            new Sum(name: 'sum', field: 'categories.stock'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(stock: 1), new Category(stock: 2)]),
                new Product(key: 'key2', categories: [new Category(stock: 3), new Category(stock: 4)]),
                new Product(key: 'key3', categories: [new Category(stock: 5), new Category(stock: 6)]),
            ]),
            ['sum' => 21],
        ];
        yield 'Count, object list int field' => [
            new Count(name: 'count', field: 'categories.stock'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(stock: 2), new Category(stock: 3)]),
                new Product(key: 'key2', categories: [new Category(stock: 2), new Category(stock: 4)]),
                new Product(key: 'key3', categories: [new Category(stock: 4), new Category(stock: 2)]),
            ]),
            [
                'count' => [
                    ['key' => 2, 'count' => 3],
                    ['key' => 3, 'count' => 1],
                    ['key' => 4, 'count' => 2],
                ],
            ],
        ];
        yield 'Distinct, object list int field' => [
            new Distinct(name: 'distinct', field: 'categories.stock'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(stock: 1), new Category(stock: 1)]),
                new Product(key: 'key2', categories: [new Category(stock: 3), new Category(stock: 2)]),
                new Product(key: 'key3', categories: [new Category(stock: 2), new Category(stock: 6)]),
            ]),
            ['distinct' => [1, 2, 3, 6]],
        ];
    }

    public static function objectListBoolCases(): \Generator
    {
        yield 'Min, object list bool field' => [
            new Min(name: 'min', field: 'categories.active'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(active: true), new Category(active: false)]),
                new Product(key: 'key2', categories: [new Category(active: false), new Category(active: true)]),
                new Product(key: 'key3', categories: [new Category(active: true), new Category(active: true)]),
            ]),
            ['min' => false],
        ];
        yield 'Max, object list bool field' => [
            new Max(name: 'max', field: 'categories.active'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(active: true), new Category(active: false)]),
                new Product(key: 'key2', categories: [new Category(active: false), new Category(active: true)]),
                new Product(key: 'key3', categories: [new Category(active: true), new Category(active: true)]),
            ]),
            ['max' => true],
        ];
        yield 'Count, object list bool field' => [
            new Count(name: 'count', field: 'categories.active'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(active: true), new Category(active: false)]),
                new Product(key: 'key2', categories: [new Category(active: false)]),
                new Product(key: 'key3', categories: [new Category(active: true)]),
            ]),
            [
                'count' => [
                    ['key' => false, 'count' => 2],
                    ['key' => true, 'count' => 2],
                ],
            ],
        ];
        yield 'Distinct, object list bool field' => [
            new Distinct(name: 'distinct', field: 'categories.active'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(active: true), new Category(active: false)]),
                new Product(key: 'key2', categories: [new Category(active: false), new Category(active: true)]),
                new Product(key: 'key3', categories: [new Category(active: true), new Category(active: true)]),
            ]),
            ['distinct' => [false, true]],
        ];
    }

    public static function objectListDateCases(): \Generator
    {
        yield 'Min, object list date field' => [
            new Min(name: 'min', field: 'categories.changed'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(changed: '2021-01-01 00:00:00.000'), new Category(changed: '2021-01-02 00:00:00.000')]),
                new Product(key: 'key2', categories: [new Category(changed: '2021-01-03 00:00:00.000'), new Category(changed: '2021-01-04 00:00:00.000')]),
                new Product(key: 'key3', categories: [new Category(changed: '2021-01-05 00:00:00.000'), new Category(changed: '2021-01-06 00:00:00.000')]),
            ]),
            ['min' => '2021-01-01 00:00:00.000'],
        ];
        yield 'Max, object list date field' => [
            new Max(name: 'max', field: 'categories.changed'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(changed: '2021-01-01 00:00:00.000'), new Category(changed: '2021-01-02 00:00:00.000')]),
                new Product(key: 'key2', categories: [new Category(changed: '2021-01-03 00:00:00.000'), new Category(changed: '2021-01-04 00:00:00.000')]),
                new Product(key: 'key3', categories: [new Category(changed: '2021-01-05 00:00:00.000'), new Category(changed: '2021-01-06 00:00:00.000')]),
            ]),
            ['max' => '2021-01-06 00:00:00.000'],
        ];
        yield 'Count, object list date field' => [
            new Count(name: 'count', field: 'categories.changed'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(changed: '2021-01-01 00:00:00.000'), new Category(changed: '2021-01-03 00:00:00.000')]),
                new Product(key: 'key2', categories: [new Category(changed: '2021-01-03 00:00:00.000'), new Category(changed: '2021-01-02 00:00:00.000')]),
                new Product(key: 'key3', categories: [new Category(changed: '2021-01-01 00:00:00.000'), new Category(changed: '2021-01-03 00:00:00.000')]),
            ]),
            [
                'count' => [
                    ['key' => '2021-01-01 00:00:00.000', 'count' => 2],
                    ['key' => '2021-01-02 00:00:00.000', 'count' => 1],
                    ['key' => '2021-01-03 00:00:00.000', 'count' => 3],
                ],
            ],
        ];
        yield 'Distinct, object list date field' => [
            new Distinct(name: 'distinct', field: 'categories.changed'),
            new Documents([
                new Product(key: 'key1', categories: [new Category(changed: '2021-01-01 00:00:00.000'), new Category(changed: '2021-01-03 00:00:00.000')]),
                new Product(key: 'key2', categories: [new Category(changed: '2021-01-03 00:00:00.000'), new Category(changed: '2021-01-02 00:00:00.000')]),
                new Product(key: 'key3', categories: [new Category(changed: '2021-01-01 00:00:00.000'), new Category(changed: '2021-01-03 00:00:00.000')]),
            ]),
            ['distinct' => ['2021-01-01 00:00:00.000', '2021-01-02 00:00:00.000', '2021-01-03 00:00:00.000']],
        ];
    }

    public static function translatedStringCases(): \Generator
    {
        yield 'Min, translated string field' => [
            new Min(name: 'min', field: 'name'),
            new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'b', 'de' => 'b'])),
                new Product(key: 'key2', name: new Translation(['en' => null, 'de' => 'd'])),
                new Product(key: 'key3', name: new Translation(['de' => 'a'])),
            ]),
            ['min' => 'a'],
        ];
        yield 'Max, translated string field' => [
            new Max(name: 'max', field: 'name'),
            new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'b', 'de' => 'b'])),
                new Product(key: 'key2', name: new Translation(['en' => null, 'de' => 'd'])),
                new Product(key: 'key3', name: new Translation(['de' => 'a'])),
            ]),
            ['max' => 'd'],
        ];
        yield 'Count, translated string field' => [
            new Count(name: 'count', field: 'name'),
            new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'a'])),
                new Product(key: 'key2', name: new Translation(['en' => null, 'de' => 'a'])),
                new Product(key: 'key3', name: new Translation(['en' => 'b', 'de' => 'a'])),
                new Product(key: 'key4', name: new Translation(['de' => 'c'])),
            ]),
            [
                'count' => [
                    ['key' => 'a', 'count' => 2],
                    ['key' => 'b', 'count' => 1],
                    ['key' => 'c', 'count' => 1],
                ],
            ],
        ];
        yield 'Distinct, translated string field' => [
            new Distinct(name: 'distinct', field: 'name'),
            new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'a'])),
                new Product(key: 'key2', name: new Translation(['en' => null, 'de' => 'a'])),
                new Product(key: 'key3', name: new Translation(['en' => 'b', 'de' => 'a'])),
                new Product(key: 'key4', name: new Translation(['de' => 'c'])),
            ]),
            ['distinct' => ['a', 'b', 'c']],
        ];
    }

    public static function translatedIntCases(): \Generator
    {
        yield 'Avg, translated int field' => [
            new Avg(name: 'avg', field: 'position'),
            new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => null, 'de' => 3])),
                new Product(key: 'key3', position: new Translation(['de' => 8])),
            ]),
            ['avg' => 4],
        ];
        yield 'Min, translated int field' => [
            new Min(name: 'min', field: 'position'),
            new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => null, 'de' => 3])),
                new Product(key: 'key3', position: new Translation(['de' => 5])),
            ]),
            ['min' => 1],
        ];
        yield 'Max, translated int field' => [
            new Max(name: 'max', field: 'position'),
            new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => null, 'de' => 3])),
                new Product(key: 'key3', position: new Translation(['de' => 5])),
            ]),
            ['max' => 5],
        ];
        yield 'Sum, translated int field' => [
            new Sum(name: 'sum', field: 'position'),
            new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => null, 'de' => 3])),
                new Product(key: 'key3', position: new Translation(['de' => 5])),
            ]),
            ['sum' => 9],
        ];
        yield 'Count, translated int field' => [
            new Count(name: 'count', field: 'position'),
            new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => null, 'de' => 1])),
                new Product(key: 'key3', position: new Translation(['de' => 5])),
                new Product(key: 'key4', position: new Translation(['en' => 5])),
            ]),
            [
                'count' => [
                    ['key' => 1, 'count' => 2],
                    ['key' => 5, 'count' => 2],
                ],
            ],
        ];
        yield 'Distinct, translated int field' => [
            new Distinct(name: 'distinct', field: 'position'),
            new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => null, 'de' => 3])),
                new Product(key: 'key3', position: new Translation(['de' => 5])),
            ]),
            ['distinct' => [1, 3, 5]],
        ];
    }

    public static function translatedFloatCases(): \Generator
    {
        yield 'Avg, translated float field' => [
            new Avg(name: 'avg', field: 'weight'),
            new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => null, 'de' => 3.3])),
                new Product(key: 'key3', weight: new Translation(['de' => 8.8])),
            ]),
            ['avg' => 4.4],
        ];
        yield 'Min, translated float field' => [
            new Min(name: 'min', field: 'weight'),
            new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => null, 'de' => 3.3])),
                new Product(key: 'key3', weight: new Translation(['de' => 5.5])),
            ]),
            ['min' => 1.1],
        ];
        yield 'Max, translated float field' => [
            new Max(name: 'max', field: 'weight'),
            new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => null, 'de' => 3.3])),
                new Product(key: 'key3', weight: new Translation(['de' => 5.5])),
            ]),
            ['max' => 5.5],
        ];
        yield 'Sum, translated float field' => [
            new Sum(name: 'sum', field: 'weight'),
            new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => null, 'de' => 3.3])),
                new Product(key: 'key3', weight: new Translation(['de' => 5.5])),
            ]),
            ['sum' => 9.9],
        ];
        yield 'Count, translated float field' => [
            new Count(name: 'count', field: 'weight'),
            new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => null, 'de' => 1.1])),
                new Product(key: 'key3', weight: new Translation(['de' => 5.5])),
                new Product(key: 'key4', weight: new Translation(['en' => 5.5])),
            ]),
            [
                'count' => [
                    ['key' => 1.1, 'count' => 2],
                    ['key' => 5.5, 'count' => 2],
                ],
            ],
        ];
        yield 'Distinct, translated float field' => [
            new Distinct(name: 'distinct', field: 'weight'),
            new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => null, 'de' => 3.3])),
                new Product(key: 'key3', weight: new Translation(['de' => 5.5])),
            ]),
            ['distinct' => [1.1, 3.3, 5.5]],
        ];
    }

    public static function translatedBoolCases(): \Generator
    {
        yield 'Min, translated bool field' => [
            new Min(name: 'min', field: 'highlight'),
            new Documents([
                new Product(key: 'key1', highlight: new Translation(['en' => true, 'de' => false])),
                new Product(key: 'key2', highlight: new Translation(['en' => null, 'de' => true])),
                new Product(key: 'key3', highlight: new Translation(['de' => false])),
            ]),
            ['min' => false],
        ];
        yield 'Max, translated bool field' => [
            new Max(name: 'max', field: 'highlight'),
            new Documents([
                new Product(key: 'key1', highlight: new Translation(['en' => true, 'de' => false])),
                new Product(key: 'key2', highlight: new Translation(['en' => null, 'de' => true])),
                new Product(key: 'key3', highlight: new Translation(['de' => false])),
            ]),
            ['max' => true],
        ];
        yield 'Count, translated bool field' => [
            new Count(name: 'count', field: 'highlight'),
            new Documents([
                new Product(key: 'key1', highlight: new Translation(['en' => true])),
                new Product(key: 'key2', highlight: new Translation(['en' => null, 'de' => true])),
                new Product(key: 'key3', highlight: new Translation(['en' => false, 'de' => true])),
                new Product(key: 'key4', highlight: new Translation(['de' => false])),
            ]),
            [
                'count' => [
                    ['key' => false, 'count' => 2],
                    ['key' => true, 'count' => 2],
                ],
            ],
        ];
        yield 'Distinct, translated bool field' => [
            new Distinct(name: 'distinct', field: 'highlight'),
            new Documents([
                new Product(key: 'key1', highlight: new Translation(['en' => true])),
                new Product(key: 'key2', highlight: new Translation(['en' => null, 'de' => true])),
                new Product(key: 'key3', highlight: new Translation(['en' => false, 'de' => true])),
                new Product(key: 'key4', highlight: new Translation(['de' => false])),
            ]),
            ['distinct' => [false, true]],
        ];
    }

    public static function translatedDateCases(): \Generator
    {
        yield 'Min, translated date field' => [
            new Min(name: 'min', field: 'release'),
            new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => null, 'de' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['de' => '2021-01-04 00:00:00.000'])),
            ]),
            ['min' => '2021-01-01 00:00:00.000'],
        ];
        yield 'Max, translated date field' => [
            new Max(name: 'max', field: 'release'),
            new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => null, 'de' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['de' => '2021-01-04 00:00:00.000'])),
            ]),
            ['max' => '2021-01-04 00:00:00.000'],
        ];
        yield 'Count, translated date field' => [
            new Count(name: 'count', field: 'release'),
            new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => null, 'de' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['de' => '2021-01-04 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['en' => '2021-01-04 00:00:00.000'])),
            ]),
            [
                'count' => [
                    ['key' => '2021-01-01 00:00:00.000', 'count' => 1],
                    ['key' => '2021-01-03 00:00:00.000', 'count' => 1],
                    ['key' => '2021-01-04 00:00:00.000', 'count' => 2],
                ],
            ],
        ];
        yield 'Distinct, translated date field' => [
            new Distinct(name: 'distinct', field: 'release'),
            new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => null, 'de' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['de' => '2021-01-04 00:00:00.000'])),
            ]),
            ['distinct' => ['2021-01-01 00:00:00.000', '2021-01-03 00:00:00.000', '2021-01-04 00:00:00.000']],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('stringCases')]
    #[DataProvider('textCases')]
    #[DataProvider('intCases')]
    #[DataProvider('floatCases')]
    #[DataProvider('boolCases')]
    #[DataProvider('dateCases')]
    //    #[DataProvider('listStringCases')]
    //    #[DataProvider('listFloatCases')]
    //    #[DataProvider('listIntCases')]
    //    #[DataProvider('listDateCases')]
    #[DataProvider('objectStringCases')]
    #[DataProvider('objectFloatCases')]
    #[DataProvider('objectIntCases')]
    #[DataProvider('objectBoolCases')]
    #[DataProvider('objectDateCases')]
    #[DataProvider('objectListStringCases')]
    #[DataProvider('objectListFloatCases')]
    #[DataProvider('objectListIntCases')]
    #[DataProvider('objectListBoolCases')]
    #[DataProvider('objectListDateCases')]
    #[DataProvider('translatedStringCases')]
    #[DataProvider('translatedIntCases')]
    #[DataProvider('translatedFloatCases')]
    #[DataProvider('translatedBoolCases')]
    #[DataProvider('translatedDateCases')]
    public function testStorage(
        Aggregation $aggregations,
        Documents $input,
        array $expected
    ): void {
        $storage = $this->getStorage();

        $storage->store($input);

        try {
            $context = new StorageContext(languages: ['en', 'de']);

            $loaded = $storage->aggregate(
                aggregations: [$aggregations],
                criteria: new Criteria(),
                context: $context
            );
        } catch (NotSupportedByEngine $e) {
            static::markTestIncomplete($e->getMessage());
        }

        static::assertEquals($expected, $loaded);
    }
}
