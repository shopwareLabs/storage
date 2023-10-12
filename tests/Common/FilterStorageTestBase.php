<?php

namespace Shopware\StorageTests\Common;

use PHPUnit\Framework\TestCase;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\StorageContext;

abstract class FilterStorageTestBase extends TestCase
{
    abstract public function getStorage(): FilterStorage;

    /**
     * @dataProvider debugProvider
     */
    final public function testDebug(
        Documents $input,
        FilterCriteria $criteria,
        FilterResult $expected
    ): void
    {
        $storage = $this->getStorage();

        $storage->store($input);

        $loaded = $storage->read($criteria, new StorageContext(languages: ['en', 'de']));

        static::assertEquals($expected, $loaded);
    }

    final public static function debugProvider(): \Generator
    {
        // can be used for debugging purposes
//        yield 'Smoke test' => [
//            'input' => new Documents(),
//            'criteria' => new FilterCriteria(),
//            'expected' => new FilterResult([])
//        ];

        yield 'Test translated bool field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedBool', 'type' => 'equals', 'value' => false]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ])
        ];

//
    }

    /**
     * @dataProvider removeProvider
     */
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

    /**
     * @dataProvider storageProvider
     */
    final public function testStorage(Documents $input, FilterCriteria $criteria, FilterResult $expected): void
    {
        $storage = $this->getStorage();

        $storage->store($input);

        $loaded = $storage->read($criteria, new StorageContext(languages: ['en', 'de']));

        static::assertEquals($expected, $loaded);
    }

    final protected function getSchema(): Schema
    {
        return new Schema(
            source: 'test_storage',
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

    final public function storageProvider(): \Generator
    {
        yield 'Smoke test' => [
            'input' => new Documents(),
            'criteria' => new FilterCriteria(),
            'expected' => new FilterResult([])
        ];
        yield 'Test with keys and values' => [
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

        yield 'Test string field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'foo'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'stringField', 'type' => 'equals', 'value' => 'foo']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key3', stringField: 'foo'),
            ])
        ];
        yield 'Test string field with equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'stringField', 'type' => 'equals-any', 'value' => ['foo', 'bar']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
            ])
        ];
        yield 'Test string field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'stringField', 'type' => 'not', 'value' => 'foo']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ])
        ];
        yield 'Test string field with not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'stringField', 'type' => 'not-any', 'value' => ['foo', 'bar']]
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
                    ['field' => 'stringField', 'type' => 'contains', 'value' => 'ba']
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
                    ['field' => 'stringField', 'type' => 'starts-with', 'value' => 'ba']
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
                    ['field' => 'stringField', 'type' => 'ends-with', 'value' => 'bar']
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
                    ['field' => 'stringField', 'type' => 'gte', 'value' => 'b']
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
                    ['field' => 'stringField', 'type' => 'lte', 'value' => 'b']
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
                    ['field' => 'stringField', 'type' => 'gt', 'value' => 'b']
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
                    ['field' => 'stringField', 'type' => 'lt', 'value' => 'b']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', stringField: 'a'),
            ])
        ];
        yield 'Test string field with gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'a'),
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
                self::document(key: 'key4', stringField: 'd'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'stringField', 'type' => 'gte', 'value' => 'b'],
                    ['field' => 'stringField', 'type' => 'lte', 'value' => 'c'],
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', stringField: 'b'),
                self::document(key: 'key3', stringField: 'c'),
            ])
        ];
        yield 'Test string field with null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'stringField', 'type' => 'equals', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', stringField: null)
            ])
        ];
        yield 'Test string field with null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', stringField: 'foo'),
                self::document(key: 'key2', stringField: 'bar'),
                self::document(key: 'key3', stringField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'stringField', 'type' => 'not', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', stringField: 'foo'),
                self::document('key2', stringField: 'bar')
            ])
        ];

        yield 'Test text field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'foo'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'textField', 'type' => 'equals', 'value' => 'foo']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key3', textField: 'foo'),
            ])
        ];
        yield 'Test text field with equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'textField', 'type' => 'equals-any', 'value' => ['foo', 'bar']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
            ])
        ];
        yield 'Test text field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'textField', 'type' => 'not', 'value' => 'foo']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ])
        ];
        yield 'Test text field with not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'foo'),
                self::document(key: 'key2', textField: 'bar'),
                self::document(key: 'key3', textField: 'baz'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'textField', 'type' => 'not-any', 'value' => ['foo', 'bar']]
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
                    ['field' => 'textField', 'type' => 'contains', 'value' => 'ba']
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
                    ['field' => 'textField', 'type' => 'starts-with', 'value' => 'ba']
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
                    ['field' => 'textField', 'type' => 'ends-with', 'value' => 'bar']
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
                    ['field' => 'textField', 'type' => 'gte', 'value' => 'b']
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
                    ['field' => 'textField', 'type' => 'lte', 'value' => 'b']
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
                    ['field' => 'textField', 'type' => 'gt', 'value' => 'b']
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
                    ['field' => 'textField', 'type' => 'lt', 'value' => 'b']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', textField: 'a'),
            ])
        ];
        yield 'Test text field with gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', textField: 'a'),
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
                self::document(key: 'key4', textField: 'd'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'textField', 'type' => 'gte', 'value' => 'b'],
                    ['field' => 'textField', 'type' => 'lte', 'value' => 'c'],
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', textField: 'b'),
                self::document(key: 'key3', textField: 'c'),
            ])
        ];

        yield 'Test date field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'equals', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
            ])
        ];
        yield 'Test date field with equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'equals-any', 'value' => ['2021-01-01', '2021-01-02']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
            ])
        ];
        yield 'Test date field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'not', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'Test date field with not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'not-any', 'value' => ['2021-01-01', '2021-01-02']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'Test date field with gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'gte', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'Test date field with lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'lte', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
            ])
        ];
        yield 'Test date field with gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'gt', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'Test date field with lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'lt', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
            ])
        ];
        yield 'Test date field with gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
                self::document(key: 'key4', dateField: '2021-01-04 00:00:00.000'),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'gte', 'value' => '2021-01-02'],
                    ['field' => 'dateField', 'type' => 'lte', 'value' => '2021-01-03'],
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: '2021-01-03 00:00:00.000'),
            ])
        ];
        yield 'Test date field with null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01'),
                self::document(key: 'key2', dateField: '2021-01-02'),
                self::document(key: 'key3', dateField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'equals', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', dateField: null)
            ])
        ];
        yield 'Test date field with null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', dateField: '2021-01-01 00:00:00.000'),
                self::document(key: 'key2', dateField: '2021-01-02 00:00:00.000'),
                self::document(key: 'key3', dateField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'dateField', 'type' => 'not', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', dateField: '2021-01-01 00:00:00.000'),
                self::document('key2', dateField: '2021-01-02 00:00:00.000')
            ])
        ];

        yield 'Test int field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'equals', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', intField: 2),
            ])
        ];
        yield 'Test int field with equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'equals-any', 'value' => [1, 2]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
            ])
        ];
        yield 'Test int field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'not', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'Test int field with not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'not-any', 'value' => [1, 2]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'Test int field with gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'gte', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'Test int field with lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'lte', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
            ])
        ];
        yield 'Test int field with gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'gt', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'Test int field with lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'lt', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', intField: 1),
            ])
        ];
        yield 'Test int field with gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
                self::document(key: 'key4', intField: 4),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'gte', 'value' => 2],
                    ['field' => 'intField', 'type' => 'lte', 'value' => 3],
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: 3),
            ])
        ];
        yield 'Test int field with null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'equals', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', intField: null)
            ])
        ];
        yield 'Test int field with null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', intField: 1),
                self::document(key: 'key2', intField: 2),
                self::document(key: 'key3', intField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'intField', 'type' => 'not', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', intField: 1),
                self::document('key2', intField: 2)
            ])
        ];

        yield 'Test float field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'equals', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', floatField: 2.2),
            ])
        ];
        yield 'Test float field with equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'equals-any', 'value' => [1.1, 2.2]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
            ])
        ];
        yield 'Test float field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'not', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'Test float field with not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'not-any', 'value' => [1.1, 2.2]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'Test float field with gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'gte', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'Test float field with lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'lte', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
            ])
        ];
        yield 'Test float field with gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'gt', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'Test float field with lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'lt', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', floatField: 1.1),
            ])
        ];
        yield 'Test float field with gte and lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
                self::document(key: 'key4', floatField: 4.4),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'gte', 'value' => 2.2],
                    ['field' => 'floatField', 'type' => 'lte', 'value' => 3.3],
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: 3.3),
            ])
        ];
        yield 'Test float field with null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'equals', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', floatField: null)
            ])
        ];
        yield 'Test float field with null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', floatField: 1.1),
                self::document(key: 'key2', floatField: 2.2),
                self::document(key: 'key3', floatField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'floatField', 'type' => 'not', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', floatField: 1.1),
                self::document('key2', floatField: 2.2)
            ])
        ];

        yield 'Test object field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'equals', 'value' => 'baz']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
            ])
        ];
        yield 'Test object field with equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'equals-any', 'value' => ['baz', 'qux']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ])
        ];
        yield 'Test object field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'not', 'value' => 'baz']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ])
        ];
        yield 'Test object field with not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'not-any', 'value' => ['baz', 'qux']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
            ])
        ];
        yield 'Test object field with contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'contains', 'value' => 'ba']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
            ])
        ];
        yield 'Test object field with gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'gte', 'value' => 'baz']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ])
        ];
        yield 'Test object field with lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'lte', 'value' => 'baz']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
            ])
        ];
        yield 'Test object field with gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'gt', 'value' => 'baz']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ])
        ];
        yield 'Test object field with lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'lt', 'value' => 'baz']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
            ])
        ];
        yield 'Test object field with null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'equals', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
            ])
        ];
        yield 'Test object field with nested null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: ['foo' => null]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField', 'type' => 'equals', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['foo' => null])
            ])
        ];
        yield 'Test object field with null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['foo' => 'bar']),
                self::document(key: 'key2', objectField: ['foo' => 'baz']),
                self::document(key: 'key3', objectField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.foo', 'type' => 'not', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', objectField: ['foo' => 'bar']),
                self::document('key2', objectField: ['foo' => 'baz'])
            ])
        ];

        yield 'Test object field with equals filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooInt', 'type' => 'equals', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
            ])
        ];
        yield 'Test object field with equals any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooInt', 'type' => 'equals-any', 'value' => [1, 2]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
            ])
        ];
        yield 'Test object field with not filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooInt', 'type' => 'not', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ])
        ];
        yield 'Test object field with not any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooInt', 'type' => 'not-any', 'value' => [1, 2]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ])
        ];
        yield 'Test object field with gte filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooInt', 'type' => 'gte', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ])
        ];
        yield 'Test object field with lte filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooInt', 'type' => 'lte', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
            ])
        ];
        yield 'Test object field with gt filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooInt', 'type' => 'gt', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ])
        ];
        yield 'Test object field with lt filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooInt', 'type' => 'lt', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
            ])
        ];
        yield 'Test object field with gte and lte filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooInt' => 1]),
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
                self::document(key: 'key4', objectField: ['fooInt' => 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooInt', 'type' => 'gte', 'value' => 2],
                    ['field' => 'objectField.fooInt', 'type' => 'lte', 'value' => 3],
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooInt' => 2]),
                self::document(key: 'key3', objectField: ['fooInt' => 3]),
            ])
        ];

        yield 'Test object field with equals filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooFloat', 'type' => 'equals', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
            ])
        ];
        yield 'Test object field with equals any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooFloat', 'type' => 'equals-any', 'value' => [1.1, 2.2]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
            ])
        ];
        yield 'Test object field with not filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooFloat', 'type' => 'not', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ])
        ];
        yield 'Test object field with not any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooFloat', 'type' => 'not-any', 'value' => [1.1, 2.2]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ])
        ];
        yield 'Test object field with gte filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooFloat', 'type' => 'gte', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ])
        ];
        yield 'Test object field with lte filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooFloat', 'type' => 'lte', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
            ])
        ];
        yield 'Test object field with gt filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooFloat', 'type' => 'gt', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ])
        ];
        yield 'Test object field with lt filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooFloat', 'type' => 'lt', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
            ])
        ];
        yield 'Test object field with gte and lte filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooFloat' => 1.1]),
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
                self::document(key: 'key4', objectField: ['fooFloat' => 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooFloat', 'type' => 'gte', 'value' => 2.2],
                    ['field' => 'objectField.fooFloat', 'type' => 'lte', 'value' => 3.3],
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooFloat' => 2.2]),
                self::document(key: 'key3', objectField: ['fooFloat' => 3.3]),
            ])
        ];

        yield 'Test object field with equals filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooDate', 'type' => 'equals', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test object field with equals any filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooDate', 'type' => 'equals-any', 'value' => ['2021-01-02', '2021-01-03']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'Test object field with not filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooDate', 'type' => 'not', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'Test object field with not any filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooDate', 'type' => 'not-any', 'value' => ['2021-01-02', '2021-01-03']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
            ])
        ];
        yield 'Test object field with gte filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooDate', 'type' => 'gte', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'Test object field with lte filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooDate', 'type' => 'lte', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test object field with gt filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooDate', 'type' => 'gt', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'Test object field with lt filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooDate', 'type' => 'lt', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
            ])
        ];
        yield 'Test object field with gte and lte filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', objectField: ['fooDate' => '2021-01-01 00:00:00.000']),
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key4', objectField: ['fooDate' => '2021-01-04 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectField.fooDate', 'type' => 'gte', 'value' => '2021-01-02'],
                    ['field' => 'objectField.fooDate', 'type' => 'lte', 'value' => '2021-01-03'],
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooDate' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', objectField: ['fooDate' => '2021-01-03 00:00:00.000']),
            ])
        ];

        yield 'Test list field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'equals', 'value' => 'baz']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: ['foo', 'baz']),
            ])
        ];
        yield 'Test list field with equals any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'equals-any', 'value' => ['baz', 'qux']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ])
        ];
        yield 'Test list field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'not', 'value' => 'baz']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ])
        ];
        yield 'Test list field with not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'not-any', 'value' => ['baz', 'qux']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['foo', 'bar']),
            ])
        ];
        yield 'Test list field with contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
                self::document(key: 'key3', listField: ['foo', 'qux']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'contains', 'value' => 'ba']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['foo', 'bar']),
                self::document(key: 'key2', listField: ['foo', 'baz']),
            ])
        ];
        yield 'Test list field with null value equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'equals', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', listField: null)
            ])
        ];
        yield 'Test list field with null value not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: null),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'not', 'value' => null]
                ]
            ),
            'expected' => new FilterResult([
                self::document('key1', listField: [1, 2]),
                self::document('key2', listField: [1, 3])
            ])
        ];

        yield 'Test list field with equals filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'equals', 'value' => 3]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: [1, 3]),
            ])
        ];
        yield 'Test list field with equals any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'equals-any', 'value' => [3, 4]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ])
        ];
        yield 'Test list field with not filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'not', 'value' => 3]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key3', listField: [1, 4]),
            ])
        ];
        yield 'Test list field with not any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1, 2]),
                self::document(key: 'key2', listField: [1, 3]),
                self::document(key: 'key3', listField: [1, 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'not-any', 'value' => [3, 4]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: [1, 2]),
            ])
        ];
        yield 'Test list field with equals filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'equals', 'value' => 3.3]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: [1.1, 3.3]),
            ])
        ];
        yield 'Test list field with equals any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'equals-any', 'value' => [3.3, 4.4]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ])
        ];
        yield 'Test list field with not filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'not', 'value' => 3.3]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ])
        ];
        yield 'Test list field with not any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: [1.1, 2.2]),
                self::document(key: 'key2', listField: [1.1, 3.3]),
                self::document(key: 'key3', listField: [1.1, 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'not-any', 'value' => [3.3, 4.4]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: [1.1, 2.2]),
            ])
        ];
        yield 'Test list field with equals filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'equals', 'value' => '2021-01-03']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
            ])
        ];
        yield 'Test list field with equals any filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'equals-any', 'value' => ['2021-01-03', '2021-01-04']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ])
        ];
        yield 'Test list field with not filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'not', 'value' => '2021-01-03']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ])
        ];
        yield 'Test list field with not any filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'not-any', 'value' => ['2021-01-03', '2021-01-04']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
            ])
        ];
        yield 'Test list field with contains filter and date values' => [
            'input' => new Documents([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
                self::document(key: 'key2', listField: ['2021-01-01', '2021-01-03']),
                self::document(key: 'key3', listField: ['2021-01-01', '2021-01-04']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'listField', 'type' => 'contains', 'value' => '2021-01-02']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', listField: ['2021-01-01', '2021-01-02']),
            ])
        ];

        yield 'Test list object field with equals filter and string value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.foo', 'type' => 'equals', 'value' => 'baz-2']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ])
        ];
        yield 'Test list object field with equals any filter and string value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.foo', 'type' => 'equals-any', 'value' => ['bar-2', 'qux-2']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ])
        ];
        yield 'Test list object field with contains filter and string value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.foo', 'type' => 'contains', 'value' => 'baz']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ])
        ];
        yield 'Test list object field with starts with filter and string value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.foo', 'type' => 'starts-with', 'value' => 'qu']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ])
        ];
        yield 'Test list object field with ends with filter and string value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['foo' => 'bar'], ['foo' => 'bar-2']]),
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.foo', 'type' => 'ends-with', 'value' => 'z-2']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['foo' => 'baz'], ['foo' => 'baz-2']]),
                self::document(key: 'key3', objectListField: [['foo' => 'qux'], ['foo' => 'qux-2'], ['foo' => 'baz-2']]),
            ])
        ];

        yield 'Test list object field with equals filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooInt', 'type' => 'equals', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
            ])
        ];
        yield 'Test list object field with equals any filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooInt', 'type' => 'equals-any', 'value' => [10, 22]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ])
        ];
        yield 'Test list object field with gte filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooInt', 'type' => 'gte', 'value' => 22]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ])
        ];
        yield 'Test list object field with lte filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooInt', 'type' => 'lte', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
            ])
        ];
        yield 'Test list object field with gt filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooInt', 'type' => 'gt', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ])
        ];
        yield 'Test list object field with lt filter and int value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
                self::document(key: 'key3', objectListField: [['fooInt' => 20], ['fooInt' => 22], ['fooInt' => 24]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooInt', 'type' => 'lt', 'value' => 20]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooInt' => 1], ['fooInt' => 2]]),
                self::document(key: 'key2', objectListField: [['fooInt' => 10], ['fooInt' => 2]]),
            ])
        ];

        yield 'Test list object field with equals filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooFloat', 'type' => 'equals', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
            ])
        ];
        yield 'Test list object field with equals any filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),

            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooFloat', 'type' => 'equals-any', 'value' => [10.1, 22.2]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ])
        ];
        yield 'Test list object field with gte filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooFloat', 'type' => 'gte', 'value' => 22.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ])
        ];
        yield 'Test list object field with lte filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooFloat', 'type' => 'lte', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
            ])
        ];
        yield 'Test list object field with gt filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooFloat', 'type' => 'gt', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ])
        ];
        yield 'Test list object field with lt filter and float value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key3', objectListField: [['fooFloat' => 20.1], ['fooFloat' => 22.2], ['fooFloat' => 24.2]]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooFloat', 'type' => 'lt', 'value' => 20.1]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooFloat' => 1.1], ['fooFloat' => 2.2]]),
                self::document(key: 'key2', objectListField: [['fooFloat' => 10.1], ['fooFloat' => 2.2]]),
            ])
        ];

        yield 'Test list object field with equals filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooDate', 'type' => 'equals', 'value' => '2021-01-02 00:00:00.000']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
            ])
        ];
        yield 'Test list object field with equals any filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooDate', 'type' => 'equals-any', 'value' => ['2021-01-10 00:00:00.000', '2021-01-22 00:00:00.000']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ])
        ];

        yield 'Test list object field with gte filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooDate', 'type' => 'gte', 'value' => '2021-01-22 00:00:00.000']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ])
        ];
        yield 'Test list object field with lte filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooDate', 'type' => 'lte', 'value' => '2021-01-02 00:00:00.000']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
            ])
        ];
        yield 'Test list object field with gt filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooDate', 'type' => 'gt', 'value' => '2021-01-02 00:00:00.000']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ])
        ];
        yield 'Test list object field with lt filter and date value' => [
            'input' => new Documents([
                self::document(key: 'key1', objectListField: [['fooDate' => '2021-01-01 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key2', objectListField: [['fooDate' => '2021-01-10 00:00:00.000'], ['fooDate' => '2021-01-02 00:00:00.000']]),
                self::document(key: 'key3', objectListField: [['fooDate' => '2021-01-20 00:00:00.000'], ['fooDate' => '2021-01-22 00:00:00.000'], ['fooDate' => '2021-01-24 00:00:00.000']]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'objectListField.fooDate', 'type' => 'lt', 'value' => '2021-01-20 00:00:00.000']
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
                    ['field' => 'objectField.fooObj.bar', 'type' => 'equals', 'value' => 'qux']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', objectField: ['fooObj' => ['bar' => 'qux']]),
            ])
        ];

        yield 'Test translated string field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'equals', 'value' => 'foo']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ])
        ];
        yield 'Test translated string field with equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'equals-any', 'value' => ['foo', 'bar']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
            ])
        ];
        yield 'Test translated string field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'not', 'value' => 'foo']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ])
        ];
        yield 'Test translated string field with not any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'not-any', 'value' => ['foo', 'bar']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ])
        ];
        yield 'Test translated string field with contains filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'boo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'foo', 'de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'contains', 'value' => 'oo']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'boo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'foo', 'de' => 'bar']),
            ])
        ];
        yield 'Test translated string field with starts-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'baz', 'de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'starts-with', 'value' => 'foo']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
            ])
        ];
        yield 'Test translated string field with ends-with filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['en' => 'ob', 'de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'ends-with', 'value' => 'o']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'foo']),
            ])
        ];
        yield 'Test translated string field with gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'gte', 'value' => 'b']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ])
        ];
        yield 'Test translated string field with gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'gt', 'value' => 'b']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'c']),
            ])
        ];
        yield 'Test translated string field with lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'lte', 'value' => 'b']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ])
        ];
        yield 'Test translated string field with lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
                self::document(key: 'key2', translatedString: ['en' => 'c']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'b']),
                self::document(key: 'key4', translatedString: ['en' => 'b', 'de' => 'a']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'lt', 'value' => 'b']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedString: ['en' => 'a', 'de' => 'b']),
            ])
        ];
        yield 'Test translated string field with equals filter and empty string' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'bar', 'de' => 'foo']),
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key3', translatedString: ['en' => '', 'de' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'equals', 'value' => 'foo']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'foo']),
                self::document(key: 'key4', translatedString: ['de' => 'foo']),
            ])
        ];

        yield 'Test translated int field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'equals', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ])
        ];
        yield 'Test translated int field with equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key4', translatedInt: ['de' => 4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'equals-any', 'value' => [2, 3, 4]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key4', translatedInt: ['de' => 4]),
            ])
        ];
        yield 'Test translated int field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'not', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
            ])
        ];
        yield 'Test translated int field with not-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'not-any', 'value' => [1, 2]]
                ]
            ),
            'expected' => new FilterResult([])
        ];
        yield 'Test translated int field with gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'gte', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
            ])
        ];
        yield 'Test translated int field with gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'gt', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 3]),
            ])
        ];
        yield 'Test translated int field with lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'lte', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ])
        ];
        yield 'Test translated int field with lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 3]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'lt', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 1]),
            ])
        ];

        yield 'Test translated float field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'equals', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ])
        ];
        yield 'Test translated float field with equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key4', translatedFloat: ['de' => 4.4]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'equals-any', 'value' => [2.2, 3.3, 4.4]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key4', translatedFloat: ['de' => 4.4]),
            ])
        ];
        yield 'Test translated float field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'not', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
            ])
        ];
        yield 'Test translated float field with not-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'not-any', 'value' => [1.1, 2.2]]
                ]
            ),
            'expected' => new FilterResult([])
        ];
        yield 'Test translated float field with gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'gte', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
            ])
        ];
        yield 'Test translated float field with gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'gt', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
            ])
        ];
        yield 'Test translated float field with lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'lte', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ])
        ];
        yield 'Test translated float field with lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 3.3]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'lt', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 1.1]),
            ])
        ];

        yield 'Test translated bool field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedBool', 'type' => 'equals', 'value' => false]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ])
        ];
        yield 'Test translated bool field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedBool', 'type' => 'not', 'value' => false]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
            ])
        ];

        yield 'Test translated date field with equals filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedDate', 'type' => 'equals', 'value' => '2021-01-02 00:00:00.000']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field with equals-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedDate', 'type' => 'equals-any', 'value' => ['2021-01-02 00:00:00.000', '2021-01-03 00:00:00.000']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field with not filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedDate', 'type' => 'not', 'value' => '2021-01-02 00:00:00.000']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field with not-any filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-02 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedDate', 'type' => 'not-any', 'value' => ['2021-01-01 00:00:00.000', '2021-01-02 00:00:00.000']]
                ]
            ),
            'expected' => new FilterResult([])
        ];
        yield 'Test translated date field with gte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedDate', 'type' => 'gte', 'value' => '2021-01-02 00:00:00.000']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field with gt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedDate', 'type' => 'gt', 'value' => '2021-01-02 00:00:00.000']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field with lte filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedDate', 'type' => 'lte', 'value' => '2021-01-02 00:00:00.000']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ])
        ];
        yield 'Test translated date field with lt filter' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key2', translatedDate: ['en' => '2021-01-03 00:00:00.000']),
                self::document(key: 'key3', translatedDate: ['en' => null, 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedDate', 'type' => 'lt', 'value' => '2021-01-02 00:00:00.000']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedDate: ['en' => '2021-01-01 00:00:00.000', 'de' => '2021-01-02 00:00:00.000']),
                self::document(key: 'key4', translatedDate: ['de' => '2021-01-01 00:00:00.000']),
            ])
        ];

        yield 'Test translated list field with equals filter and string values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'equals', 'value' => 'bar']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ])
        ];
        yield 'Test translated list field with equals-any filter and string values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'baz']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'equals-any', 'value' => ['bar', 'baz']]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'baz']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ])
        ];
        yield 'Test translated list field with not filter and string values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'not', 'value' => 'bar']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
            ])
        ];
        yield 'Test translated list field with not-any filter and string values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'not-any', 'value' => ['foo', 'bar']]
                ]
            ),
            'expected' => new FilterResult([])
        ];
        yield 'Test translated list field with contains filter and string values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedString: ['en' => 'foo', 'de' => 'bar']),
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedString', 'type' => 'contains', 'value' => 'ba']
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedString: ['en' => 'bar']),
                self::document(key: 'key3', translatedString: ['en' => null, 'de' => 'bar']),
                self::document(key: 'key4', translatedString: ['de' => 'bar']),
            ])
        ];

        yield 'Test translated list field with equals filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'equals', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ])
        ];
        yield 'Test translated list field with equals-any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'equals-any', 'value' => [2, 3]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 3]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ])
        ];
        yield 'Test translated list field with not filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'not', 'value' => 2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
            ])
        ];
        yield 'Test translated list field with not-any filter and int values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedInt: ['en' => 1, 'de' => 2]),
                self::document(key: 'key2', translatedInt: ['en' => 2]),
                self::document(key: 'key3', translatedInt: ['en' => null, 'de' => 2]),
                self::document(key: 'key4', translatedInt: ['de' => 2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedInt', 'type' => 'not-any', 'value' => [1, 2]]
                ]
            ),
            'expected' => new FilterResult([])
        ];

        yield 'Test translated list field with equals filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'equals', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ])
        ];
        yield 'Test translated list field with equals-any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'equals-any', 'value' => [2.2, 3.3]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 3.3]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ])
        ];
        yield 'Test translated list field with not filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'not', 'value' => 2.2]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
            ])
        ];
        yield 'Test translated list field with not-any filter and float values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedFloat: ['en' => 1.1, 'de' => 2.2]),
                self::document(key: 'key2', translatedFloat: ['en' => 2.2]),
                self::document(key: 'key3', translatedFloat: ['en' => null, 'de' => 2.2]),
                self::document(key: 'key4', translatedFloat: ['de' => 2.2]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedFloat', 'type' => 'not-any', 'value' => [1.1, 2.2]]
                ]
            ),
            'expected' => new FilterResult([])
        ];

        yield 'Test translated list field with equals filter and bool values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedBool', 'type' => 'equals', 'value' => false]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ])
        ];
        yield 'Test translated list field with equals-any filter and bool values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => true]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedBool', 'type' => 'equals-any', 'value' => [false, true]]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => true]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ])
        ];
        yield 'Test translated list field with not filter and bool values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedBool', 'type' => 'not', 'value' => false]
                ]
            ),
            'expected' => new FilterResult([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
            ])
        ];
        yield 'Test translated list field with not-any filter and bool values' => [
            'input' => new Documents([
                self::document(key: 'key1', translatedBool: ['en' => true, 'de' => false]),
                self::document(key: 'key2', translatedBool: ['en' => false]),
                self::document(key: 'key3', translatedBool: ['en' => null, 'de' => false]),
                self::document(key: 'key4', translatedBool: ['de' => false]),
            ]),
            'criteria' => new FilterCriteria(
                filters: [
                    ['field' => 'translatedBool', 'type' => 'not-any', 'value' => [true, false]]
                ]
            ),
            'expected' => new FilterResult([])
        ];
    }

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
    ): Document
    {
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
