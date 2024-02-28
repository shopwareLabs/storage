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
use Shopware\Storage\Common\Schema\Translation;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\StorageTests\Common\Schema\Category;
use Shopware\StorageTests\Common\Schema\Media;
use Shopware\StorageTests\Common\Schema\Product;

abstract class FilterStorageTestBase extends TestCase
{
    abstract public function getStorage(): FilterAware&Storage;

    #[DataProvider('stringCases')]
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
    #[DataProvider('highlightCases')]
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
            'expected' => [],
        ];

        yield 'call remove with single key' => [
            'input' => new Documents([
                new Product(key: 'key1'),
                new Product(key: 'key2'),
                new Product(key: 'key3'),
            ]),
            'remove' => ['key1'],
            'expected' => [
                new Product(key: 'key2'),
                new Product(key: 'key3'),
            ],
        ];

        yield 'call remove with multiple keys' => [
            'input' => new Documents([
                new Product(key: 'key1'),
                new Product(key: 'key2'),
                new Product(key: 'key3'),
            ]),
            'remove' => ['key1', 'key2'],
            'expected' => [
                new Product(key: 'key3'),
            ],
        ];
    }

    final public static function stringCases(): \Generator
    {
        yield 'string field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'foo'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'ean', value: 'foo'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key3', ean: 'foo'),
            ]),
        ];
        yield 'string field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'ean', value: ['foo', 'bar']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key2', ean: 'bar'),
            ]),
        ];
        yield 'string field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'ean', value: 'foo'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'baz'),
            ]),
        ];
        yield 'string field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'ean', value: ['foo', 'bar']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', ean: 'baz'),
            ]),
        ];
        yield 'string field, contains filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'ean', value: 'ba'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'baz'),
            ]),
        ];
        yield 'string field, starts-with filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Prefix(field: 'ean', value: 'ba'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'baz'),
            ]),
        ];
        yield 'string field, ends-with filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'foo-bar'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Suffix(field: 'ean', value: 'bar'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3', ean: 'foo-bar'),
            ]),
        ];
        yield 'string field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'a'),
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'ean', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'c'),
            ]),
        ];
        yield 'string field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'a'),
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'ean', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', ean: 'a'),
                new Product(key: 'key2', ean: 'b'),
            ]),
        ];
        yield 'string field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'a'),
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'ean', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', ean: 'c'),
            ]),
        ];
        yield 'string field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'a'),
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'ean', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', ean: 'a'),
            ]),
        ];
        yield 'string field, gte and lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'a'),
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'c'),
                new Product(key: 'key4', ean: 'd'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'ean', value: 'b'),
                    new Lte(field: 'ean', value: 'c'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', ean: 'b'),
                new Product(key: 'key3', ean: 'c'),
            ]),
        ];
        yield 'string field, null value, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'ean', value: null),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3'),
            ]),
        ];
        yield 'string field, null value, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', ean: 'foo'),
                new Product(key: 'key2', ean: 'bar'),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'ean', value: null),
                ]
            ),
            'expected' => new Result([
                new Product('key1', ean: 'foo'),
                new Product('key2', ean: 'bar'),
            ]),
        ];
    }

    final public static function textCases(): \Generator
    {
        yield 'text field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'foo'),
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'foo'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'comment', value: 'foo'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', comment: 'foo'),
                new Product(key: 'key3', comment: 'foo'),
            ]),
        ];
        yield 'text field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'foo'),
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'comment', value: ['foo', 'bar']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', comment: 'foo'),
                new Product(key: 'key2', comment: 'bar'),
            ]),
        ];
        yield 'text field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'foo'),
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'comment', value: 'foo'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'baz'),
            ]),
        ];
        yield 'text field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'foo'),
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'comment', value: ['foo', 'bar']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', comment: 'baz'),
            ]),
        ];
        yield 'text field, contains filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'foo'),
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'comment', value: 'ba'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'baz'),
            ]),
        ];
        yield 'text field, starts-with filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'foo'),
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'baz'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Prefix(field: 'comment', value: 'ba'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'baz'),
            ]),
        ];
        yield 'text field, ends-with filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'foo'),
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'foo-bar'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Suffix(field: 'comment', value: 'bar'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', comment: 'bar'),
                new Product(key: 'key3', comment: 'foo-bar'),
            ]),
        ];
        yield 'text field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'a'),
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'comment', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'c'),
            ]),
        ];
        yield 'text field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'a'),
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'comment', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', comment: 'a'),
                new Product(key: 'key2', comment: 'b'),
            ]),
        ];
        yield 'text field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'a'),
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'comment', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', comment: 'c'),
            ]),
        ];
        yield 'text field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'a'),
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'c'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'comment', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', comment: 'a'),
            ]),
        ];
        yield 'text field, gte and lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', comment: 'a'),
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'c'),
                new Product(key: 'key4', comment: 'd'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'comment', value: 'b'),
                    new Lte(field: 'comment', value: 'c'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', comment: 'b'),
                new Product(key: 'key3', comment: 'c'),
            ]),
        ];
    }

    final public static function intCases(): \Generator
    {
        yield 'int field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', stock: 2),
            ]),
        ];
        yield 'int field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'stock', value: [1, 2]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
            ]),
        ];
        yield 'int field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key3', stock: 3),
            ]),
        ];
        yield 'int field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'stock', value: [1, 2]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', stock: 3),
            ]),
        ];
        yield 'int field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
        ];
        yield 'int field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
            ]),
        ];
        yield 'int field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', stock: 3),
            ]),
        ];
        yield 'int field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', stock: 1),
            ]),
        ];
        yield 'int field, gte and lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
                new Product(key: 'key4', stock: 4),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'stock', value: 2),
                    new Lte(field: 'stock', value: 3),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3', stock: 3),
            ]),
        ];
        yield 'int field, null value, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'stock', value: null),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3'),
            ]),
        ];
        yield 'int field, null value, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', stock: 1),
                new Product(key: 'key2', stock: 2),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'stock', value: null),
                ]
            ),
            'expected' => new Result([
                new Product('key1', stock: 1),
                new Product('key2', stock: 2),
            ]),
        ];
    }

    final public static function floatCases(): \Generator
    {
        yield 'float field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', price: 2.2),
            ]),
        ];
        yield 'float field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'price', value: [1.1, 2.2]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
            ]),
        ];
        yield 'float field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key3', price: 3.3),
            ]),
        ];
        yield 'float field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'price', value: [1.1, 2.2]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', price: 3.3),
            ]),
        ];
        yield 'float field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
        ];
        yield 'float field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
            ]),
        ];
        yield 'float field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', price: 3.3),
            ]),
        ];
        yield 'float field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', price: 1.1),
            ]),
        ];
        yield 'float field, gte and lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
                new Product(key: 'key4', price: 4.4),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'price', value: 2.2),
                    new Lte(field: 'price', value: 3.3),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3', price: 3.3),
            ]),
        ];
        yield 'float field, null value, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'price', value: null),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3'),
            ]),
        ];
        yield 'float field, null value, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', price: 1.1),
                new Product(key: 'key2', price: 2.2),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'price', value: null),
                ]
            ),
            'expected' => new Result([
                new Product('key1', price: 1.1),
                new Product('key2', price: 2.2),
            ]),
        ];
    }

    final public static function boolCases(): \Generator
    {
        yield 'bool field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', active: true),
                new Product(key: 'key2', active: false),
                new Product(key: 'key3', active: true),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'active', value: true),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', active: true),
                new Product(key: 'key3', active: true),
            ]),
        ];
        yield 'bool field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', active: true),
                new Product(key: 'key2', active: false),
                new Product(key: 'key3', active: true),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'active', value: true),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', active: false),
            ]),
        ];
    }

    final public static function dateCases(): \Generator
    {
        yield 'date field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
            ]),
        ];
        yield 'date field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'changed', value: ['2021-01-01', '2021-01-02']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
            ]),
        ];
        yield 'date field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
        ];
        yield 'date field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'changed', value: ['2021-01-01', '2021-01-02']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
        ];
        yield 'date field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
        ];
        yield 'date field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
            ]),
        ];
        yield 'date field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
        ];
        yield 'date field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
            ]),
        ];
        yield 'date field, gte and lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
                new Product(key: 'key4', changed: '2021-01-04 00:00:00.000'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'changed', value: '2021-01-02'),
                    new Lte(field: 'changed', value: '2021-01-03'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3', changed: '2021-01-03 00:00:00.000'),
            ]),
        ];
        yield 'date field, null value, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01'),
                new Product(key: 'key2', changed: '2021-01-02'),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'changed', value: null),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3'),
            ]),
        ];
        yield 'date field, null value, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', changed: '2021-01-01 00:00:00.000'),
                new Product(key: 'key2', changed: '2021-01-02 00:00:00.000'),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'changed', value: null),
                ]
            ),
            'expected' => new Result([
                new Product('key1', changed: '2021-01-01 00:00:00.000'),
                new Product('key2', changed: '2021-01-02 00:00:00.000'),
            ]),
        ];
    }

    final public static function listStringCases(): \Generator
    {
        yield 'list field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: ['foo', 'bar']),
                new Product(key: 'key2', keywords: ['foo', 'baz']),
                new Product(key: 'key3', keywords: ['foo', 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'keywords', value: 'baz'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', keywords: ['foo', 'baz']),
            ]),
        ];
        yield 'list field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: ['foo', 'bar']),
                new Product(key: 'key2', keywords: ['foo', 'baz']),
                new Product(key: 'key3', keywords: ['foo', 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'keywords', value: ['baz', 'qux']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', keywords: ['foo', 'baz']),
                new Product(key: 'key3', keywords: ['foo', 'qux']),
            ]),
        ];
        yield 'list field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: ['foo', 'bar']),
                new Product(key: 'key2', keywords: ['foo', 'baz']),
                new Product(key: 'key3', keywords: ['foo', 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'keywords', value: 'baz'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', keywords: ['foo', 'bar']),
                new Product(key: 'key3', keywords: ['foo', 'qux']),
            ]),
        ];
        yield 'list field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: ['foo', 'bar']),
                new Product(key: 'key2', keywords: ['foo', 'baz']),
                new Product(key: 'key3', keywords: ['foo', 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'keywords', value: ['baz', 'qux']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', keywords: ['foo', 'bar']),
            ]),
        ];
        yield 'list field, contains filter' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: ['foo', 'bar']),
                new Product(key: 'key2', keywords: ['foo', 'baz']),
                new Product(key: 'key3', keywords: ['foo', 'qux']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'keywords', value: 'ba'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', keywords: ['foo', 'bar']),
                new Product(key: 'key2', keywords: ['foo', 'baz']),
            ]),
        ];
        yield 'list field, null value, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: [1, 2]),
                new Product(key: 'key2', keywords: [1, 3]),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'keywords', value: null),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3'),
            ]),
        ];
        yield 'list field, null value, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: [1, 2]),
                new Product(key: 'key2', keywords: [1, 3]),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'keywords', value: null),
                ]
            ),
            'expected' => new Result([
                new Product('key1', keywords: [1, 2]),
                new Product('key2', keywords: [1, 3]),
            ]),
        ];
    }

    final public static function listFloatCases(): \Generator
    {
        yield 'list field, equals filter, float values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: [1.1, 2.2]),
                new Product(key: 'key2', keywords: [1.1, 3.3]),
                new Product(key: 'key3', keywords: [1.1, 4.4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'keywords', value: 3.3),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', keywords: [1.1, 3.3]),
            ]),
        ];
        yield 'list field, equals any filter, float values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: [1.1, 2.2]),
                new Product(key: 'key2', keywords: [1.1, 3.3]),
                new Product(key: 'key3', keywords: [1.1, 4.4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'keywords', value: [3.3, 4.4]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', keywords: [1.1, 3.3]),
                new Product(key: 'key3', keywords: [1.1, 4.4]),
            ]),
        ];
        yield 'list field, not filter, float values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: [1.1, 2.2]),
                new Product(key: 'key2', keywords: [1.1, 3.3]),
                new Product(key: 'key3', keywords: [1.1, 4.4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'keywords', value: 3.3),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', keywords: [1.1, 2.2]),
                new Product(key: 'key3', keywords: [1.1, 4.4]),
            ]),
        ];
        yield 'list field, not any filter, float values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: [1.1, 2.2]),
                new Product(key: 'key2', keywords: [1.1, 3.3]),
                new Product(key: 'key3', keywords: [1.1, 4.4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'keywords', value: [3.3, 4.4]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', keywords: [1.1, 2.2]),
            ]),
        ];
    }

    final public static function listIntCases(): \Generator
    {
        yield 'list field, equals filter, int values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: [1, 2]),
                new Product(key: 'key2', keywords: [1, 3]),
                new Product(key: 'key3', keywords: [1, 4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'keywords', value: 3),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', keywords: [1, 3]),
            ]),
        ];
        yield 'list field, equals any filter, int values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: [1, 2]),
                new Product(key: 'key2', keywords: [1, 3]),
                new Product(key: 'key3', keywords: [1, 4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'keywords', value: [3, 4]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', keywords: [1, 3]),
                new Product(key: 'key3', keywords: [1, 4]),
            ]),
        ];
        yield 'list field, not filter, int values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: [1, 2]),
                new Product(key: 'key2', keywords: [1, 3]),
                new Product(key: 'key3', keywords: [1, 4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'keywords', value: 3),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', keywords: [1, 2]),
                new Product(key: 'key3', keywords: [1, 4]),
            ]),
        ];
        yield 'list field, not any filter, int values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: [1, 2]),
                new Product(key: 'key2', keywords: [1, 3]),
                new Product(key: 'key3', keywords: [1, 4]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'keywords', value: [3, 4]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', keywords: [1, 2]),
            ]),
        ];
    }

    final public static function listDateCases(): \Generator
    {
        yield 'list field, equals filter, date values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: ['2021-01-01', '2021-01-02']),
                new Product(key: 'key2', keywords: ['2021-01-01', '2021-01-03']),
                new Product(key: 'key3', keywords: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'keywords', value: '2021-01-03'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', keywords: ['2021-01-01', '2021-01-03']),
            ]),
        ];
        yield 'list field, equals any filter, date values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: ['2021-01-01', '2021-01-02']),
                new Product(key: 'key2', keywords: ['2021-01-01', '2021-01-03']),
                new Product(key: 'key3', keywords: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'keywords', value: ['2021-01-03', '2021-01-04']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', keywords: ['2021-01-01', '2021-01-03']),
                new Product(key: 'key3', keywords: ['2021-01-01', '2021-01-04']),
            ]),
        ];
        yield 'list field, not filter, date values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: ['2021-01-01', '2021-01-02']),
                new Product(key: 'key2', keywords: ['2021-01-01', '2021-01-03']),
                new Product(key: 'key3', keywords: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'keywords', value: '2021-01-03'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', keywords: ['2021-01-01', '2021-01-02']),
                new Product(key: 'key3', keywords: ['2021-01-01', '2021-01-04']),
            ]),
        ];
        yield 'list field, not any filter, date values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: ['2021-01-01', '2021-01-02']),
                new Product(key: 'key2', keywords: ['2021-01-01', '2021-01-03']),
                new Product(key: 'key3', keywords: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'keywords', value: ['2021-01-03', '2021-01-04']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', keywords: ['2021-01-01', '2021-01-02']),
            ]),
        ];
        yield 'list field, contains filter, date values' => [
            'input' => new Documents([
                new Product(key: 'key1', keywords: ['2021-01-01', '2021-01-02']),
                new Product(key: 'key2', keywords: ['2021-01-01', '2021-01-03']),
                new Product(key: 'key3', keywords: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'keywords', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', keywords: ['2021-01-01', '2021-01-02']),
            ]),
        ];
    }

    final public static function nestedObjectCases(): \Generator
    {
        yield 'nested object' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(logo: new Media(url: 'baz'))),
                new Product(key: 'key2', mainCategory: new Category(logo: new Media(url: 'qux'))),
                new Product(key: 'key3', mainCategory: new Category(logo: new Media(url: 'quux'))),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'mainCategory.logo.url', value: 'qux'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(logo: new Media(url: 'qux'))),
            ]),
        ];
    }

    final public static function objectStringCases(): \Generator
    {
        yield 'object string field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'mainCategory.ean', value: 'baz'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
            ]),
        ];
        yield 'object string field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'mainCategory.ean', value: ['baz', 'qux']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
        ];
        yield 'object string field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'mainCategory.ean', value: 'baz'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
        ];
        yield 'object string field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'mainCategory.ean', value: ['baz', 'qux']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
            ]),
        ];
        yield 'object string field, contains filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'mainCategory.ean', value: 'ba'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
            ]),
        ];
        yield 'object string field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'mainCategory.ean', value: 'baz'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
        ];
        yield 'object string field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'mainCategory.ean', value: 'baz'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
            ]),
        ];
        yield 'object string field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'mainCategory.ean', value: 'baz'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
        ];
        yield 'object string field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
                new Product(key: 'key2', mainCategory: new Category(ean: 'baz')),
                new Product(key: 'key3', mainCategory: new Category(ean: 'qux')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'mainCategory.ean', value: 'baz'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(ean: 'bar')),
            ]),
        ];

        //        yield 'object field null value equals filter' => [
        //            'input' => new Documents([
        //                self::document(key: 'key1', mainCategory: ['ean' => 'bar']),
        //                self::document(key: 'key2', mainCategory: ['ean' => 'baz']),
        //                self::document(key: 'key3', mainCategory: null),
        //                self::document(key: 'key4', mainCategory: ['ean' => null]),
        //            ]),
        //            'criteria' => new FilterCriteria(
        //                filters: [
        //                     new Equals(field: 'mainCategory.ean', value: null)
        //                ]
        //            ),
        //            'expected' => new FilterResult([
        //                self::document(key: 'key4', mainCategory: ['ean' => null]),
        //            ])
        //        ];
        //        yield 'object field nested null value equals filter' => [
        //            'input' => new Documents([
        //                self::document(key: 'key1', mainCategory: ['ean' => 'bar']),
        //                self::document(key: 'key2', mainCategory: ['ean' => 'baz']),
        //                self::document(key: 'key3', mainCategory: null),
        //                self::document(key: 'key4', mainCategory: ['ean' => null]),
        //            ]),
        //            'criteria' => new FilterCriteria(
        //                filters: [
        //                     new Equals(field: 'mainCategory', value: null)
        //                ]
        //            ),
        //            'expected' => new FilterResult([
        //                self::document(key: 'key3', mainCategory: null),
        //            ])
        //        ];
        //        yield 'object field null value not filter' => [
        //            'input' => new Documents([
        //                self::document(key: 'key1', mainCategory: ['ean' => 'bar']),
        //                self::document(key: 'key2', mainCategory: ['ean' => 'baz']),
        //                self::document(key: 'key3', mainCategory: null),
        //            ]),
        //            'criteria' => new Criteria(
        //                filters: [
        //                     new Not(field: 'mainCategory.ean', value: null)
        //                ]
        //            ),
        //            'expected' => new Result([
        //                self::document('key1', mainCategory: ['ean' => 'bar']),
        //                self::document('key2', mainCategory: ['ean' => 'baz'])
        //            ])
        //        ];
    }

    final public static function objectFloatCases(): \Generator
    {
        yield 'object float field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'mainCategory.price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
            ]),
        ];
        yield 'object float field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'mainCategory.price', value: [1.1, 2.2]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
            ]),
        ];
        yield 'object float field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'mainCategory.price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
        ];
        yield 'object float field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'mainCategory.price', value: [1.1, 2.2]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
        ];
        yield 'object float field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'mainCategory.price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
        ];
        yield 'object float field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'mainCategory.price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
            ]),
        ];
        yield 'object float field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'mainCategory.price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
        ];
        yield 'object float field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'mainCategory.price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
            ]),
        ];
        yield 'object float field, gte and lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(price: 1.1)),
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
                new Product(key: 'key4', mainCategory: new Category(price: 4.4)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'mainCategory.price', value: 2.2),
                    new Lte(field: 'mainCategory.price', value: 3.3),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(price: 2.2)),
                new Product(key: 'key3', mainCategory: new Category(price: 3.3)),
            ]),
        ];
    }

    final public static function objectIntCases(): \Generator
    {
        yield 'object int field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'mainCategory.stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
            ]),
        ];
        yield 'object int field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'mainCategory.stock', value: [1, 2]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
            ]),
        ];
        yield 'object int field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'mainCategory.stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
        ];
        yield 'object int field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'mainCategory.stock', value: [1, 2]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
        ];
        yield 'object int field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'mainCategory.stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
        ];
        yield 'object int field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'mainCategory.stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
            ]),
        ];
        yield 'object int field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'mainCategory.stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
        ];
        yield 'object int field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'mainCategory.stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
            ]),
        ];
        yield 'object int field, gte and lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(stock: 1)),
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
                new Product(key: 'key4', mainCategory: new Category(stock: 4)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'mainCategory.stock', value: 2),
                    new Lte(field: 'mainCategory.stock', value: 3),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(stock: 2)),
                new Product(key: 'key3', mainCategory: new Category(stock: 3)),
            ]),
        ];
    }

    final public static function objectBoolCases(): \Generator
    {
        yield 'object bool field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(active: true)),
                new Product(key: 'key2', mainCategory: new Category(active: false)),
                new Product(key: 'key3', mainCategory: new Category(active: true)),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'mainCategory.active', value: true),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(active: true)),
                new Product(key: 'key3', mainCategory: new Category(active: true)),
            ]),
        ];
    }

    final public static function objectDateCases(): \Generator
    {
        yield 'object date field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'mainCategory.changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
            ]),
        ];
        yield 'object date field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'mainCategory.changed', value: ['2021-01-02', '2021-01-03']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
        ];
        yield 'object date field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'mainCategory.changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
        ];
        yield 'object date field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'mainCategory.changed', value: ['2021-01-02', '2021-01-03']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
            ]),
        ];
        yield 'object date field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'mainCategory.changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
        ];
        yield 'object date field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'mainCategory.changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
            ]),
        ];
        yield 'object date field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'mainCategory.changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
        ];
        yield 'object date field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'mainCategory.changed', value: '2021-01-02'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
            ]),
        ];
        yield 'object date field, gte and lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', mainCategory: new Category(changed: '2021-01-01 00:00:00.000')),
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
                new Product(key: 'key4', mainCategory: new Category(changed: '2021-01-04 00:00:00.000')),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'mainCategory.changed', value: '2021-01-02'),
                    new Lte(field: 'mainCategory.changed', value: '2021-01-03'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', mainCategory: new Category(changed: '2021-01-02 00:00:00.000')),
                new Product(key: 'key3', mainCategory: new Category(changed: '2021-01-03 00:00:00.000')),
            ]),
        ];
    }

    final public static function translatedStringCases(): \Generator
    {
        yield 'translated string field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'bar', 'de' => 'foo'])),
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['de' => 'foo'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'name', value: 'foo'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['de' => 'foo'])),
            ]),
        ];
        yield 'translated string field, equals-any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'bar', 'de' => 'foo'])),
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['en' => 'baz', 'de' => 'foo'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'name', value: ['foo', 'bar']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', name: new Translation(['en' => 'bar', 'de' => 'foo'])),
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
            ]),
        ];
        yield 'translated string field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'bar', 'de' => 'foo'])),
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['en' => 'baz', 'de' => 'foo'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'name', value: 'foo'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', name: new Translation(['en' => 'bar', 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['en' => 'baz', 'de' => 'foo'])),
            ]),
        ];
        yield 'translated string field, not any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'bar', 'de' => 'foo'])),
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['en' => 'baz', 'de' => 'foo'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'name', value: ['foo', 'bar']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key4', name: new Translation(['en' => 'baz', 'de' => 'foo'])),
            ]),
        ];
        yield 'translated string field, contains filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'bar', 'de' => 'foo'])),
                new Product(key: 'key2', name: new Translation(['en' => 'boo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['en' => 'foo', 'de' => 'bar'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'name', value: 'oo'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', name: new Translation(['en' => 'boo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['en' => 'foo', 'de' => 'bar'])),
            ]),
        ];
        yield 'translated string field, starts-with filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'bar', 'de' => 'foo'])),
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['en' => 'baz', 'de' => 'foo'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Prefix(field: 'name', value: 'foo'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
            ]),
        ];
        yield 'translated string field, ends-with filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'bar', 'de' => 'foo'])),
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['en' => 'ob', 'de' => 'foo'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Suffix(field: 'name', value: 'o'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'foo'])),
            ]),
        ];
        yield 'translated string field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'a', 'de' => 'b'])),
                new Product(key: 'key2', name: new Translation(['en' => 'c'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'b'])),
                new Product(key: 'key4', name: new Translation(['en' => 'b', 'de' => 'a'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'name', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', name: new Translation(['en' => 'c'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'b'])),
                new Product(key: 'key4', name: new Translation(['en' => 'b', 'de' => 'a'])),
            ]),
        ];
        yield 'translated string field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'a', 'de' => 'b'])),
                new Product(key: 'key2', name: new Translation(['en' => 'c'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'b'])),
                new Product(key: 'key4', name: new Translation(['en' => 'b', 'de' => 'a'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'name', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', name: new Translation(['en' => 'c'])),
            ]),
        ];
        yield 'translated string field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'a', 'de' => 'b'])),
                new Product(key: 'key2', name: new Translation(['en' => 'c'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'b'])),
                new Product(key: 'key4', name: new Translation(['en' => 'b', 'de' => 'a'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'name', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', name: new Translation(['en' => 'a', 'de' => 'b'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'b'])),
                new Product(key: 'key4', name: new Translation(['en' => 'b', 'de' => 'a'])),
            ]),
        ];
        yield 'translated string field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'a', 'de' => 'b'])),
                new Product(key: 'key2', name: new Translation(['en' => 'c'])),
                new Product(key: 'key3', name: new Translation(['en' => null, 'de' => 'b'])),
                new Product(key: 'key4', name: new Translation(['en' => 'b', 'de' => 'a'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'name', value: 'b'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', name: new Translation(['en' => 'a', 'de' => 'b'])),
            ]),
        ];
        yield 'translated string field, equals filter, empty string' => [
            'input' => new Documents([
                new Product(key: 'key1', name: new Translation(['en' => 'bar', 'de' => 'foo'])),
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key3', name: new Translation(['en' => '', 'de' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['de' => 'foo'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'name', value: 'foo'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', name: new Translation(['en' => 'foo'])),
                new Product(key: 'key4', name: new Translation(['de' => 'foo'])),
            ]),
        ];
    }

    final public static function translatedIntCases(): \Generator
    {
        yield 'translated int field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => 2])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 2])),
                new Product(key: 'key4', position: new Translation(['de' => 2])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'position', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', position: new Translation(['en' => 2])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 2])),
                new Product(key: 'key4', position: new Translation(['de' => 2])),
            ]),
        ];
        yield 'translated int field, equals-any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => 2])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 3])),
                new Product(key: 'key4', position: new Translation(['de' => 4])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'position', value: [2, 3, 4]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', position: new Translation(['en' => 2])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 3])),
                new Product(key: 'key4', position: new Translation(['de' => 4])),
            ]),
        ];
        yield 'translated int field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => 2])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 2])),
                new Product(key: 'key4', position: new Translation(['de' => 2])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'position', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
            ]),
        ];
        yield 'translated int field, not-any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => 2])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 2])),
                new Product(key: 'key4', position: new Translation(['de' => 2])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'position', value: [1, 2]),
                ]
            ),
            'expected' => new Result([]),
        ];
        yield 'translated int field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => 3])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 2])),
                new Product(key: 'key4', position: new Translation(['de' => 1])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'position', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', position: new Translation(['en' => 3])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 2])),
            ]),
        ];
        yield 'translated int field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => 3])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 2])),
                new Product(key: 'key4', position: new Translation(['de' => 1])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'position', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', position: new Translation(['en' => 3])),
            ]),
        ];
        yield 'translated int field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => 3])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 2])),
                new Product(key: 'key4', position: new Translation(['de' => 1])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'position', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 2])),
                new Product(key: 'key4', position: new Translation(['de' => 1])),
            ]),
        ];
        yield 'translated int field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key2', position: new Translation(['en' => 3])),
                new Product(key: 'key3', position: new Translation(['en' => null, 'de' => 2])),
                new Product(key: 'key4', position: new Translation(['de' => 1])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'position', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', position: new Translation(['en' => 1, 'de' => 2])),
                new Product(key: 'key4', position: new Translation(['de' => 1])),
            ]),
        ];
    }

    final public static function translatedFloatCases(): \Generator
    {
        yield 'translated float field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => 2.2])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 2.2])),
                new Product(key: 'key4', weight: new Translation(['de' => 2.2])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'weight', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', weight: new Translation(['en' => 2.2])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 2.2])),
                new Product(key: 'key4', weight: new Translation(['de' => 2.2])),
            ]),
        ];
        yield 'translated float field, equals-any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => 2.2])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 3.3])),
                new Product(key: 'key4', weight: new Translation(['de' => 4.4])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'weight', value: [2.2, 3.3, 4.4]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', weight: new Translation(['en' => 2.2])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 3.3])),
                new Product(key: 'key4', weight: new Translation(['de' => 4.4])),
            ]),
        ];
        yield 'translated float field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => 2.2])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 2.2])),
                new Product(key: 'key4', weight: new Translation(['de' => 2.2])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'weight', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
            ]),
        ];
        yield 'translated float field, not-any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => 2.2])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 2.2])),
                new Product(key: 'key4', weight: new Translation(['de' => 2.2])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'weight', value: [1.1, 2.2]),
                ]
            ),
            'expected' => new Result([]),
        ];
        yield 'translated float field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => 3.3])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 2.2])),
                new Product(key: 'key4', weight: new Translation(['de' => 1.1])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'weight', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', weight: new Translation(['en' => 3.3])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 2.2])),
            ]),
        ];
        yield 'translated float field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => 3.3])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 2.2])),
                new Product(key: 'key4', weight: new Translation(['de' => 1.1])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'weight', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', weight: new Translation(['en' => 3.3])),
            ]),
        ];
        yield 'translated float field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => 3.3])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 2.2])),
                new Product(key: 'key4', weight: new Translation(['de' => 1.1])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'weight', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 2.2])),
                new Product(key: 'key4', weight: new Translation(['de' => 1.1])),
            ]),
        ];
        yield 'translated float field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key2', weight: new Translation(['en' => 3.3])),
                new Product(key: 'key3', weight: new Translation(['en' => null, 'de' => 2.2])),
                new Product(key: 'key4', weight: new Translation(['de' => 1.1])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'weight', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', weight: new Translation(['en' => 1.1, 'de' => 2.2])),
                new Product(key: 'key4', weight: new Translation(['de' => 1.1])),
            ]),
        ];
    }

    final public static function highlightCases(): \Generator
    {
        yield 'translated bool field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', highlight: new Translation(['en' => true, 'de' => false])),
                new Product(key: 'key2', highlight: new Translation(['en' => false])),
                new Product(key: 'key3', highlight: new Translation(['en' => null, 'de' => false])),
                new Product(key: 'key4', highlight: new Translation(['de' => false])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'highlight', value: false),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', highlight: new Translation(['en' => false])),
                new Product(key: 'key3', highlight: new Translation(['en' => null, 'de' => false])),
                new Product(key: 'key4', highlight: new Translation(['de' => false])),
            ]),
        ];
        yield 'translated bool field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', highlight: new Translation(['en' => true, 'de' => false])),
                new Product(key: 'key2', highlight: new Translation(['en' => false])),
                new Product(key: 'key3', highlight: new Translation(['en' => null, 'de' => false])),
                new Product(key: 'key4', highlight: new Translation(['de' => false])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'highlight', value: false),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', highlight: new Translation(['en' => true, 'de' => false])),
            ]),
        ];
    }

    final public static function translatedDateCases(): \Generator
    {
        yield 'translated date field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-02 00:00:00.000'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'release', value: '2021-01-02 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-02 00:00:00.000'])),
            ]),
        ];
        yield 'translated date field, equals-any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-02 00:00:00.000'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'release', value: ['2021-01-02 00:00:00.000', '2021-01-03 00:00:00.000']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-02 00:00:00.000'])),
            ]),
        ];
        yield 'translated date field, not filter' => [
            'input' => new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-02 00:00:00.000'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Not(field: 'release', value: '2021-01-02 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
            ]),
        ];
        yield 'translated date field, not-any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-02 00:00:00.000'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Neither(field: 'release', value: ['2021-01-01 00:00:00.000', '2021-01-02 00:00:00.000']),
                ]
            ),
            'expected' => new Result([]),
        ];
        yield 'translated date field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-01 00:00:00.000'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'release', value: '2021-01-02 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-02 00:00:00.000'])),
            ]),
        ];
        yield 'translated date field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-01 00:00:00.000'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'release', value: '2021-01-02 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-03 00:00:00.000'])),
            ]),
        ];
        yield 'translated date field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-01 00:00:00.000'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'release', value: '2021-01-02 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-01 00:00:00.000'])),
            ]),
        ];
        yield 'translated date field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key2', release: new Translation(['en' => '2021-01-03 00:00:00.000'])),
                new Product(key: 'key3', release: new Translation(['en' => null, 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-01 00:00:00.000'])),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'release', value: '2021-01-02 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', release: new Translation(['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000'])),
                new Product(key: 'key4', release: new Translation(['de' => '2021-01-01 00:00:00.000'])),
            ]),
        ];
    }

    final public static function objectListStringCases(): \Generator
    {
        yield 'list object string field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(ean: 'bar'),
                    new Category(ean: 'bar-2'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(ean: 'baz'),
                    new Category(ean: 'baz-2'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(ean: 'qux'),
                    new Category(ean: 'qux-2'),
                    new Category(ean: 'baz-2'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'categories.ean', value: 'baz-2'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', categories: [
                    new Category(ean: 'baz'),
                    new Category(ean: 'baz-2'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(ean: 'qux'),
                    new Category(ean: 'qux-2'),
                    new Category(ean: 'baz-2'),
                ]),
            ]),
        ];
        yield 'list object string field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(ean: 'bar'),
                    new Category(ean: 'bar-2'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(ean: 'baz'),
                    new Category(ean: 'baz-2'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(ean: 'qux'),
                    new Category(ean: 'qux-2'),
                    new Category(ean: 'baz-2'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'categories.ean', value: ['bar-2', 'qux-2']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(ean: 'bar'),
                    new Category(ean: 'bar-2'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(ean: 'qux'),
                    new Category(ean: 'qux-2'),
                    new Category(ean: 'baz-2'),
                ]),
            ]),
        ];
        yield 'list object string field, contains filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(ean: 'bar'),
                    new Category(ean: 'bar-2'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(ean: 'baz'),
                    new Category(ean: 'baz-2'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(ean: 'qux'),
                    new Category(ean: 'qux-2'),
                    new Category(ean: 'baz-2'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Contains(field: 'categories.ean', value: 'baz'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', categories: [
                    new Category(ean: 'baz'),
                    new Category(ean: 'baz-2'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(ean: 'qux'),
                    new Category(ean: 'qux-2'),
                    new Category(ean: 'baz-2'),
                ]),
            ]),
        ];
        yield 'list object string field, starts-with filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(ean: 'bar'),
                    new Category(ean: 'bar-2'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(ean: 'baz'),
                    new Category(ean: 'baz-2'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(ean: 'qux'),
                    new Category(ean: 'qux-2'),
                    new Category(ean: 'baz-2'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Prefix(field: 'categories.ean', value: 'qu'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', categories: [
                    new Category(ean: 'qux'),
                    new Category(ean: 'qux-2'),
                    new Category(ean: 'baz-2'),
                ]),
            ]),
        ];
        yield 'list object string field, ends-with filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(ean: 'bar'),
                    new Category(ean: 'bar-2'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(ean: 'baz'),
                    new Category(ean: 'baz-2'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(ean: 'qux'),
                    new Category(ean: 'qux-2'),
                    new Category(ean: 'baz-2'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Suffix(field: 'categories.ean', value: 'z-2'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', categories: [
                    new Category(ean: 'baz'),
                    new Category(ean: 'baz-2'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(ean: 'qux'),
                    new Category(ean: 'qux-2'),
                    new Category(ean: 'baz-2'),
                ]),
            ]),
        ];
    }

    final public static function combinedObjectListCases(): \Generator
    {
        yield 'Test multi filter on nested objects' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(ean: 'bar', stock: 1, active: true),
                    new Category(ean: 'bar-2', stock: 2, active: false),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(ean: 'bar', stock: 1, active: false),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(ean: 'bar', stock: 2, active: true),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'categories.ean', value: 'bar'),
                    new Equals(field: 'categories.stock', value: 1),
                    new Equals(field: 'categories.active', value: true),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(ean: 'bar', stock:1, active: true),
                    new Category(ean: 'bar-2', stock:2, active: false),
                ]),
            ]),
        ];
    }

    final public static function objectListFloatCases(): \Generator
    {
        yield 'list object float field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(price: 1.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(price: 20.1),
                    new Category(price: 22.2),
                    new Category(price: 24.2),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'categories.price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(price: 1.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
            ]),
        ];
        yield 'list object float field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(price: 1.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(price: 20.1),
                    new Category(price: 22.2),
                    new Category(price: 24.2),
                ]),
            ]),

            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'categories.price', value: [10.1, 22.2]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(price: 20.1),
                    new Category(price: 22.2),
                    new Category(price: 24.2),
                ]),
            ]),
        ];
        yield 'list object float field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(price: 1.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(price: 20.1),
                    new Category(price: 22.2),
                    new Category(price: 24.2),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'categories.price', value: 22.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', categories: [
                    new Category(price: 20.1),
                    new Category(price: 22.2),
                    new Category(price: 24.2),
                ]),
            ]),
        ];
        yield 'list object float field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(price: 1.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(price: 20.1),
                    new Category(price: 22.2),
                    new Category(price: 24.2),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'categories.price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(price: 1.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
            ]),
        ];
        yield 'list object float field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(price: 1.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(price: 20.1),
                    new Category(price: 22.2),
                    new Category(price: 24.2),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'categories.price', value: 2.2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(price: 20.1),
                    new Category(price: 22.2),
                    new Category(price: 24.2),
                ]),
            ]),
        ];
        yield 'list object float field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(price: 1.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(price: 20.1),
                    new Category(price: 22.2),
                    new Category(price: 24.2),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'categories.price', value: 20.1),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(price: 1.1),
                    new Category(price: 2.2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(price: 10.1),
                    new Category(price: 2.2),
                ]),
            ]),
        ];
    }

    final public static function objectListIntCases(): \Generator
    {
        yield 'list object int field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(stock: 1),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(stock: 20),
                    new Category(stock: 22),
                    new Category(stock: 24),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'categories.stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(stock: 1),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
            ]),
        ];
        yield 'list object int field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(stock: 1),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(stock: 20),
                    new Category(stock: 22),
                    new Category(stock: 24),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'categories.stock', value: [10, 22]),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(stock: 20),
                    new Category(stock: 22),
                    new Category(stock: 24),
                ]),
            ]),
        ];
        yield 'list object int field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(stock: 1),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(stock: 20),
                    new Category(stock: 22),
                    new Category(stock: 24),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'categories.stock', value: 22),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', categories: [
                    new Category(stock: 20),
                    new Category(stock: 22),
                    new Category(stock: 24),
                ]),
            ]),
        ];
        yield 'list object int field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(stock: 1),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(stock: 20),
                    new Category(stock: 22), new Category(stock: 24),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'categories.stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(stock: 1),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
            ]),
        ];
        yield 'list object int field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(stock: 1),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(stock: 20),
                    new Category(stock: 22),
                    new Category(stock: 24),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'categories.stock', value: 2),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(stock: 20),
                    new Category(stock: 22),
                    new Category(stock: 24),
                ]),
            ]),
        ];
        yield 'list object int field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(stock: 1),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(stock: 20),
                    new Category(stock: 22),
                    new Category(stock: 24),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'categories.stock', value: 20),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(stock: 1),
                    new Category(stock: 2),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(stock: 10),
                    new Category(stock: 2),
                ]),
            ]),
        ];
    }

    final public static function objectListBoolCases(): \Generator
    {
        yield 'object list bool field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(active: true),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(active: false),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(active: false),
                    new Category(active: true),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'categories.active', value: true),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(active: true),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(active: false),
                    new Category(active: true),
                ]),
            ]),
        ];
    }

    final public static function objectListDateCases(): \Generator
    {
        yield 'list object date field, gte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(changed: '2021-01-01 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(changed: '2021-01-20 00:00:00.000'),
                    new Category(changed: '2021-01-22 00:00:00.000'),
                    new Category(changed: '2021-01-24 00:00:00.000'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gte(field: 'categories.changed', value: '2021-01-22 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key3', categories: [
                    new Category(changed: '2021-01-20 00:00:00.000'),
                    new Category(changed: '2021-01-22 00:00:00.000'),
                    new Category(changed: '2021-01-24 00:00:00.000'),
                ]),
            ]),
        ];
        yield 'list object date field, lte filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(changed: '2021-01-01 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(changed: '2021-01-20 00:00:00.000'),
                    new Category(changed: '2021-01-22 00:00:00.000'),
                    new Category(changed: '2021-01-24 00:00:00.000'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lte(field: 'categories.changed', value: '2021-01-02 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(changed: '2021-01-01 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
            ]),
        ];
        yield 'list object date field, gt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(changed: '2021-01-01 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(changed: '2021-01-20 00:00:00.000'),
                    new Category(changed: '2021-01-22 00:00:00.000'),
                    new Category(changed: '2021-01-24 00:00:00.000'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Gt(field: 'categories.changed', value: '2021-01-02 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(changed: '2021-01-20 00:00:00.000'),
                    new Category(changed: '2021-01-22 00:00:00.000'),
                    new Category(changed: '2021-01-24 00:00:00.000'),
                ]),
            ]),
        ];
        yield 'list object date field, lt filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(changed: '2021-01-01 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(changed: '2021-01-20 00:00:00.000'),
                    new Category(changed: '2021-01-22 00:00:00.000'),
                    new Category(changed: '2021-01-24 00:00:00.000'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Lt(field: 'categories.changed', value: '2021-01-20 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(changed: '2021-01-01 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
            ]),
        ];
        yield 'list object date field, equals filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(changed: '2021-01-01 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(changed: '2021-01-20 00:00:00.000'),
                    new Category(changed: '2021-01-22 00:00:00.000'),
                    new Category(changed: '2021-01-24 00:00:00.000'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Equals(field: 'categories.changed', value: '2021-01-02 00:00:00.000'),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key1', categories: [
                    new Category(changed: '2021-01-01 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
            ]),
        ];
        yield 'list object date field, equals any filter' => [
            'input' => new Documents([
                new Product(key: 'key1', categories: [
                    new Category(changed: '2021-01-01 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(changed: '2021-01-20 00:00:00.000'),
                    new Category(changed: '2021-01-22 00:00:00.000'),
                    new Category(changed: '2021-01-24 00:00:00.000'),
                ]),
            ]),
            'criteria' => new Criteria(
                filters: [
                    new Any(field: 'categories.changed', value: ['2021-01-10 00:00:00.000', '2021-01-22 00:00:00.000']),
                ]
            ),
            'expected' => new Result([
                new Product(key: 'key2', categories: [
                    new Category(changed: '2021-01-10 00:00:00.000'),
                    new Category(changed: '2021-01-02 00:00:00.000'),
                ]),
                new Product(key: 'key3', categories: [
                    new Category(changed: '2021-01-20 00:00:00.000'),
                    new Category(changed: '2021-01-22 00:00:00.000'),
                    new Category(changed: '2021-01-24 00:00:00.000'),
                ]),
            ]),
        ];
    }

    final public static function keysCases(): \Generator
    {
        yield 'keys and values' => [
            'input' => new Documents([
                new Product(key: 'key1'),
                new Product(key: 'key2'),
                new Product(key: 'key3'),
            ]),
            'criteria' => new Criteria(
                primaries: ['key1', 'key2']
            ),
            'expected' => new Result([
                new Product(key: 'key1'),
                new Product(key: 'key2'),
            ]),
        ];
    }

    final public static function paginationCases(): \Generator
    {
        yield 'pagination' => [
            'input' => new Documents([
                new Product(key: 'key1'),
                new Product(key: 'key2'),
                new Product(key: 'key3'),
                new Product(key: 'key4'),
                new Product(key: 'key5'),
            ]),
            'criteria' => new Criteria(
                paging: new Page(page: 2, limit: 2)
            ),
            'expected' => new Result([
                new Product(key: 'key3'),
                new Product(key: 'key4'),
            ]),
        ];
    }
}
