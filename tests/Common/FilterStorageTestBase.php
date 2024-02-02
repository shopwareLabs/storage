<?php

namespace Shopware\StorageTests\Common;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Exception\NotSupportedByEngine;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Filter\Result;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Filter\Paging\Page;
use Shopware\Storage\Common\Filter\Type\Any;
use Shopware\Storage\Common\Filter\Type\Contains;
use Shopware\Storage\Common\Filter\Type\Equals;
use Shopware\Storage\Common\Filter\Type\Gt;
use Shopware\Storage\Common\Filter\Type\Gte;
use Shopware\Storage\Common\Filter\Type\Lt;
use Shopware\Storage\Common\Filter\Type\Lte;
use Shopware\Storage\Common\Filter\Type\Neither;
use Shopware\Storage\Common\Filter\Type\Not;
use Shopware\Storage\Common\Filter\Type\Prefix;
use Shopware\Storage\Common\Filter\Type\Suffix;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;

abstract class FilterStorageTestBase extends TestCase
{
    use SchemaStorageTrait;

    abstract public function getStorage(): FilterAware&Storage;

    #[DataProvider('objectBoolCases')]
    public function testDebug(
        Documents $input,
        Criteria $criteria,
        Result $expected
    ): void {
        $this->testStorage(
            input: $input,
            criteria: $criteria,
            expected: $expected
        );
    }

    #[DataProvider('keysCases')]
    #[DataProvider('paginationCases')]
    #[DataProvider('nestedObjectCases')]
    #[DataProvider('stringCases')]
    #[DataProvider('textCases')]
    #[DataProvider('intCases')]
    #[DataProvider('floatCases')]
    #[DataProvider('boolCases')]
    #[DataProvider('dateCases')]
    #[DataProvider('listStringCases')]
    #[DataProvider('listFloatCases')]
    #[DataProvider('listIntCases')]
    #[DataProvider('listDateCases')]
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
    //#[DataProvider('translatedListCases')]
    //#[DataProvider('translatedObjectStringCases')]
    //#[DataProvider('translatedObjectIntCases')]
    //#[DataProvider('translatedObjectFloatCases')]
    //#[DataProvider('translatedObjectBoolCases')]
    //#[DataProvider('translatedObjectDateCases')]
    final public function testStorage(Documents $input, Criteria $criteria, Result $expected): void
    {
        $storage = $this->getStorage();

        $storage->store($input);

        try {
            $loaded = $storage->filter($criteria, new StorageContext(languages: ['en', 'de']));
        } catch (NotSupportedByEngine $e) {
            static::markTestIncomplete($e->getMessage());
        }

        static::assertEquals($expected, $loaded);
    }

    /**
     * @param string[] $remove
     * @param array<Document> $expected
     */
    #[DataProvider('removeProvider')]
    final public function testRemove(Documents $input, array $remove, array $expected): void
    {
        $storage = $this->getStorage();

        $storage->store($input);

        $storage->remove($remove);

        $criteria = new Criteria(
            primaries: $input->keys()
        );

        $loaded = $storage->filter($criteria, new StorageContext(languages: ['en', 'de']));

        $expected = new Result($expected);

        static::assertEquals($expected, $loaded);
    }

    final public static function removeProvider(): \Generator
    {
        yield 'call remove with empty storage' => [
            'input' => new Documents(),
            'remove' => ['key1', 'key2'],
            'expected' => []
        ];

        yield 'call remove with single key' => [
            'input' => new Documents([
                self::document(key: 'key1'),
                self::document(key: 'key2'),
                self::document(key: 'key3'),
            ]),
            'remove' => ['key1'],
            'expected' => [
                self::document(key: 'key2'),
                self::document(key: 'key3'),
            ]
        ];

        yield 'call remove with multiple keys' => [
            'input' => new Documents([
                self::document(key: 'key1'),
                self::document(key: 'key2'),
                self::document(key: 'key3'),
            ]),
            'remove' => ['key1', 'key2'],
            'expected' => [
                self::document(key: 'key3'),
            ]
        ];
    }

    final public static function stringCases(): \Generator
    {
        yield 'string field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'foo'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'stringField', value: 'foo')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key3', stringField: 'foo'),
            ])
        ];
        yield 'string field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'stringField', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
            ])
        ];
        yield 'string field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'stringField', value: 'foo')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ])
        ];
        yield 'string field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'stringField', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', stringField: 'baz'),
            ])
        ];
        yield 'string field, contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'stringField', value: 'ba')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ])
        ];
        yield 'string field, starts-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Prefix(field: 'stringField', value: 'ba')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ])
        ];
        yield 'string field, ends-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'foo-bar'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Suffix(field: 'stringField', value: 'bar')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'foo-bar'),
            ])
        ];
        yield 'string field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'stringField', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ])
        ];
        yield 'string field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'stringField', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
            ])
        ];
        yield 'string field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'stringField', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', stringField: 'c'),
            ])
        ];
        yield 'string field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'stringField', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', stringField: 'a'),
            ])
        ];
        yield 'string field, gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
                self::document(key: 'key4', stringField: 'd'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'stringField', value: 'b'),
                    new Lte(field: 'stringField', value: 'c'),
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ])
        ];
        yield 'string field, null value, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: null),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'stringField', value: null)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', stringField: null)
            ])
        ];
        yield 'string field, null value, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: null),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'stringField', value: null)
                ]
            ),
            'expected' => new Result([
                self::document('key1', stringField: 'foo'),
                self::document('key2', stringField: 'bar')
            ])
        ];
    }

    final public static function textCases(): \Generator
    {
        yield 'text field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'foo'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'textField', value: 'foo')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key3', textField: 'foo'),
            ])
        ];
        yield 'text field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'textField', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
            ])
        ];
        yield 'text field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'textField', value: 'foo')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ])
        ];
        yield 'text field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'textField', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', textField: 'baz'),
            ])
        ];
        yield 'text field, contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'textField', value: 'ba')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ])
        ];
        yield 'text field, starts-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Prefix(field: 'textField', value: 'ba')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ])
        ];
        yield 'text field, ends-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'foo-bar'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Suffix(field: 'textField', value: 'bar')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'foo-bar'),
            ])
        ];
        yield 'text field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'textField', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ])
        ];
        yield 'text field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'textField', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
            ])
        ];
        yield 'text field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'textField', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', textField: 'c'),
            ])
        ];
        yield 'text field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'textField', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', textField: 'a'),
            ])
        ];
        yield 'text field, gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
                self::document(key: 'key4', textField: 'd'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'textField', value: 'b'),
                    new Lte(field: 'textField', value: 'c'),
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ])
        ];
    }

    final public static function intCases(): \Generator
    {
        yield 'int field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', intField: 2),
            ])
        ];
        yield 'int field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'intField', value: [1, 2])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
            ])
        ];
        yield 'int field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'int field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'intField', value: [1, 2])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'int field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'int field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
            ])
        ];
        yield 'int field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'int field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', intField: 1),
            ])
        ];
        yield 'int field, gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
                self::document(key: 'key4', intField: 4),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'intField', value: 2),
                    new Lte(field: 'intField', value: 3),
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'int field, null value, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: null),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'intField', value: null)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', intField: null)
            ])
        ];
        yield 'int field, null value, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: null),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'intField', value: null)
                ]
            ),
            'expected' => new Result([
                self::document('key1', intField: 1),
                self::document('key2', intField: 2)
            ])
        ];
    }

    final public static function floatCases(): \Generator
    {
        yield 'float field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', floatField: 2.2),
            ])
        ];
        yield 'float field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'floatField', value: [1.1, 2.2])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
            ])
        ];
        yield 'float field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'float field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'floatField', value: [1.1, 2.2])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'float field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'float field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
            ])
        ];
        yield 'float field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'float field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', floatField: 1.1),
            ])
        ];
        yield 'float field, gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
                self::document(key: 'key4', floatField: 4.4),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'floatField', value: 2.2),
                    new Lte(field: 'floatField', value: 3.3),
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'float field, null value, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: null),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'floatField', value: null)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', floatField: null)
            ])
        ];
        yield 'float field, null value, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: null),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'floatField', value: null)
                ]
            ),
            'expected' => new Result([
                self::document('key1', floatField: 1.1),
                self::document('key2', floatField: 2.2)
            ])
        ];
    }

    final public static function boolCases(): \Generator
    {
        yield 'bool field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', boolField: true),
                self::document(key: 'key2', boolField: false),
                self::document(key: 'key3', boolField: true),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'boolField', value: true)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', boolField: true),
                self::document(key: 'key3', boolField: true),
            ])
        ];
        yield 'bool field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', boolField: true),
                self::document(key: 'key2', boolField: false),
                self::document(key: 'key3', boolField: true),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'boolField', value: true)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', boolField: false),
            ])
        ];
    }

    final public static function dateCases(): \Generator
    {
        yield 'date field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
            ])
        ];
        yield 'date field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'dateField', value: ['2021-01-01', '2021-01-02'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
            ])
        ];
        yield 'date field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'date field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'dateField', value: ['2021-01-01', '2021-01-02'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'date field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'date field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
            ])
        ];
        yield 'date field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'date field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
            ])
        ];
        yield 'date field, gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
                self::document(key: 'key4', dateField: '2021-01-04 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'dateField', value: '2021-01-02'),
                    new Lte(field: 'dateField', value: '2021-01-03'),
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'date field, null value, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01'),
                self::document(key: 'key2', dateField: '2021-01-02'),
                self::document(key: 'key3', dateField: null),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'dateField', value: null)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', dateField: null)
            ])
        ];
        yield 'date field, null value, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: null),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'dateField', value: null)
                ]
            ),
            'expected' => new Result([
                self::document('key1', dateField: '2021-01-01 00:00:00.000'),
                self::document('key2', dateField: '2021-01-02 00:00:00.000')
            ])
        ];
    }

    final public static function listStringCases(): \Generator
    {
        yield 'list field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'listField', value: 'baz')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', listField: ['foo', 'baz']),
            ])
        ];
        yield 'list field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'listField', value: ['baz', 'qux'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ])
        ];
        yield 'list field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'listField', value: 'baz')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ])
        ];
        yield 'list field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'listField', value: ['baz', 'qux'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', listField: ['foo', 'bar']),
            ])
        ];
        yield 'list field, contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'listField', value: 'ba')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
            ])
        ];
        yield 'list field, null value, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: null),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'listField', value: null)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', listField: null)
            ])
        ];
        yield 'list field, null value, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: null),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'listField', value: null)
                ]
            ),
            'expected' => new Result([
                self::document('key1', listField: [1, 2]),
                self::document('key2', listField: [1, 3])
            ])
        ];
    }

    final public static function listFloatCases(): \Generator
    {
        yield 'list field, equals filter, float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'listField', value: 3.3)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', listField: [1.1, 3.3]),
            ])
        ];
        yield 'list field, equals any filter, float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'listField', value: [3.3, 4.4])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ])
        ];
        yield 'list field, not filter, float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'listField', value: 3.3)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ])
        ];
        yield 'list field, not any filter, float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'listField', value: [3.3, 4.4])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', listField: [1.1, 2.2]),
            ])
        ];
    }

    final public static function listIntCases(): \Generator
    {
        yield 'list field, equals filter, int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'listField', value: 3)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', listField: [1, 3]),
            ])
        ];
        yield 'list field, equals any filter, int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'listField', value: [3, 4])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ])
        ];
        yield 'list field, not filter, int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'listField', value: 3)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key3', listField: [1, 4]),
            ])
        ];
        yield 'list field, not any filter, int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'listField', value: [3, 4])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', listField: [1, 2]),
            ])
        ];
    }

    final public static function listDateCases(): \Generator
    {
        yield 'list field, equals filter, date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'listField', value: '2021-01-03')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
            ])
        ];
        yield 'list field, equals any filter, date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'listField', value: ['2021-01-03', '2021-01-04'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ])
        ];
        yield 'list field, not filter, date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'listField', value: '2021-01-03')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ])
        ];
        yield 'list field, not any filter, date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'listField', value: ['2021-01-03', '2021-01-04'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
            ])
        ];
        yield 'list field, contains filter, date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'listField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
            ])
        ];
    }

    final public static function nestedObjectCases(): \Generator
    {
        yield 'nested object' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooObj' => ['bar' => 'baz']]),
                self::document(key: 'key2', objectField: ['fooObj' => ['bar' => 'qux']]),
                self::document(key: 'key3', objectField: ['fooObj' => ['bar' => 'quux']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectField.fooObj.bar', value: 'qux')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['fooObj' => ['bar' => 'qux']]),
            ])
        ];
    }

    final public static function objectStringCases(): \Generator
    {
        yield 'object string field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectField.stringField', value: 'baz')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
            ])
        ];
        yield 'object string field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'objectField.stringField', value: ['baz', 'qux'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ])
        ];
        yield 'object string field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'objectField.stringField', value: 'baz')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ])
        ];
        yield 'object string field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'objectField.stringField', value: ['baz', 'qux'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
            ])
        ];
        yield 'object string field, contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'objectField.stringField', value: 'ba')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
            ])
        ];
        yield 'object string field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'objectField.stringField', value: 'baz')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ])
        ];
        yield 'object string field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'objectField.stringField', value: 'baz')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
            ])
        ];
        yield 'object string field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'objectField.stringField', value: 'baz')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ])
        ];
        yield 'object string field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
                self::document(key: 'key3', objectField: ['stringField' => 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'objectField.stringField', value: 'baz')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
            ])
        ];

        //        yield 'object field null value equals filter' => [
        //            'input' => new Documents([
        //                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
        //                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
        //                self::document(key: 'key3', objectField: null),
        //                self::document(key: 'key4', objectField: ['stringField' => null]),
        //            ]),
        //            'criteria' => new FilterCriteria(
        //                filters: [
        //                     new Equals(field: 'objectField.stringField', value: null)
        //                ]
        //            ),
        //            'expected' => new FilterResult([
        //                self::document(key: 'key4', objectField: ['stringField' => null]),
        //            ])
        //        ];
        //        yield 'object field nested null value equals filter' => [
        //            'input' => new Documents([
        //                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
        //                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
        //                self::document(key: 'key3', objectField: null),
        //                self::document(key: 'key4', objectField: ['stringField' => null]),
        //            ]),
        //            'criteria' => new FilterCriteria(
        //                filters: [
        //                     new Equals(field: 'objectField', value: null)
        //                ]
        //            ),
        //            'expected' => new FilterResult([
        //                self::document(key: 'key3', objectField: null),
        //            ])
        //        ];
        //        yield 'object field null value not filter' => [
        //            'input' => new Documents([
        //                self::document(key: 'key1', objectField: ['stringField' => 'bar']),
        //                self::document(key: 'key2', objectField: ['stringField' => 'baz']),
        //                self::document(key: 'key3', objectField: null),
        //            ]),
        //            'criteria' => new Criteria(
        //                filters: [
        //                     new Not(field: 'objectField.stringField', value: null)
        //                ]
        //            ),
        //            'expected' => new Result([
        //                self::document('key1', objectField: ['stringField' => 'bar']),
        //                self::document('key2', objectField: ['stringField' => 'baz'])
        //            ])
        //        ];
    }

    final public static function objectFloatCases(): \Generator
    {
        yield 'object float field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectField.floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
            ])
        ];
        yield 'object float field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'objectField.floatField', value: [1.1, 2.2])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
            ])
        ];
        yield 'object float field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'objectField.floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ])
        ];
        yield 'object float field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'objectField.floatField', value: [1.1, 2.2])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ])
        ];
        yield 'object float field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'objectField.floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ])
        ];
        yield 'object float field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'objectField.floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
            ])
        ];
        yield 'object float field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'objectField.floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ])
        ];
        yield 'object float field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'objectField.floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
            ])
        ];
        yield 'object float field, gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['floatField' => 1.1]),
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
                self::document(key: 'key4', objectField: ['floatField' => 4.4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'objectField.floatField', value: 2.2),
                    new Lte(field: 'objectField.floatField', value: 3.3),
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['floatField' => 2.2]),
                self::document(key: 'key3', objectField: ['floatField' => 3.3]),
            ])
        ];
    }

    final public static function objectIntCases(): \Generator
    {
        yield 'object int field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectField.intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['intField' => 2]),
            ])
        ];
        yield 'object int field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'objectField.intField', value: [1, 2])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
            ])
        ];
        yield 'object int field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'objectField.intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ])
        ];
        yield 'object int field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'objectField.intField', value: [1, 2])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ])
        ];
        yield 'object int field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'objectField.intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ])
        ];
        yield 'object int field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'objectField.intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
            ])
        ];
        yield 'object int field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'objectField.intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ])
        ];
        yield 'object int field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'objectField.intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['intField' => 1]),
            ])
        ];
        yield 'object int field, gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['intField' => 1]),
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
                self::document(key: 'key4', objectField: ['intField' => 4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'objectField.intField', value: 2),
                    new Lte(field: 'objectField.intField', value: 3),
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['intField' => 2]),
                self::document(key: 'key3', objectField: ['intField' => 3]),
            ])
        ];
    }

    final public static function objectBoolCases(): \Generator
    {
        yield 'object bool field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['boolField' => true]),
                self::document(key: 'key2', objectField: ['boolField' => false]),
                self::document(key: 'key3', objectField: ['boolField' => true]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectField.boolField', value: true)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['boolField' => true]),
                self::document(key: 'key3', objectField: ['boolField' => true]),
            ])
        ];
    }

    final public static function objectDateCases(): \Generator
    {
        yield 'object date field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectField.dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'object date field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'objectField.dateField', value: ['2021-01-02', '2021-01-03'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'object date field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'objectField.dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'object date field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'objectField.dateField', value: ['2021-01-02', '2021-01-03'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
            ])
        ];
        yield 'object date field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'objectField.dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'object date field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'objectField.dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'object date field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'objectField.dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'object date field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'objectField.dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
            ])
        ];
        yield 'object date field, gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['dateField' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key4', objectField: ['dateField' => '2021-01-04 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'objectField.dateField', value: '2021-01-02'),
                    new Lte(field: 'objectField.dateField', value: '2021-01-03'),
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectField: ['dateField' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['dateField' => '2021-01-03 00:00:00.000']),
            ])
        ];
    }

    final public static function translatedStringCases(): \Generator
    {
        yield 'translated string field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'translatedString', value: 'foo')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ])
        ];
        yield 'translated string field, equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'translatedString', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
            ])
        ];
        yield 'translated string field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'translatedString', value: 'foo')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ])
        ];
        yield 'translated string field, not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'translatedString', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ])
        ];
        yield 'translated string field, contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'boo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'foo', 'de' => 'bar']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'translatedString', value: 'oo')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedString: ['en' => 'boo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'foo', 'de' => 'bar']),
            ])
        ];
        yield 'translated string field, starts-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Prefix(field: 'translatedString', value: 'foo')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
            ])
        ];
        yield 'translated string field, ends-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'ob', 'de' => 'foo']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Suffix(field: 'translatedString', value: 'o')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
            ])
        ];
        yield 'translated string field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'translatedString', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ])
        ];
        yield 'translated string field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'translatedString', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedString: ['en' => 'c']),
            ])
        ];
        yield 'translated string field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'translatedString', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ])
        ];
        yield 'translated string field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'translatedString', value: 'b')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
            ])
        ];
        yield 'translated string field, equals filter, empty string' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => '', 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'translatedString', value: 'foo')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ])
        ];
    }

    final public static function translatedIntCases(): \Generator
    {
        yield 'translated int field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ])
        ];
        yield 'translated int field, equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key4', translatedInt: ['de' => 4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'translatedInt', value: [2, 3, 4])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key4', translatedInt: ['de' => 4]),
            ])
        ];
        yield 'translated int field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
            ])
        ];
        yield 'translated int field, not-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'translatedInt', value: [1, 2])
                ]
            ),
            'expected' => new Result([])
        ];
        yield 'translated int field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
            ])
        ];
        yield 'translated int field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedInt: ['en' => 3]),
            ])
        ];
        yield 'translated int field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ])
        ];
        yield 'translated int field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ])
        ];
    }

    final public static function translatedFloatCases(): \Generator
    {
        yield 'translated float field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ])
        ];
        yield 'translated float field, equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key4', translatedFloat: ['de' => 4.4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'translatedFloat', value: [2.2, 3.3, 4.4])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key4', translatedFloat: ['de' => 4.4]),
            ])
        ];
        yield 'translated float field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
            ])
        ];
        yield 'translated float field, not-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'translatedFloat', value: [1.1, 2.2])
                ]
            ),
            'expected' => new Result([])
        ];
        yield 'translated float field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
            ])
        ];
        yield 'translated float field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
            ])
        ];
        yield 'translated float field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ])
        ];
        yield 'translated float field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ])
        ];
    }

    final public static function translatedBoolCases(): \Generator
    {
        yield 'translated bool field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'translatedBool', value: false)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ])
        ];
        yield 'translated bool field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'translatedBool', value: false)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
            ])
        ];
    }

    final public static function translatedDateCases(): \Generator
    {
        yield 'translated date field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'translated date field, equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'translatedDate', value: ['2021-01-02 00:00:00.000', '2021-01-03 00:00:00.000'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'translated date field, not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'translated date field, not-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'translatedDate', value: ['2021-01-01 00:00:00.000', '2021-01-02 00:00:00.000'])
                ]
            ),
            'expected' => new Result([])
        ];
        yield 'translated date field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'translated date field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'translated date field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ])
        ];
        yield 'translated date field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ])
        ];
    }

    final public static function objectListStringCases(): \Generator
    {
        yield 'list object string field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['stringField' => 'bar'], ['stringField' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['stringField' => 'baz'], ['stringField' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'qux'], ['stringField' => 'qux-2'], ['stringField' => 'baz-2']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectListField.stringField', value: 'baz-2')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectListField: [['stringField' => 'baz'], ['stringField' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'qux'], ['stringField' => 'qux-2'], ['stringField' => 'baz-2']]),
            ])
        ];
        yield 'list object string field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['stringField' => 'bar'], ['stringField' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['stringField' => 'baz'], ['stringField' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'qux'], ['stringField' => 'qux-2'], ['stringField' => 'baz-2']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'objectListField.stringField', value: ['bar-2', 'qux-2'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['stringField' => 'bar'], ['stringField' => 'bar-2']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'qux'], ['stringField' => 'qux-2'], ['stringField' => 'baz-2']]),
            ])
        ];
        yield 'list object string field, contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['stringField' => 'bar'], ['stringField' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['stringField' => 'baz'], ['stringField' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'qux'], ['stringField' => 'qux-2'], ['stringField' => 'baz-2']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'objectListField.stringField', value: 'baz')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectListField: [['stringField' => 'baz'], ['stringField' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'qux'], ['stringField' => 'qux-2'], ['stringField' => 'baz-2']]),
            ])
        ];
        yield 'list object string field, starts-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['stringField' => 'bar'], ['stringField' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['stringField' => 'baz'], ['stringField' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'qux'], ['stringField' => 'qux-2'], ['stringField' => 'baz-2']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Prefix(field: 'objectListField.stringField', value: 'qu')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', objectListField: [['stringField' => 'qux'], ['stringField' => 'qux-2'], ['stringField' => 'baz-2']]),
            ])
        ];
        yield 'list object string field, ends-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['stringField' => 'bar'], ['stringField' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['stringField' => 'baz'], ['stringField' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'qux'], ['stringField' => 'qux-2'], ['stringField' => 'baz-2']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Suffix(field: 'objectListField.stringField', value: 'z-2')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectListField: [['stringField' => 'baz'], ['stringField' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['stringField' => 'qux'], ['stringField' => 'qux-2'], ['stringField' => 'baz-2']]),
            ])
        ];
    }

    final public static function objectListFloatCases(): \Generator
    {
        yield 'list object float field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
                self::document(key: 'key3', objectListField: [['floatField' => 20.1], ['floatField' => 22.2], ['floatField' => 24.2]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectListField.floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
            ])
        ];
        yield 'list object float field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
                self::document(key: 'key3', objectListField: [['floatField' => 20.1], ['floatField' => 22.2], ['floatField' => 24.2]]),
            ]),

            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'objectListField.floatField', value: [10.1, 22.2])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
                self::document(key: 'key3', objectListField: [['floatField' => 20.1], ['floatField' => 22.2], ['floatField' => 24.2]]),
            ])
        ];
        yield 'list object float field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
                self::document(key: 'key3', objectListField: [['floatField' => 20.1], ['floatField' => 22.2], ['floatField' => 24.2]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'objectListField.floatField', value: 22.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', objectListField: [['floatField' => 20.1], ['floatField' => 22.2], ['floatField' => 24.2]]),
            ])
        ];
        yield 'list object float field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
                self::document(key: 'key3', objectListField: [['floatField' => 20.1], ['floatField' => 22.2], ['floatField' => 24.2]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'objectListField.floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
            ])
        ];
        yield 'list object float field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
                self::document(key: 'key3', objectListField: [['floatField' => 20.1], ['floatField' => 22.2], ['floatField' => 24.2]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'objectListField.floatField', value: 2.2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
                self::document(key: 'key3', objectListField: [['floatField' => 20.1], ['floatField' => 22.2], ['floatField' => 24.2]]),
            ])
        ];
        yield 'list object float field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
                self::document(key: 'key3', objectListField: [['floatField' => 20.1], ['floatField' => 22.2], ['floatField' => 24.2]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'objectListField.floatField', value: 20.1)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['floatField' => 1.1], ['floatField' => 2.2]]),
                self::document(key: 'key2', objectListField: [['floatField' => 10.1], ['floatField' => 2.2]]),
            ])
        ];
    }

    final public static function objectListIntCases(): \Generator
    {
        yield 'list object int field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
                self::document(key: 'key3', objectListField: [['intField' => 20], ['intField' => 22], ['intField' => 24]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectListField.intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
            ])
        ];
        yield 'list object int field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
                self::document(key: 'key3', objectListField: [['intField' => 20], ['intField' => 22], ['intField' => 24]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'objectListField.intField', value: [10, 22])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
                self::document(key: 'key3', objectListField: [['intField' => 20], ['intField' => 22], ['intField' => 24]]),
            ])
        ];
        yield 'list object int field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
                self::document(key: 'key3', objectListField: [['intField' => 20], ['intField' => 22], ['intField' => 24]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'objectListField.intField', value: 22)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', objectListField: [['intField' => 20], ['intField' => 22], ['intField' => 24]]),
            ])
        ];
        yield 'list object int field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
                self::document(key: 'key3', objectListField: [['intField' => 20], ['intField' => 22], ['intField' => 24]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'objectListField.intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
            ])
        ];
        yield 'list object int field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
                self::document(key: 'key3', objectListField: [['intField' => 20], ['intField' => 22], ['intField' => 24]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'objectListField.intField', value: 2)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
                self::document(key: 'key3', objectListField: [['intField' => 20], ['intField' => 22], ['intField' => 24]]),
            ])
        ];
        yield 'list object int field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
                self::document(key: 'key3', objectListField: [['intField' => 20], ['intField' => 22], ['intField' => 24]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'objectListField.intField', value: 20)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['intField' => 1], ['intField' => 2]]),
                self::document(key: 'key2', objectListField: [['intField' => 10], ['intField' => 2]]),
            ])
        ];
    }

    final public static function objectListBoolCases(): \Generator
    {
        yield 'object list bool field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['boolField' => true]]),
                self::document(key: 'key2', objectListField: [['boolField' => false]]),
                self::document(key: 'key3', objectListField: [['boolField' => false], ['boolField' => true]]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectListField.boolField', value: true)
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['boolField' => true]]),
                self::document(key: 'key3', objectListField: [['boolField' => false], ['boolField' => true]]),
            ])
        ];
    }

    final public static function objectListDateCases(): \Generator
    {
        yield 'list object date field, gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-20 00:00:00.000'], ['dateField' => '2021-01-22 00:00:00.000'], ['dateField' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'objectListField.dateField', value: '2021-01-22 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-20 00:00:00.000'], ['dateField' => '2021-01-22 00:00:00.000'], ['dateField' => '2021-01-24 00:00:00.000']]),
            ])
        ];
        yield 'list object date field, lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-20 00:00:00.000'], ['dateField' => '2021-01-22 00:00:00.000'], ['dateField' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'objectListField.dateField', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
            ])
        ];
        yield 'list object date field, gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-20 00:00:00.000'], ['dateField' => '2021-01-22 00:00:00.000'], ['dateField' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'objectListField.dateField', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-20 00:00:00.000'], ['dateField' => '2021-01-22 00:00:00.000'], ['dateField' => '2021-01-24 00:00:00.000']]),
            ])
        ];
        yield 'list object date field, lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-20 00:00:00.000'], ['dateField' => '2021-01-22 00:00:00.000'], ['dateField' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'objectListField.dateField', value: '2021-01-20 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
            ])
        ];
        yield 'list object date field, equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-20 00:00:00.000'], ['dateField' => '2021-01-22 00:00:00.000'], ['dateField' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'objectListField.dateField', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
            ])
        ];
        yield 'list object date field, equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['dateField' => '2021-01-01 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-20 00:00:00.000'], ['dateField' => '2021-01-22 00:00:00.000'], ['dateField' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'objectListField.dateField', value: ['2021-01-10 00:00:00.000', '2021-01-22 00:00:00.000'])
                ]
            ),
            'expected' => new Result([
                self::document(key: 'key2', objectListField: [['dateField' => '2021-01-10 00:00:00.000'], ['dateField' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['dateField' => '2021-01-20 00:00:00.000'], ['dateField' => '2021-01-22 00:00:00.000'], ['dateField' => '2021-01-24 00:00:00.000']]),
            ])
        ];
    }

    final public static function keysCases(): \Generator
    {
        yield 'keys and values' => [
            'input' => new Documents([
                self::document(key: 'key1'),
                self::Document(key: 'key2'),
                self::Document(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                primaries: ['key1', 'key2']
            ),
            'expected' => new Result([
                self::document(key: 'key1'),
                self::document(key: 'key2'),
            ])
        ];
    }

    final public static function paginationCases(): \Generator
    {
        yield 'pagination' => [
            'input' => new Documents([
                self::document(key: 'key1'),
                self::document(key: 'key2'),
                self::document(key: 'key3'),
                self::document(key: 'key4'),
                self::document(key: 'key5'),
            ]),
            'criteria' => new Criteria(
                paging: new Page(page: 2, limit: 2)
            ),
            'expected' => new Result([
                self::document(key: 'key3'),
                self::document(key: 'key4'),
            ])
        ];
    }
}
