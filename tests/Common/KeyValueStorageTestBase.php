<?php

namespace Shopware\StorageTests\Common;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\StorageTests\Common\Schema\Category;
use Shopware\StorageTests\Common\Schema\Product;

abstract class KeyValueStorageTestBase extends TestCase
{
    abstract public function getStorage(): Storage;

    #[DataProvider('storeProvider')]
    final public function testGet(Documents $input): void
    {
        $storage = $this->getStorage();

        $storage->store($input);

        $context = new StorageContext(languages: ['en', 'de']);

        foreach ($input as $expected) {
            $document = $storage->get(key: $expected->key, context: $context);
            static::assertInstanceOf(Document::class, $document);
            static::assertEquals($expected, $document);

            $storage->remove([$expected->key]);

            $document = $storage->get($expected->key, context: $context);
            static::assertNull($document);
        }
    }

    #[DataProvider('storeProvider')]
    final public function getMGet(Documents $input): void
    {
        $storage = $this->getStorage();

        $storage->store(documents: $input);

        $context = new StorageContext(languages: ['en', 'de']);

        $documents = $storage->mget(keys: $input->keys(), context: $context);

        foreach ($input as $expected) {
            static::assertTrue($documents->has($expected->key));

            $document = $documents->get($expected->key);
            static::assertInstanceOf(Document::class, $document);
            static::assertEquals($expected, $document);
        }

        $storage->remove($input->keys());

        $documents = $storage->mget($input->keys(), $context);

        static::assertCount(0, $documents);
    }

    final public static function storeProvider(): \Generator
    {
        yield 'Test store with single document' => [
            new Documents([
                new Product(key: 'document-1', ean: 'bar'),
            ]),
        ];

        yield 'Test multiple documents' => [
            new Documents([
                new Product(key: 'document-1', ean: 'bar'),
                new Product(key: 'document-2', ean: 'baz'),
            ]),
        ];

        yield 'Test document with empty data' => [
            new Documents([
                new Product(key: 'document-1'),
            ]),
        ];

        yield 'Test document with float zero' => [
            new Documents([
                new Product(key: 'document-1', price: 0.0),
            ]),
        ];

        yield 'Test document with int zero' => [
            new Documents([
                new Product(key: 'document-1', stock: 0),
            ]),
        ];

        yield 'Test document with null' => [
            new Documents([
                new Product(key: 'document-1', stock: null),
            ]),
        ];

        yield 'Test document with string zero' => [
            new Documents([
                new Product(key: 'document-1', ean: '0'),
            ]),
        ];

        yield 'Test document nested data' => [
            new Documents([
                new Product(key: 'document-1', mainCategory: new Category(ean: 'bar')),
            ]),
        ];

        yield 'Test document with unicode' => [
            new Documents([
                new Product(key: 'document-1', ean: '👍'),
            ]),
        ];

        yield 'Test document with json' => [
            new Documents([
                new Product(key: 'document-1', ean: '{"bar":"baz"}'),
            ]),
        ];
    }
}
