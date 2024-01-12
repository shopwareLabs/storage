<?php

namespace Shopware\StorageTests\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Exception\NotSupportedByEngine;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Filter\FilterStorage;
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
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Meilisearch\MeilisearchStorage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

class MeilisearchStorageTest extends FilterStorageTestBase
{
    private ?Client $client = null;

    private function exists(): bool
    {
        try {
            $this->getClient()->getIndex($this->getSchema()->source);
        } catch (ApiException) {
            return false;
        }

        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->exists()) {
            $this->index()->deleteAllDocuments();

            $this->wait();

            return;
        }

        $this->getClient()->deleteIndex($this->getSchema()->source);

        $this->wait();

        $this->getClient()->createIndex(
            uid: $this->getSchema()->source,
            options: ['primaryKey' => 'key']
        );

        $fields = array_map(fn($field) => $field->name, $this->getSchema()->fields);

        $fields[] = 'key';

        $fields = array_values(array_filter($fields));

        $this->index()
            ->updateFilterableAttributes($fields);

        $this->index()
            ->updateSortableAttributes($fields);

        $this->wait();
    }

    #[DataProvider('debugProvider')]
    public function testDebug(
        Documents $input,
        FilterCriteria $criteria,
        FilterResult $expected
    ): void {
        $storage = $this->getStorage();

        $storage->store($input);

        try {
            $loaded = $storage->filter($criteria, new StorageContext(languages: ['en', 'de']));
        } catch (NotSupportedByEngine $e) {
            static::markTestIncomplete($e->getMessage());
        }

        static::assertEquals($expected, $loaded);
    }

    public static function debugProvider(): \Generator
    {
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

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client(
                url: 'http://localhost:7700',
                apiKey: 'UTbXxcv5T5Hq-nCYAjgPJ5lsBxf7PdhgiNexmoTByJk'
            );
        }

        return $this->client;
    }

    public function getStorage(): FilterStorage
    {
        return new LiveMeilisearchStorage(
            storage: new MeilisearchStorage(
                client: $this->getClient(),
                schema: $this->getSchema()
            ),
            client: $this->getClient(),
        );
    }

    private function index(): Indexes
    {
        return $this->getClient()->index($this->getSchema()->source);
    }

    private function wait(): void
    {
        $tasks = new TasksQuery();
        $tasks->setStatuses(['enqueued', 'processing']);

        $tasks = $this->getClient()->getTasks($tasks);

        $ids = array_map(fn($task) => $task['uid'], $tasks->getResults());

        if (count($ids) === 0) {
            return;
        }

        $this->getClient()->waitForTasks($ids);
    }
}
