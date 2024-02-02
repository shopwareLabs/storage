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
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

abstract class AggregationStorageTestBase extends TestCase
{
    use SchemaStorageTrait;

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('translatedDateCases')]
    #[DataProvider('dateCases')]
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
            new Min(name: 'min', field: 'stringField'),
            new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c')
            ]),
            ['min' => 'a']
        ];
        yield 'Max, string field' => [
            new Max(name: 'max', field: 'stringField'),
            new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c')
            ]),
            ['max' => 'c']
        ];
        yield 'Distinct, string field' => [
            new Distinct(name: 'distinct', field: 'stringField'),
            new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
                self::document(key: 'key4', stringField: 'c')
            ]),
            ['distinct' => ['a', 'b', 'c']]
        ];
        yield 'Count, string field' => [
            new Count(name: 'count', field: 'stringField'),
            new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'b'),
            ]),
            [
                'count' => [
                    ['key' => 'a', 'count' => 1],
                    ['key' => 'b', 'count' => 2]
                ]
            ]
        ];
    }

    public static function textCases(): \Generator
    {
        yield 'Min, text field' => [
            new Min(name: 'min', field: 'textField'),
            new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c')
            ]),
            ['min' => 'a']
        ];
        yield 'Max, text field' => [
            new Max(name: 'max', field: 'textField'),
            new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c')
            ]),
            ['max' => 'c']
        ];
        yield 'Distinct, text field' => [
            new Distinct(name: 'distinct', field: 'textField'),
            new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
                self::document(key: 'key4', textField: 'c')
            ]),
            ['distinct' => ['a', 'b', 'c']]
        ];
        yield 'Count, text field' => [
            new Count(name: 'count', field: 'textField'),
            new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'b'),
            ]),
            [
                'count' => [
                    ['key' => 'a', 'count' => 1],
                    ['key' => 'b', 'count' => 2]
                ]
            ]
        ];
    }

    public static function intCases(): \Generator
    {
        yield 'Min, int field' => [
            new Min(name: 'min', field: 'intField'),
            new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            ['min' => 1],
        ];
        yield 'Avg, int field' => [
            new Avg(name: 'avg', field: 'intField'),
            new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            ['avg' => 2],
        ];
        yield 'Max, int field' => [
            new Max(name: 'max', field: 'intField'),
            new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            ['max' => 3],
        ];
        yield 'Sum, int field' => [
            new Sum(name: 'sum', field: 'intField'),
            new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            ['sum' => 6],
        ];
        yield 'Count, int field' => [
            new Count(name: 'count', field: 'intField'),
            new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 2),
                self::document(key: 'key4', intField: 2),
                self::document(key: 'key5', intField: 3),
            ]),
            [
                'count' => [
                    ['key' => 1, 'count' => 1],
                    ['key' => 2, 'count' => 3],
                    ['key' => 3, 'count' => 1]
                ]
            ],
        ];
        yield 'Distinct, int field' => [
            new Distinct(name: 'distinct', field: 'intField'),
            new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
                self::document(key: 'key4', intField: 3),
            ]),
            ['distinct' => [1, 2, 3]],
        ];
    }

    public static function floatCases(): \Generator
    {
        yield 'Avg, float field' => [
            new Avg(name: 'avg', field: 'floatField'),
            new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            ['avg' => 2.2],
        ];
        yield 'Min, float field' => [
            new Min(name: 'min', field: 'floatField'),
            new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            ['min' => 1.1],
        ];
        yield 'Max, float field' => [
            new Max(name: 'max', field: 'floatField'),
            new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            ['max' => 3.3],
        ];
        yield 'Sum, float field' => [
            new Sum(name: 'sum', field: 'floatField'),
            new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            ['sum' => 6.6],
        ];
        yield 'Count, float field' => [
            new Count(name: 'count', field: 'floatField'),
            new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 2.2),
                self::document(key: 'key4', floatField: 2.2),
                self::document(key: 'key5', floatField: 3.3),
            ]),
            [
                'count' => [
                    ['key' => 1.1, 'count' => 1],
                    ['key' => 2.2, 'count' => 3],
                    ['key' => 3.3, 'count' => 1]
                ]
            ],
        ];
        yield 'Distinct, float field' => [
            new Distinct(name: 'distinct', field: 'floatField'),
            new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
                self::document(key: 'key4', floatField: 3.3),
                self::document(key: 'key5', floatField: 3.31),
            ]),
            ['distinct' => [1.1, 2.2, 3.3, 3.31]],
        ];
    }

    public static function boolCases(): \Generator
    {
        yield 'Min, bool field' => [
            new Min(name: 'min', field: 'boolField'),
            new Documents([
                self::document(key: 'key1', boolField: true),
                self::document(key: 'key2', boolField: false),
                self::document(key: 'key3', boolField: true),
            ]),
            ['min' => false],
        ];
        yield 'Max, bool field' => [
            new Max(name: 'max', field: 'boolField'),
            new Documents([
                self::document(key: 'key1', boolField: true),
                self::document(key: 'key2', boolField: false),
                self::document(key: 'key3', boolField: true),
            ]),
            ['max' => true],
        ];
        yield 'Distinct, bool field' => [
            new Distinct(name: 'distinct', field: 'boolField'),
            new Documents([
                self::document(key: 'key1', boolField: true),
                self::document(key: 'key2', boolField: false),
                self::document(key: 'key3', boolField: true),
                self::document(key: 'key4', boolField: true),
                self::document(key: 'key5', boolField: false),
            ]),
            ['distinct' => [false, true]],
        ];
        yield 'Count, bool field' => [
            new Count(name: 'count', field: 'boolField'),
            new Documents([
                self::document(key: 'key1', boolField: true),
                self::document(key: 'key2', boolField: false),
                self::document(key: 'key3', boolField: false),
                self::document(key: 'key4', boolField: false),
                self::document(key: 'key5', boolField: true),
            ]),
            [
                'count' => [
                    ['key' => false, 'count' => 3],
                    ['key' => true, 'count' => 2],
                ]
            ],
        ];
    }

    public static function dateCases(): \Generator
    {
        yield 'Min, date field' => [
            new Min(name: 'min', field: 'dateField'),
            new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            ['min' => '2021-01-01 00:00:00.000'],
        ];
        yield 'Max, date field' => [
            new Max(name: 'max', field: 'dateField'),
            new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            ['max' => '2021-01-03 00:00:00.000'],
        ];
        yield 'Count, date field' => [
            new Count(name: 'count', field: 'dateField'),
            new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key4', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key5', dateField: '2021-01-03 00:00:00.000'),
            ]),
            [
                'count' => [
                    ['key' => '2021-01-01 00:00:00.000', 'count' => 1],
                    ['key' => '2021-01-02 00:00:00.000', 'count' => 3],
                    ['key' => '2021-01-03 00:00:00.000', 'count' => 1]
                ]
            ],
        ];
        yield 'Distinct, date field' => [
            new Distinct(name: 'distinct', field: 'dateField'),
            new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
                self::document(key: 'key4', dateField: '2021-01-03 00:00:00.000'),
                self::document(key: 'key5', dateField: '2021-01-03 00:00:00.000'),
            ]),
            ['distinct' => ['2021-01-01 00:00:00.000', '2021-01-02 00:00:00.000', '2021-01-03 00:00:00.000']],
        ];
    }

    public static function listStringCases(): \Generator
    {
        yield 'Min, list string field' => [
            new Min(name: 'min', field: 'listField'),
            new Documents([
                self::document('key1', listField: ['b', 'c']),
                self::document('key2', listField: ['d', 'e', 'f']),
                self::document('key3', listField: ['g', 'h', 'i']),
            ]),
            ['min' => 'b'],
        ];
        yield 'Max, list string field' => [
            new Max(name: 'max', field: 'listField'),
            new Documents([
                self::document('key1', listField: ['b', 'c']),
                self::document('key2', listField: ['d', 'e', 'i']),
                self::document('key3', listField: ['g', 'h', 'f']),
            ]),
            ['max' => 'i'],
        ];
        yield 'Distinct, list string field' => [
            new Max(name: 'distinct', field: 'listField'),
            new Documents([
                self::document('key1', listField: ['b', 'c']),
                self::document('key2', listField: ['d', 'e', 'i']),
                self::document('key3', listField: ['g', 'h', 'f']),
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
                self::document('key1', listField: [1, 2, 3]),
                self::document('key2', listField: [2, 4, 3]),
                self::document('key3', listField: [2, 5, 5]),
            ]),
            ['avg' => 3],
        ];
        yield 'Min, list int field' => [
            new Min(name: 'min', field: 'listField'),
            new Documents([
                self::document('key1', listField: [1, 2, 3]),
                self::document('key2', listField: [2, 4, 3]),
                self::document('key3', listField: [2, 5, 4]),
            ]),
            ['min' => 1],
        ];
        yield 'Max, list int field' => [
            new Max(name: 'max', field: 'listField'),
            new Documents([
                self::document('key1', listField: [1, 2, 3]),
                self::document('key2', listField: [2, 4, 3]),
                self::document('key3', listField: [2, 5, 4]),
            ]),
            ['max' => 5],
        ];
        yield 'Sum, list int field' => [
            new Sum(name: 'sum', field: 'listField'),
            new Documents([
                self::document('key1', listField: [1, 2, 3]),
                self::document('key2', listField: [2, 4, 3]),
                self::document('key3', listField: [2, 5, 4]),
            ]),
            ['sum' => 26],
        ];
        yield 'Count, list int field' => [
            new Count(name: 'count', field: 'listField'),
            new Documents([
                self::document('key1', listField: [1, 2, 3]),
                self::document('key2', listField: [2, 4, 3]),
                self::document('key3', listField: [2, 5, 3]),
            ]),
            [
                'count' => [
                    ['key' => 1, 'count' => 1],
                    ['key' => 2, 'count' => 3],
                    ['key' => 3, 'count' => 3],
                    ['key' => 4, 'count' => 1],
                    ['key' => 5, 'count' => 1],
                ]
            ],
        ];
        yield 'Distinct, list int field' => [
            new Distinct(name: 'distinct', field: 'listField'),
            new Documents([
                self::document('key1', listField: [1, 2, 3]),
                self::document('key2', listField: [2, 4, 3]),
                self::document('key3', listField: [2, 5, 3]),
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
            new Min(name: 'min', field: 'objectField.stringField'),
            new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'a']),
                self::document(key: 'key2', objectField: ['stringField' => 'b']),
                self::document(key: 'key3', objectField: ['stringField' => 'c'])
            ]),
            ['min' => 'a']
        ];
        yield 'Max, object string field' => [
            new Max(name: 'max', field: 'objectField.stringField'),
            new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'a']),
                self::document(key: 'key2', objectField: ['stringField' => 'b']),
                self::document(key: 'key3', objectField: ['stringField' => 'c'])
            ]),
            ['max' => 'c']
        ];
        yield 'Count, object string field' => [
            new Count(name: 'count', field: 'objectField.stringField'),
            new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'a']),
                self::document(key: 'key2', objectField: ['stringField' => 'b']),
                self::document(key: 'key3', objectField: ['stringField' => 'b']),
            ]),
            [
                'count' => [
                    ['key' => 'a', 'count' => 1],
                    ['key' => 'b', 'count' => 2]
                ]
            ]
        ];
        yield 'Distinct, object string field' => [
            new Distinct(name: 'distinct', field: 'objectField.stringField'),
            new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'a']),
                self::document(key: 'key2', objectField: ['stringField' => 'b']),
                self::document(key: 'key3', objectField: ['stringField' => 'c']),
                self::document(key: 'key4', objectField: ['stringField' => 'c'])
            ]),
            ['distinct' => ['a', 'b', 'c']]
        ];
    }

    public static function objectFloatCases(): \Generator
    {
        yield 'Avg, object float field' => [
            new Avg(name: 'avg', field: 'objectField.floatField'),
            new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            ['avg' => 2.2],
        ];
        yield 'Min, object float field' => [
            new Min(name: 'min', field: 'objectField.floatField'),
            new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            ['min' => 1.1],
        ];
        yield 'Max, object float field' => [
            new Max(name: 'max', field: 'objectField.floatField'),
            new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            ['max' => 3.3],
        ];
        yield 'Sum, object float field' => [
            new Sum(name: 'sum', field: 'objectField.floatField'),
            new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            ['sum' => 6.6],
        ];
        yield 'Count, object float field' => [
            new Count(name: 'count', field: 'objectField.floatField'),
            new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 2.2]),
                self::document(key: 'key4', objectField: ['floatField' => 2.2]),
                self::document(key: 'key5', objectField: ['floatField' => 3.3]),
            ]),
            [
                'count' => [
                    ['key' => 1.1, 'count' => 1],
                    ['key' => 2.2, 'count' => 3],
                    ['key' => 3.3, 'count' => 1]
                ]
            ],
        ];
        yield 'Distinct, object float field' => [
            new Distinct(name: 'distinct', field: 'objectField.floatField'),
            new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
                self::document(key: 'key4', objectField: ['floatField' => 3.3]),
            ]),
            ['distinct' => [1.1, 2.2, 3.3]],
        ];
    }

    public static function objectIntCases(): \Generator
    {
        yield 'Avg, object int field' => [
            new Avg(name: 'avg', field: 'objectField.intField'),
            new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            ['avg' => 2],
        ];
        yield 'Min, object int field' => [
            new Min(name: 'min', field: 'objectField.intField'),
            new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            ['min' => 1],
        ];
        yield 'Max, object int field' => [
            new Max(name: 'max', field: 'objectField.intField'),
            new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            ['max' => 3],
        ];
        yield 'Sum, object int field' => [
            new Sum(name: 'sum', field: 'objectField.intField'),
            new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            ['sum' => 6],
        ];
        yield 'Count, object int field' => [
            new Count(name: 'count', field: 'objectField.intField'),
            new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 2]),
                self::document(key: 'key4', objectField: ['intField' => 2]),
                self::document(key: 'key5', objectField: ['intField' => 3]),
            ]),
            [
                'count' => [
                    ['key' => 1, 'count' => 1],
                    ['key' => 2, 'count' => 3],
                    ['key' => 3, 'count' => 1]
                ]
            ],
        ];
        yield 'Distinct, object int field' => [
            new Distinct(name: 'distinct', field: 'objectField.intField'),
            new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
                self::document(key: 'key4', objectField: ['intField' => 3]),
            ]),
            ['distinct' => [1, 2, 3]],
        ];
    }

    public static function objectBoolCases(): \Generator
    {
        yield 'Min, object bool field' => [
            new Min(name: 'min', field: 'objectField.boolField'),
            new Documents([
                self::document(key: 'key1', objectField: ['boolField' => true]),
                self::document(key: 'key2', objectField: ['boolField' => false]),
                self::document(key: 'key3', objectField: ['boolField' => true]),
            ]),
            ['min' => false],
        ];
        yield 'Max, object bool field' => [
            new Max(name: 'max', field: 'objectField.boolField'),
            new Documents([
                self::document(key: 'key1', objectField: ['boolField' => true]),
                self::document(key: 'key2', objectField: ['boolField' => false]),
                self::document(key: 'key3', objectField: ['boolField' => true]),
            ]),
            ['max' => true],
        ];
        yield 'Count, object bool field' => [
            new Count(name: 'count', field: 'objectField.boolField'),
            new Documents([
                self::document(key: 'key1', objectField: ['boolField' => true]),
                self::document(key: 'key2', objectField: ['boolField' => false]),
                self::document(key: 'key3', objectField: ['boolField' => false]),
                self::document(key: 'key4', objectField: ['boolField' => false]),
                self::document(key: 'key5', objectField: ['boolField' => true]),
            ]),
            [
                'count' => [
                    ['key' => false, 'count' => 3],
                    ['key' => true, 'count' => 2],
                ]
            ],
        ];
        yield 'Distinct, object bool field' => [
            new Distinct(name: 'distinct', field: 'objectField.boolField'),
            new Documents([
                self::document(key: 'key1', objectField: ['boolField' => true]),
                self::document(key: 'key2', objectField: ['boolField' => false]),
                self::document(key: 'key3', objectField: ['boolField' => true]),
                self::document(key: 'key4', objectField: ['boolField' => true]),
                self::document(key: 'key5', objectField: ['boolField' => false]),
            ]),
            ['distinct' => [false, true]],
        ];
    }

    public static function objectDateCases(): \Generator
    {
        yield 'Min, object date field' => [
            new Min(name: 'min', field: 'objectField.dateField'),
            new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            ['min' => '2021-01-01 00:00:00.000'],
        ];
        yield 'Max, object date field' => [
            new Max(name: 'max', field: 'objectField.dateField'),
            new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            ['max' => '2021-01-03 00:00:00.000'],
        ];

        yield 'Count, object date field' => [
            new Count(name: 'count', field: 'objectField.dateField'),
            new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key5', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            [
                'count' => [
                    ['key' => '2021-01-01 00:00:00.000', 'count' => 1],
                    ['key' => '2021-01-02 00:00:00.000', 'count' => 3],
                    ['key' => '2021-01-03 00:00:00.000', 'count' => 1]
                ]
            ],
        ];
        yield 'Distinct, object date field' => [
            new Distinct(name: 'distinct', field: 'objectField.dateField'),
            new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key4', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key5', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            ['distinct' => ['2021-01-01 00:00:00.000', '2021-01-02 00:00:00.000', '2021-01-03 00:00:00.000']],
        ];
    }

    public static function objectListStringCases(): \Generator
    {
        yield 'Min, object list string field' => [
            new Min(name: 'min', field: 'objectListField.stringField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['stringField' => 'c'], ['stringField' => 'b']]),
                self::document(key: 'key2', objectListField: [['stringField' => 'a'], ['stringField' => 'd']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'e'], ['stringField' => 'f']]),
            ]),
            ['min' => 'a']
        ];
        yield 'Max, object list string field' => [
            new Max(name: 'max', field: 'objectListField.stringField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['stringField' => 'c'], ['stringField' => 'b']]),
                self::document(key: 'key2', objectListField: [['stringField' => 'a'], ['stringField' => 'd']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'e'], ['stringField' => 'f']]),
            ]),
            ['max' => 'f']
        ];
        yield 'Count, object list string field' => [
            new Count(name: 'count', field: 'objectListField.stringField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['stringField' => 'a'], ['stringField' => 'b']]),
                self::document(key: 'key2', objectListField: [['stringField' => 'a'], ['stringField' => 'c']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'c'], ['stringField' => 'd']]),
            ]),
            [
                'count' => [
                    ['key' => 'a', 'count' => 2],
                    ['key' => 'b', 'count' => 1],
                    ['key' => 'c', 'count' => 2],
                    ['key' => 'd', 'count' => 1]
                ]
            ]
        ];
        yield 'Distinct, object list string field' => [
            new Distinct(name: 'distinct', field: 'objectListField.stringField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['stringField' => 'a'], ['stringField' => 'b']]),
                self::document(key: 'key2', objectListField: [['stringField' => 'a'], ['stringField' => 'c']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'c'], ['stringField' => 'd']]),
            ]),
            ['distinct' => ['a', 'b', 'c', 'd']]
        ];
    }

    public static function objectListFloatCases(): \Generator
    {
        yield 'Avg, object list float field' => [
            new Avg(name: 'avg', field: 'objectListField.floatField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 3.3], ['floatField' => 4.4]]),
                self::document(key: 'key3', objectListField: [['floatField' => 5.5], ['floatField' => 6.6]]),
            ]),
            ['avg' => 3.85],
        ];
        yield 'Min, object list float field' => [
            new Min(name: 'min', field: 'objectListField.floatField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 3.3], ['floatField' => 4.4]]),
                self::document(key: 'key3', objectListField: [['floatField' => 5.5], ['floatField' => 6.6]]),
            ]),
            ['min' => 1.1],
        ];
        yield 'Max, object list float field' => [
            new Max(name: 'max', field: 'objectListField.floatField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 3.3], ['floatField' => 4.4]]),
                self::document(key: 'key3', objectListField: [['floatField' => 5.5], ['floatField' => 6.6]]),
            ]),
            ['max' => 6.6],
        ];
        yield 'Sum, object list float field' => [
            new Sum(name: 'sum', field: 'objectListField.floatField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 3.3], ['floatField' => 4.4]]),
                self::document(key: 'key3', objectListField: [['floatField' => 5.5], ['floatField' => 6.6]]),
            ]),
            ['sum' => 23.1],
        ];
        yield 'Count, object list float field' => [
            new Count(name: 'count', field: 'objectListField.floatField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 3.3], ['floatField' => 4.4]]),
                self::document(key: 'key3', objectListField: [['floatField' => 5.5], ['floatField' => 6.6]]),
            ]),
            [
                'count' => [
                    ['key' => 1.1, 'count' => 1],
                    ['key' => 2.2, 'count' => 1],
                    ['key' => 3.3, 'count' => 1],
                    ['key' => 4.4, 'count' => 1],
                    ['key' => 5.5, 'count' => 1],
                    ['key' => 6.6, 'count' => 1],
                ]
            ],
        ];
        yield 'Distinct, object list float field' => [
            new Distinct(name: 'distinct', field: 'objectListField.floatField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 3.3], ['floatField' => 4.4]]),
                self::document(key: 'key3', objectListField: [['floatField' => 5.5], ['floatField' => 6.6]]),
            ]),
            ['distinct' => [1.1, 2.2, 3.3, 4.4, 5.5, 6.6]],
        ];
    }

    public static function objectListIntCases(): \Generator
    {
        yield 'Avg, object list int field' => [
            new Avg(name: 'avg', field: 'objectListField.intField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 3], ['intField' => 4]]),
                self::document(key: 'key3', objectListField: [['intField' => 5], ['intField' => 6]]),
            ]),
            ['avg' => 3.5],
        ];
        yield 'Min, object list int field' => [
            new Min(name: 'min', field: 'objectListField.intField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 3], ['intField' => 4]]),
                self::document(key: 'key3', objectListField: [['intField' => 5], ['intField' => 6]]),
            ]),
            ['min' => 1],
        ];
        yield 'Max, object list int field' => [
            new Max(name: 'max', field: 'objectListField.intField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 3], ['intField' => 4]]),
                self::document(key: 'key3', objectListField: [['intField' => 5], ['intField' => 6]]),
            ]),
            ['max' => 6],
        ];
        yield 'Sum, object list int field' => [
            new Sum(name: 'sum', field: 'objectListField.intField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 3], ['intField' => 4]]),
                self::document(key: 'key3', objectListField: [['intField' => 5], ['intField' => 6]]),
            ]),
            ['sum' => 21],
        ];
        yield 'Count, object list int field' => [
            new Count(name: 'count', field: 'objectListField.intField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 2], ['intField' => 3]]),
                self::document(key: 'key2', objectListField: [['intField' => 2], ['intField' => 4]]),
                self::document(key: 'key3', objectListField: [['intField' => 4], ['intField' => 2]]),
            ]),
            [
                'count' => [
                    ['key' => 2, 'count' => 3],
                    ['key' => 3, 'count' => 1],
                    ['key' => 4, 'count' => 2],
                ]
            ],
        ];
        yield 'Distinct, object list int field' => [
            new Distinct(name: 'distinct', field: 'objectListField.intField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 1]]),
                self::document(key: 'key2', objectListField: [['intField' => 3], ['intField' => 2]]),
                self::document(key: 'key3', objectListField: [['intField' => 2], ['intField' => 6]]),
            ]),
            ['distinct' => [1, 2, 3, 6]],
        ];
    }

    public static function objectListBoolCases(): \Generator
    {
        yield 'Min, object list bool field' => [
            new Min(name: 'min', field: 'objectListField.boolField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['boolField' => true], ['boolField' => false]]),
                self::document(key: 'key2', objectListField: [['boolField' => false], ['boolField' => true]]),
                self::document(key: 'key3', objectListField: [['boolField' => true], ['boolField' => true]]),
            ]),
            ['min' => false],
        ];
        yield 'Max, object list bool field' => [
            new Max(name: 'max', field: 'objectListField.boolField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['boolField' => true], ['boolField' => false]]),
                self::document(key: 'key2', objectListField: [['boolField' => false], ['boolField' => true]]),
                self::document(key: 'key3', objectListField: [['boolField' => true], ['boolField' => true]]),
            ]),
            ['max' => true],
        ];
        yield 'Count, object list bool field' => [
            new Count(name: 'count', field: 'objectListField.boolField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['boolField' => true], ['boolField' => false]]),
                self::document(key: 'key2', objectListField: [['boolField' => false]]),
                self::document(key: 'key3', objectListField: [['boolField' => true]]),
            ]),
            [
                'count' => [
                    ['key' => false, 'count' => 2],
                    ['key' => true, 'count' => 2],
                ]
            ],
        ];
        yield 'Distinct, object list bool field' => [
            new Distinct(name: 'distinct', field: 'objectListField.boolField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['boolField' => true], ['boolField' => false]]),
                self::document(key: 'key2', objectListField: [['boolField' => false], ['boolField' => true]]),
                self::document(key: 'key3', objectListField: [['boolField' => true], ['boolField' => true]]),
            ]),
            ['distinct' => [false, true]],
        ];
    }

    public static function objectListDateCases(): \Generator
    {
        yield 'Min, object list date field' => [
            new Min(name: 'min', field: 'objectListField.dateField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-03 00:00:00.000'], ['dateField' => '2021-01-04 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-05 00:00:00.000'], ['dateField' => '2021-01-06 00:00:00.000']]),
            ]),
            ['min' => '2021-01-01 00:00:00.000'],
        ];
        yield 'Max, object list date field' => [
            new Max(name: 'max', field: 'objectListField.dateField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-03 00:00:00.000'], ['dateField' => '2021-01-04 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-05 00:00:00.000'], ['dateField' => '2021-01-06 00:00:00.000']]),
            ]),
            ['max' => '2021-01-06 00:00:00.000'],
        ];
        yield 'Count, object list date field' => [
            new Count(name: 'count', field: 'objectListField.dateField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-03 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-03 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-03 00:00:00.000']]),
            ]),
            [
                'count' => [
                    ['key' => '2021-01-01 00:00:00.000', 'count' => 2],
                    ['key' => '2021-01-02 00:00:00.000', 'count' => 1],
                    ['key' => '2021-01-03 00:00:00.000', 'count' => 3],
                ]
            ],
        ];
        yield 'Distinct, object list date field' => [
            new Distinct(name: 'distinct', field: 'objectListField.dateField'),
            new Documents([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-03 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-03 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-03 00:00:00.000']]),
            ]),
            ['distinct' => ['2021-01-01 00:00:00.000', '2021-01-02 00:00:00.000', '2021-01-03 00:00:00.000']],
        ];
    }

    public static function translatedStringCases(): \Generator
    {
        yield 'Min, translated string field' => [
            new Min(name: 'min', field: 'translatedString'),
            new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'b', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => null, 'de' => 'd']),
                self::document(key: 'key3', translatedString: ['de' => 'a'])
            ]),
            ['min' => 'a']
        ];
        yield 'Max, translated string field' => [
            new Max(name: 'max', field: 'translatedString'),
            new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'b', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => null, 'de' => 'd']),
                self::document(key: 'key3', translatedString: ['de' => 'a'])
            ]),
            ['max' => 'd']
        ];
        yield 'Count, translated string field' => [
            new Count(name: 'count', field: 'translatedString'),
            new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a']),
                self::document(key: 'key2', translatedString: ['en' => null, 'de' => 'a']),
                self::document(key: 'key3', translatedString: ['en' => 'b', 'de' => 'a']),
                self::document(key: 'key4', translatedString: ['de' => 'c']),
            ]),
            [
                'count' => [
                    ['key' => 'a', 'count' => 2],
                    ['key' => 'b', 'count' => 1],
                    ['key' => 'c', 'count' => 1],
                ]
            ]
        ];
        yield 'Distinct, translated string field' => [
            new Distinct(name: 'distinct', field: 'translatedString'),
            new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a']),
                self::document(key: 'key2', translatedString: ['en' => null, 'de' => 'a']),
                self::document(key: 'key3', translatedString: ['en' => 'b', 'de' => 'a']),
                self::document(key: 'key4', translatedString: ['de' => 'c']),
            ]),
            ['distinct' => ['a', 'b', 'c']]
        ];
    }

    public static function translatedIntCases(): \Generator
    {
        yield 'Avg, translated int field' => [
            new Avg(name: 'avg', field: 'translatedInt'),
            new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key3', translatedInt: ['de' => 8]),
            ]),
            ['avg' => 4],
        ];
        yield 'Min, translated int field' => [
            new Min(name: 'min', field: 'translatedInt'),
            new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key3', translatedInt: ['de' => 5]),
            ]),
            ['min' => 1],
        ];
        yield 'Max, translated int field' => [
            new Max(name: 'max', field: 'translatedInt'),
            new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key3', translatedInt: ['de' => 5]),
            ]),
            ['max' => 5],
        ];
        yield 'Sum, translated int field' => [
            new Sum(name: 'sum', field: 'translatedInt'),
            new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key3', translatedInt: ['de' => 5]),
            ]),
            ['sum' => 9],
        ];
        yield 'Count, translated int field' => [
            new Count(name: 'count', field: 'translatedInt'),
            new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => null, 'de' => 1]),
                self::document(key: 'key3', translatedInt: ['de' => 5]),
                self::document(key: 'key4', translatedInt: ['en' => 5]),
            ]),
            [
                'count' => [
                    ['key' => 1, 'count' => 2],
                    ['key' => 5, 'count' => 2],
                ]
            ],
        ];
        yield 'Distinct, translated int field' => [
            new Distinct(name: 'distinct', field: 'translatedInt'),
            new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key3', translatedInt: ['de' => 5]),
            ]),
            ['distinct' => [1, 3, 5]]
        ];
    }

    public static function translatedFloatCases(): \Generator
    {
        yield 'Avg, translated float field' => [
            new Avg(name: 'avg', field: 'translatedFloat'),
            new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['de' => 8.8]),
            ]),
            ['avg' => 4.4],
        ];
        yield 'Min, translated float field' => [
            new Min(name: 'min', field: 'translatedFloat'),
            new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['de' => 5.5]),
            ]),
            ['min' => 1.1],
        ];
        yield 'Max, translated float field' => [
            new Max(name: 'max', field: 'translatedFloat'),
            new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['de' => 5.5]),
            ]),
            ['max' => 5.5],
        ];
        yield 'Sum, translated float field' => [
            new Sum(name: 'sum', field: 'translatedFloat'),
            new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['de' => 5.5]),
            ]),
            ['sum' => 9.9],
        ];
        yield 'Count, translated float field' => [
            new Count(name: 'count', field: 'translatedFloat'),
            new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => null, 'de' => 1.1]),
                self::document(key: 'key3', translatedFloat: ['de' => 5.5]),
                self::document(key: 'key4', translatedFloat: ['en' => 5.5]),
            ]),
            [
                'count' => [
                    ['key' => 1.1, 'count' => 2],
                    ['key' => 5.5, 'count' => 2],
                ]
            ],
        ];
        yield 'Distinct, translated float field' => [
            new Distinct(name: 'distinct', field: 'translatedFloat'),
            new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['de' => 5.5]),
            ]),
            ['distinct' => [1.1, 3.3, 5.5]]
        ];
    }

    public static function translatedBoolCases(): \Generator
    {
        yield 'Min, translated bool field' => [
            new Min(name: 'min', field: 'translatedBool'),
            new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => null, 'de' => true]),
                self::document(key: 'key3', translatedBool: ['de' => false])
            ]),
            ['min' => false]
        ];
        yield 'Max, translated bool field' => [
            new Max(name: 'max', field: 'translatedBool'),
            new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => null, 'de' => true]),
                self::document(key: 'key3', translatedBool: ['de' => false])
            ]),
            ['max' => true]
        ];
        yield 'Count, translated bool field' => [
            new Count(name: 'count', field: 'translatedBool'),
            new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true]),
                self::document(key: 'key2', translatedBool: ['en' => null, 'de' => true]),
                self::document(key: 'key3', translatedBool: ['en' => false, 'de' => true]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            [
                'count' => [
                    ['key' => false, 'count' => 2],
                    ['key' => true, 'count' => 2],
                ]
            ]
        ];
        yield 'Distinct, translated bool field' => [
            new Distinct(name: 'distinct', field: 'translatedBool'),
            new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true]),
                self::document(key: 'key2', translatedBool: ['en' => null, 'de' => true]),
                self::document(key: 'key3', translatedBool: ['en' => false, 'de' => true]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            ['distinct' => [false, true]]
        ];
    }

    public static function translatedDateCases(): \Generator
    {
        yield 'Min, translated date field' => [
            new Min(name: 'min', field: 'translatedDate'),
            new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', dateField: '2021-01-01 00:00:00.000', translatedDate: ['en' => null, 'de' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', dateField: '2021-01-01 00:00:00.000', translatedDate: ['de' => '2021-01-04 00:00:00.000']),
            ]),
            ['min' => '2021-01-01 00:00:00.000'],
        ];
        yield 'Max, translated date field' => [
            new Max(name: 'max', field: 'translatedDate'),
            new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => null, 'de' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['de' => '2021-01-04 00:00:00.000']),
            ]),
            ['max' => '2021-01-04 00:00:00.000'],
        ];
        yield 'Count, translated date field' => [
            new Count(name: 'count', field: 'translatedDate'),
            new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => null, 'de' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['de' => '2021-01-04 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['en' => '2021-01-04 00:00:00.000']),
            ]),
            [
                'count' => [
                    ['key' => '2021-01-01 00:00:00.000', 'count' => 1],
                    ['key' => '2021-01-03 00:00:00.000', 'count' => 1],
                    ['key' => '2021-01-04 00:00:00.000', 'count' => 2],
                ]
            ],
        ];
        yield 'Distinct, translated date field' => [
            new Distinct(name: 'distinct', field: 'translatedDate'),
            new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => null, 'de' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['de' => '2021-01-04 00:00:00.000']),
            ]),
            ['distinct' => ['2021-01-01 00:00:00.000', '2021-01-03 00:00:00.000', '2021-01-04 00:00:00.000']]
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
