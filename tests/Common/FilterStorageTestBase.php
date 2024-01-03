<?php

namespace Shopware\StorageTests\Common;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Filter\FilterStorage;
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
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\StorageContext;

abstract class FilterStorageTestBase extends TestCase
{
    public const TEST_STORAGE = 'test_storage';

    abstract public function getStorage(): FilterStorage;

    #[DataProvider('storageProvider')]
    final public function testStorage(Documents $input, FilterCriteria $criteria, FilterResult $expected): void
    {
        $storage = $this->getStorage();

        $storage->store($input);

        $loaded = $storage->read($criteria, new StorageContext(languages: ['en', 'de']));

        static::assertEquals($expected, $loaded);
    }

    #[DataProvider('debugProvider')]
    final public function testDebug(
        Documents $input,
        FilterCriteria $criteria,
        FilterResult $expected
    ): void {
        self::markTestSkipped('Just for debugging purposes to run single cases');

        $storage = $this->getStorage();

        $storage->store($input);

        $loaded = $storage->read($criteria, new StorageContext(languages: ['en', 'de']));

        static::assertEquals($expected, $loaded);
    }

    final public static function debugProvider(): \Generator
    {
        yield 'Test object field with null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: null),
                self::document(key: 'key4', objectField: ['foo' => null]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectField.foo', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key4', objectField: ['foo' => null]),
            ])
        ];
        yield 'Test object field with nested null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: null),
                self::document(key: 'key4', objectField: ['foo' => null]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: null),
            ])
        ];
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

        $criteria = new FilterCriteria(
            keys: $input->keys()
        );

        $loaded = $storage->read($criteria, new StorageContext(languages: ['en', 'de']));

        $expected = new FilterResult($expected);

        static::assertEquals($expected, $loaded);
    }

    final public static function removeProvider(): \Generator
    {
        yield 'Test call remove with empty storage' => [
            'input' => new Documents(),
            'remove' => ['key1', 'key2'],
            'expected' => []
        ];

        yield 'Test call remove with single key' => [
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

        yield 'Test call remove with multiple keys' => [
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


    final protected function getSchema(): Schema
    {
        return new Schema(
            source: self::TEST_STORAGE,
            fields: [
                'stringField' => ['type' => FieldType::STRING],
                'textField' => ['type' => FieldType::TEXT],
                'intField' => ['type' => FieldType::INT],
                'floatField' => ['type' => FieldType::FLOAT],
                'boolField' => ['type' => FieldType::BOOL],
                'dateField' => ['type' => FieldType::DATETIME],
                'listField' => ['type' => FieldType::LIST],

                'translatedString' => ['type' => FieldType::STRING, 'translated' => true],
                'translatedText' => ['type' => FieldType::TEXT, 'translated' => true],
                'translatedInt' => ['type' => FieldType::INT, 'translated' => true],
                'translatedFloat' => ['type' => FieldType::FLOAT, 'translated' => true],
                'translatedBool' => ['type' => FieldType::BOOL, 'translated' => true],
                'translatedDate' => ['type' => FieldType::DATETIME, 'translated' => true],
                'translatedList' => ['type' => FieldType::LIST, 'translated' => true],

                'objectField' => [
                    'type' => FieldType::OBJECT,
                    'fields' => [
                        'foo' => ['type' => FieldType::STRING],
                        'fooInt' => ['type' => FieldType::INT],
                        'fooFloat' => ['type' => FieldType::FLOAT],
                        'fooBool' => ['type' => FieldType::BOOL],
                        'fooDate' => ['type' => FieldType::DATETIME],
                        'translatedFoo' => ['type' => FieldType::STRING, 'translated' => true],
                        'translatedFooInt' => ['type' => FieldType::INT, 'translated' => true],
                        'translatedFooFloat' => ['type' => FieldType::FLOAT, 'translated' => true],
                        'translatedFooBool' => ['type' => FieldType::BOOL, 'translated' => true],
                        'translatedFooDate' => ['type' => FieldType::DATETIME, 'translated' => true],
                        'fooObj' => [
                            'type' => FieldType::OBJECT,
                            'fields' => [
                                'bar' => ['type' => FieldType::STRING],
                            ]
                        ],
                    ]
                ],
                'objectListField' => [
                    'type' => FieldType::OBJECT_LIST,
                    'fields' => [
                        'foo' => ['type' => FieldType::STRING],
                        'fooInt' => ['type' => FieldType::INT],
                        'fooFloat' => ['type' => FieldType::FLOAT],
                        'fooBool' => ['type' => FieldType::BOOL],
                        'fooDate' => ['type' => FieldType::DATETIME],

                        'translatedFoo' => ['type' => FieldType::STRING, 'translated' => true],
                        'translatedFooInt' => ['type' => FieldType::INT, 'translated' => true],
                        'translatedFooFloat' => ['type' => FieldType::FLOAT, 'translated' => true],
                        'translatedFooBool' => ['type' => FieldType::BOOL, 'translated' => true],
                        'translatedFooDate' => ['type' => FieldType::DATETIME, 'translated' => true],

                        'fooObj' => [
                            'type' => FieldType::OBJECT,
                            'fields' => [
                                'bar' => ['type' => FieldType::STRING],
                                'translatedBar' => ['type' => FieldType::STRING, 'translated' => true],
                            ]
                        ],
                    ]
                ],
            ]
        );
    }

    final public static function storageProvider(): \Generator
    {
        yield 'Smoke test' => [
            'input' => new Documents(),
            'criteria' => new FilterCriteria(),
            'expected' => new FilterResult([])
        ];
        yield 'Test keys and values' => [
            'input' => new Documents([
                self::document(key: 'key1'),
                self::Document(key: 'key2'),
                self::Document(key: 'key3'),
            ]),
            'criteria' => new FilterCriteria(
                keys: ['key1', 'key2']
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1'),
                self::document(key: 'key2'),
            ])
        ];
        yield 'Test pagination' => [
            'input' => new Documents([
                self::document(key: 'key1'),
                self::document(key: 'key2'),
                self::document(key: 'key3'),
                self::document(key: 'key4'),
                self::document(key: 'key5'),
            ]),
            'criteria' => new FilterCriteria(
                page: 2,
                limit: 2
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3'),
                self::document(key: 'key4'),
            ])
        ];

        yield 'Test string field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'foo'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Equals(field: 'stringField', value: 'foo')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key3', stringField: 'foo'),
            ])
        ];
        yield 'Test string field equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Any(field: 'stringField', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
            ])
        ];
        yield 'Test string field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Not(field: 'stringField', value: 'foo')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ])
        ];
        yield 'Test string field not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Neither(field: 'stringField', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', stringField: 'baz'),
            ])
        ];
        yield 'Test string field contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Contains(field: 'stringField', value: 'ba')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ])
        ];
        yield 'Test string field starts-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Prefix(field: 'stringField', value: 'ba')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ])
        ];
        yield 'Test string field ends-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'foo-bar'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Suffix(field: 'stringField', value: 'bar')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'foo-bar'),
            ])
        ];
        yield 'Test string field gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Gte(field: 'stringField', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ])
        ];
        yield 'Test string field lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Lte(field: 'stringField', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
            ])
        ];
        yield 'Test string field gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Gt(field: 'stringField', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', stringField: 'c'),
            ])
        ];
        yield 'Test string field lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Lt(field: 'stringField', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', stringField: 'a'),
            ])
        ];
        yield 'Test string field gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
                self::document(key: 'key4', stringField: 'd'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Gte(field: 'stringField', value: 'b'),
                    new Lte(field: 'stringField', value: 'c'),
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ])
        ];
        yield 'Test string field null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Equals(field: 'stringField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', stringField: null)
            ])
        ];
        yield 'Test string field null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Not(field: 'stringField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', stringField: 'foo'),
                self::document('key2', stringField: 'bar')
            ])
        ];

        yield 'Test text field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'foo'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Equals(field: 'textField', value: 'foo')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key3', textField: 'foo'),
            ])
        ];
        yield 'Test text field equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Any(field: 'textField', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
            ])
        ];
        yield 'Test text field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Not(field: 'textField', value: 'foo')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ])
        ];
        yield 'Test text field not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Neither(field: 'textField', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', textField: 'baz'),
            ])
        ];
        yield 'Test text field contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Contains(field: 'textField', value: 'ba')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ])
        ];
        yield 'Test text field starts-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Prefix(field: 'textField', value: 'ba')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ])
        ];
        yield 'Test text field ends-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'foo-bar'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Suffix(field: 'textField', value: 'bar')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'foo-bar'),
            ])
        ];
        yield 'Test text field gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Gte(field: 'textField', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ])
        ];
        yield 'Test text field lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Lte(field: 'textField', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
            ])
        ];
        yield 'Test text field gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Gt(field: 'textField', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', textField: 'c'),
            ])
        ];
        yield 'Test text field lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Lt(field: 'textField', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', textField: 'a'),
            ])
        ];
        yield 'Test text field gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
                self::document(key: 'key4', textField: 'd'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Gte(field: 'textField', value: 'b'),
                    new Lte(field: 'textField', value: 'c'),
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ])
        ];

        yield 'Test date field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Equals(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
            ])
        ];
        yield 'Test date field equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Any(field: 'dateField', value: ['2021-01-01', '2021-01-02'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
            ])
        ];
        yield 'Test date field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Not(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'Test date field not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Neither(field: 'dateField', value: ['2021-01-01', '2021-01-02'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'Test date field gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Gte(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'Test date field lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Lte(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
            ])
        ];
        yield 'Test date field gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Gt(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'Test date field lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Lt(field: 'dateField', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
            ])
        ];
        yield 'Test date field gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
                self::document(key: 'key4', dateField: '2021-01-04 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Gte(field: 'dateField', value: '2021-01-02'),
                    new Lte(field: 'dateField', value: '2021-01-03'),
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'Test date field null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01'),
                self::document(key: 'key2', dateField: '2021-01-02'),
                self::document(key: 'key3', dateField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'dateField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', dateField: null)
            ])
        ];
        yield 'Test date field null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'dateField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', dateField: '2021-01-01 00:00:00.000'),
                self::document('key2', dateField: '2021-01-02 00:00:00.000')
            ])
        ];

        yield 'Test int field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'intField', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', intField: 2),
            ])
        ];
        yield 'Test int field equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'intField', value: [1, 2])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
            ])
        ];
        yield 'Test int field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'intField', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'Test int field not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'intField', value: [1, 2])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'Test int field gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'intField', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'Test int field lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'intField', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
            ])
        ];
        yield 'Test int field gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'intField', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'Test int field lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'intField', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', intField: 1),
            ])
        ];
        yield 'Test int field gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
                self::document(key: 'key4', intField: 4),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'intField', value: 2),
                     new Lte(field: 'intField', value: 3),
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'Test int field null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'intField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', intField: null)
            ])
        ];
        yield 'Test int field null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'intField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', intField: 1),
                self::document('key2', intField: 2)
            ])
        ];

        yield 'Test float field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', floatField: 2.2),
            ])
        ];
        yield 'Test float field equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'floatField', value: [1.1, 2.2])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
            ])
        ];
        yield 'Test float field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'Test float field not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'floatField', value: [1.1, 2.2])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'Test float field gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'Test float field lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
            ])
        ];
        yield 'Test float field gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'Test float field lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'floatField', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', floatField: 1.1),
            ])
        ];
        yield 'Test float field gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
                self::document(key: 'key4', floatField: 4.4),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    new Gte(field: 'floatField', value: 2.2),
                     new Lte(field: 'floatField', value: 3.3),
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'Test float field null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'floatField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', floatField: null)
            ])
        ];
        yield 'Test float field null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'floatField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', floatField: 1.1),
                self::document('key2', floatField: 2.2)
            ])
        ];

        yield 'Test object field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectField.foo', value: 'baz')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
            ])
        ];
        yield 'Test object field equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'objectField.foo', value: ['baz', 'qux'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ])
        ];
        yield 'Test object field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'objectField.foo', value: 'baz')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ])
        ];
        yield 'Test object field not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'objectField.foo', value: ['baz', 'qux'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
            ])
        ];
        yield 'Test object field contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Contains(field: 'objectField.foo', value: 'ba')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
            ])
        ];
        yield 'Test object field gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'objectField.foo', value: 'baz')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ])
        ];
        yield 'Test object field lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'objectField.foo', value: 'baz')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
            ])
        ];
        yield 'Test object field gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'objectField.foo', value: 'baz')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ])
        ];
        yield 'Test object field lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'objectField.foo', value: 'baz')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
            ])
        ];
//        yield 'Test object field null value equals filter' => [
//            'input' => new Documents([
//                self::document(key: 'key1', objectField: ['foo' => 'bar']),
//                self::document(key: 'key2', objectField: ['foo' => 'baz']),
//                self::document(key: 'key3', objectField: null),
//                self::document(key: 'key4', objectField: ['foo' => null]),
//            ]),
//            'criteria' => new FilterCriteria(
//                filters: [
//                     new Equals(field: 'objectField.foo', value: null)
//                ]
//            ),
//            'expected' => new FilterResult([
//                self::document(key: 'key4', objectField: ['foo' => null]),
//            ])
//        ];
//        yield 'Test object field nested null value equals filter' => [
//            'input' => new Documents([
//                self::document(key: 'key1', objectField: ['foo' => 'bar']),
//                self::document(key: 'key2', objectField: ['foo' => 'baz']),
//                self::document(key: 'key3', objectField: null),
//                self::document(key: 'key4', objectField: ['foo' => null]),
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
        yield 'Test object field null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'objectField.foo', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', objectField: ['foo' => 'bar']),
                self::document('key2', objectField: ['foo' => 'baz'])
            ])
        ];

        yield 'Test object field equals filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectField.fooInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
            ])
        ];
        yield 'Test object field equals any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'objectField.fooInt', value: [1, 2])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
            ])
        ];
        yield 'Test object field not filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'objectField.fooInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ])
        ];
        yield 'Test object field not any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'objectField.fooInt', value: [1, 2])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ])
        ];
        yield 'Test object field gte filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'objectField.fooInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ])
        ];
        yield 'Test object field lte filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'objectField.fooInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
            ])
        ];
        yield 'Test object field gt filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'objectField.fooInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ])
        ];
        yield 'Test object field lt filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'objectField.fooInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
            ])
        ];
        yield 'Test object field gte and lte filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
                self::document(key: 'key4', objectField: ['fooInt' => 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'objectField.fooInt', value: 2),
                     new Lte(field: 'objectField.fooInt', value: 3),
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ])
        ];

        yield 'Test object field equals filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectField.fooFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
            ])
        ];
        yield 'Test object field equals any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'objectField.fooFloat', value: [1.1, 2.2])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
            ])
        ];
        yield 'Test object field not filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'objectField.fooFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ])
        ];
        yield 'Test object field not any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'objectField.fooFloat', value: [1.1, 2.2])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ])
        ];
        yield 'Test object field gte filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'objectField.fooFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ])
        ];
        yield 'Test object field lte filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'objectField.fooFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
            ])
        ];
        yield 'Test object field gt filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'objectField.fooFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ])
        ];
        yield 'Test object field lt filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'objectField.fooFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
            ])
        ];
        yield 'Test object field gte and lte filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
                self::document(key: 'key4', objectField: ['fooFloat' => 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'objectField.fooFloat', value: 2.2),
                     new Lte(field: 'objectField.fooFloat', value: 3.3),
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ])
        ];

        yield 'Test object field equals filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectField.fooDate', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test object field equals any filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'objectField.fooDate', value: ['2021-01-02', '2021-01-03'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'Test object field not filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'objectField.fooDate', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'Test object field not any filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'objectField.fooDate', value: ['2021-01-02', '2021-01-03'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
            ])
        ];
        yield 'Test object field gte filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'objectField.fooDate', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'Test object field lte filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'objectField.fooDate', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test object field gt filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'objectField.fooDate', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'Test object field lt filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'objectField.fooDate', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
            ])
        ];
        yield 'Test object field gte and lte filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key4', objectField: ['fooDate' => '2021-01-04 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'objectField.fooDate', value: '2021-01-02'),
                     new Lte(field: 'objectField.fooDate', value: '2021-01-03'),
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ])
        ];

        yield 'Test list field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'listField', value: 'baz')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: ['foo', 'baz']),
            ])
        ];
        yield 'Test list field equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'listField', value: ['baz', 'qux'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ])
        ];
        yield 'Test list field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'listField', value: 'baz')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ])
        ];
        yield 'Test list field not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'listField', value: ['baz', 'qux'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['foo', 'bar']),
            ])
        ];
        yield 'Test list field contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Contains(field: 'listField', value: 'ba')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
            ])
        ];
        yield 'Test list field null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'listField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', listField: null)
            ])
        ];
        yield 'Test list field null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'listField', value: null)
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', listField: [1, 2]),
                self::document('key2', listField: [1, 3])
            ])
        ];

        yield 'Test list field equals filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'listField', value: 3)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: [1, 3]),
            ])
        ];
        yield 'Test list field equals any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'listField', value: [3, 4])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ])
        ];
        yield 'Test list field not filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'listField', value: 3)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key3', listField: [1, 4]),
            ])
        ];
        yield 'Test list field not any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'listField', value: [3, 4])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: [1, 2]),
            ])
        ];
        yield 'Test list field equals filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'listField', value: 3.3)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: [1.1, 3.3]),
            ])
        ];
        yield 'Test list field equals any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'listField', value: [3.3, 4.4])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ])
        ];
        yield 'Test list field not filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'listField', value: 3.3)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ])
        ];
        yield 'Test list field not any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'listField', value: [3.3, 4.4])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: [1.1, 2.2]),
            ])
        ];
        yield 'Test list field equals filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'listField', value: '2021-01-03')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
            ])
        ];
        yield 'Test list field equals any filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'listField', value: ['2021-01-03', '2021-01-04'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ])
        ];
        yield 'Test list field not filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'listField', value: '2021-01-03')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ])
        ];
        yield 'Test list field not any filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'listField', value: ['2021-01-03', '2021-01-04'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
            ])
        ];
        yield 'Test list field contains filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Contains(field: 'listField', value: '2021-01-02')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
            ])
        ];

        yield 'Test list object field equals filter and string value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectListField.foo', value: 'baz-2')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ])
        ];
        yield 'Test list object field equals any filter and string value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'objectListField.foo', value: ['bar-2', 'qux-2'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ])
        ];
        yield 'Test list object field contains filter and string value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Contains(field: 'objectListField.foo', value: 'baz')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ])
        ];
        yield 'Test list object field starts-with filter and string value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Prefix(field: 'objectListField.foo', value: 'qu')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ])
        ];
        yield 'Test list object field ends-with filter and string value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Suffix(field: 'objectListField.foo', value: 'z-2')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ])
        ];

        yield 'Test list object field equals filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectListField.fooInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
            ])
        ];
        yield 'Test list object field equals any filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'objectListField.fooInt', value: [10, 22])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ])
        ];
        yield 'Test list object field gte filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'objectListField.fooInt', value: 22)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ])
        ];
        yield 'Test list object field lte filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'objectListField.fooInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
            ])
        ];
        yield 'Test list object field gt filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'objectListField.fooInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ])
        ];
        yield 'Test list object field lt filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'objectListField.fooInt', value: 20)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
            ])
        ];

        yield 'Test list object field equals filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectListField.fooFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
            ])
        ];
        yield 'Test list object field equals any filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),

            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'objectListField.fooFloat', value: [10.1, 22.2])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ])
        ];
        yield 'Test list object field gte filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'objectListField.fooFloat', value: 22.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ])
        ];
        yield 'Test list object field lte filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'objectListField.fooFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
            ])
        ];
        yield 'Test list object field gt filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'objectListField.fooFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ])
        ];
        yield 'Test list object field lt filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'objectListField.fooFloat', value: 20.1)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
            ])
        ];

        yield 'Test list object field equals filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectListField.fooDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
            ])
        ];
        yield 'Test list object field equals any filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'objectListField.fooDate', value: ['2021-01-10 00:00:00.000', '2021-01-22 00:00:00.000'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ])
        ];

        yield 'Test list object field gte filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'objectListField.fooDate', value: '2021-01-22 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ])
        ];
        yield 'Test list object field lte filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'objectListField.fooDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
            ])
        ];
        yield 'Test list object field gt filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'objectListField.fooDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ])
        ];
        yield 'Test list object field lt filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'objectListField.fooDate', value: '2021-01-20 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
            ])
        ];

        yield 'Test nested object' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooObj' => ['bar' => 'baz']]),
                self::document(key: 'key2', objectField: ['fooObj' => ['bar' => 'qux']]),
                self::document(key: 'key3', objectField: ['fooObj' => ['bar' => 'quux']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'objectField.fooObj.bar', value: 'qux')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooObj' => ['bar' => 'qux']]),
            ])
        ];

        yield 'Test translated string field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'translatedString', value: 'foo')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ])
        ];
        yield 'Test translated string field equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'translatedString', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
            ])
        ];
        yield 'Test translated string field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'translatedString', value: 'foo')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ])
        ];
        yield 'Test translated string field not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'translatedString', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ])
        ];
        yield 'Test translated string field contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'boo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'foo', 'de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Contains(field: 'translatedString', value: 'oo')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'boo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'foo', 'de' => 'bar']),
            ])
        ];
        yield 'Test translated string field starts-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Prefix(field: 'translatedString', value: 'foo')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
            ])
        ];
        yield 'Test translated string field ends-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'ob', 'de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Suffix(field: 'translatedString', value: 'o')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
            ])
        ];
        yield 'Test translated string field gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'translatedString', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ])
        ];
        yield 'Test translated string field gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'translatedString', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'c']),
            ])
        ];
        yield 'Test translated string field lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'translatedString', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ])
        ];
        yield 'Test translated string field lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'translatedString', value: 'b')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
            ])
        ];
        yield 'Test translated string field equals filter and empty string' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => '', 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'translatedString', value: 'foo')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ])
        ];

        yield 'Test translated int field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ])
        ];
        yield 'Test translated int field equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key4', translatedInt: ['de' => 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'translatedInt', value: [2, 3, 4])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key4', translatedInt: ['de' => 4]),
            ])
        ];
        yield 'Test translated int field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
            ])
        ];
        yield 'Test translated int field not-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'translatedInt', value: [1, 2])
                ]
            ),
            'expected' => new FilterResult([])
        ];
        yield 'Test translated int field gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
            ])
        ];
        yield 'Test translated int field gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 3]),
            ])
        ];
        yield 'Test translated int field lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ])
        ];
        yield 'Test translated int field lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ])
        ];

        yield 'Test translated float field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ])
        ];
        yield 'Test translated float field equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key4', translatedFloat: ['de' => 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'translatedFloat', value: [2.2, 3.3, 4.4])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key4', translatedFloat: ['de' => 4.4]),
            ])
        ];
        yield 'Test translated float field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
            ])
        ];
        yield 'Test translated float field not-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'translatedFloat', value: [1.1, 2.2])
                ]
            ),
            'expected' => new FilterResult([])
        ];
        yield 'Test translated float field gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
            ])
        ];
        yield 'Test translated float field gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
            ])
        ];
        yield 'Test translated float field lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ])
        ];
        yield 'Test translated float field lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ])
        ];

        yield 'Test translated bool field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'translatedBool', value: false)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ])
        ];
        yield 'Test translated bool field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'translatedBool', value: false)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
            ])
        ];

        yield 'Test translated date field equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'translatedDate', value: ['2021-01-02 00:00:00.000', '2021-01-03 00:00:00.000'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field not-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'translatedDate', value: ['2021-01-01 00:00:00.000', '2021-01-02 00:00:00.000'])
                ]
            ),
            'expected' => new FilterResult([])
        ];
        yield 'Test translated date field gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gte(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Gt(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lte(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Lt(field: 'translatedDate', value: '2021-01-02 00:00:00.000')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ])
        ];

        yield 'Test translated list field equals filter and string values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'translatedString', value: 'bar')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ])
        ];
        yield 'Test translated list field equals-any filter and string values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'baz']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'translatedString', value: ['bar', 'baz'])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'baz']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ])
        ];
        yield 'Test translated list field not filter and string values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'translatedString', value: 'bar')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
            ])
        ];
        yield 'Test translated list field not-any filter and string values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'translatedString', value: ['foo', 'bar'])
                ]
            ),
            'expected' => new FilterResult([])
        ];
        yield 'Test translated list field contains filter and string values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Contains(field: 'translatedString', value: 'ba')
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ])
        ];

        yield 'Test translated list field equals filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ])
        ];
        yield 'Test translated list field equals-any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'translatedInt', value: [2, 3])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ])
        ];
        yield 'Test translated list field not filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'translatedInt', value: 2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
            ])
        ];
        yield 'Test translated list field not-any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'translatedInt', value: [1, 2])
                ]
            ),
            'expected' => new FilterResult([])
        ];

        yield 'Test translated list field equals filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ])
        ];
        yield 'Test translated list field equals-any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'translatedFloat', value: [2.2, 3.3])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ])
        ];
        yield 'Test translated list field not filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'translatedFloat', value: 2.2)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
            ])
        ];
        yield 'Test translated list field not-any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'translatedFloat', value: [1.1, 2.2])
                ]
            ),
            'expected' => new FilterResult([])
        ];

        yield 'Test translated list field equals filter and bool values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Equals(field: 'translatedBool', value: false)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ])
        ];
        yield 'Test translated list field equals-any filter and bool values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => true]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Any(field: 'translatedBool', value: [false, true])
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => true]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ])
        ];
        yield 'Test translated list field not filter and bool values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Not(field: 'translatedBool', value: false)
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
            ])
        ];
        yield 'Test translated list field not-any filter and bool values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                     new Neither(field: 'translatedBool', value: [true, false])
                ]
            ),
            'expected' => new FilterResult([])
        ];
    }

    /**
     * @param array<string, mixed>|null $objectField
     * @param array<mixed>|null $listField
     * @param array<array<string, mixed>>|null $objectListField
     * @param array<string, string|null>|null $translatedString
     * @param array<string, int|null>|null $translatedInt
     * @param array<string, float|null>|null $translatedFloat
     * @param array<string, bool|null>|null $translatedBool
     * @param array<string, string|null>|null $translatedDate
     */
    protected static function document(
        string $key,
        ?string $stringField = null,
        ?string $textField = null,
        ?string $dateField = null,
        ?int $intField = null,
        ?float $floatField = null,
        ?array $objectField = null,
        ?array $listField = null,
        ?array $objectListField = null,
        ?array $translatedString = null,
        ?array $translatedInt = null,
        ?array $translatedFloat = null,
        ?array $translatedBool = null,
        ?array $translatedDate = null,
    ): Document {
        return new Document(
            key: $key,
            data: [
                'stringField' => $stringField,
                'textField' => $textField,
                'dateField' => $dateField,
                'intField' => $intField,
                'floatField' => $floatField,
                'objectField' => $objectField,
                'listField' => $listField,
                'objectListField' => $objectListField,
                'translatedString' => $translatedString,
                'translatedInt' => $translatedInt,
                'translatedFloat' => $translatedFloat,
                'translatedBool' => $translatedBool,
                'translatedDate' => $translatedDate,
            ]
        );
    }
}
