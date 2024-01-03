<?php

namespace Shopware\StorageTests\Common;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\KeyValue\KeyValueStorage;

abstract class KeyValueStorageTestBase extends TestCase
{
    abstract public function getStorage(): KeyValueStorage;

    #[DataProvider('storeProvider')]
    final public function testSingle(Documents $input): void
    {
        $storage = $this->getStorage();

        $storage->store($input);

        foreach ($input as $expected) {
            $document = $storage->get($expected->key);
            static::assertInstanceOf(Document::class, $document);

            static::assertEquals($expected->key, $document->key);
            static::assertEquals($expected->data, $document->data);

            $storage->remove([$expected->key]);

            $document = $storage->get($expected->key);

            static::assertNull($document);
        }
    }

    #[DataProvider('storeProvider')]
    final public function testBatch(Documents $input): void
    {
        $storage = $this->getStorage();

        $storage->store(documents: $input);

        $documents = $storage->mget($input->keys());

        foreach ($input as $expected) {
            static::assertTrue($documents->has($expected->key));

            $document = $documents->get($expected->key);
            static::assertInstanceOf(Document::class, $document);

            static::assertEquals($expected->key, $document->key);
            static::assertEquals($expected->data, $document->data);
        }

        $storage->remove($input->keys());

        $documents = $storage->mget($input->keys());

        static::assertCount(0, $documents);
    }

    final public static function storeProvider(): \Generator
    {
        yield 'Test store with single document' => [
            new Documents([
                new Document(key: 'document-1', data: ['foo' => 'bar'])
            ])
        ];

        yield 'Test multiple documents' => [
            new Documents([
                new Document(key: 'document-1', data: ['foo' => 'bar']),
                new Document(key: 'document-2', data: ['foo' => 'bar'])
            ])
        ];

        yield 'Test document with empty data' => [
            new Documents([
                new Document(key: 'document-1', data: [])
            ])
        ];

        yield 'Test document with float zero' => [
            new Documents([
                new Document(key: 'document-1', data: ['foo' => 0.0])
            ])
        ];

        yield 'Test document with int zero' => [
            new Documents([
                new Document(key: 'document-1', data: ['foo' => 0])
            ])
        ];

        yield 'Test document with null' => [
            new Documents([
                new Document(key: 'document-1', data: ['foo' => null])
            ])
        ];

        yield 'Test document with string zero' => [
            new Documents([
                new Document(key: 'document-1', data: ['foo' => '0'])
            ])
        ];

        yield 'Test document nested data' => [
            new Documents([
                new Document(key: 'document-1', data: ['foo' => ['bar' => 'baz']])
            ])
        ];

        yield 'Test document with unicode' => [
            new Documents([
                new Document(key: 'document-1', data: ['foo' => '👍'])
            ])
        ];

        yield 'Test document with json' => [
            new Documents([
                new Document(key: 'document-1', data: ['foo' => '{"bar":"baz"}'])
            ])
        ];
    }
}
