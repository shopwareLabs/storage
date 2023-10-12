<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\KeyValue\KeyValueStorage;
use Shopware\Storage\MySQL\Util\MultiInsert;

class MySQLKeyValueStorage implements KeyValueStorage
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $source
    ) {
    }

    private function table(): string
    {
        return '`' . $this->source . '`';
    }


    public function setup(): void
    {
        // todo@o.skroblin auto setup feature
    }

    public function remove(array $keys): void
    {
        $this->connection->executeStatement(
            sql: 'DELETE FROM ' . $this->table() . ' WHERE `key` IN (:keys)',
            params: ['keys' => $keys],
            types: ['keys' => ArrayParameterType::STRING]
        );
    }

    public function store(Documents $documents): void
    {
        $insert = new MultiInsert(
            connection: $this->connection,
            replace: true
        );

        foreach ($documents as $document) {
            $insert->add(
                table: $this->source,
                data: [
                    'key' => $document->key,
                    'value' => json_encode($document->data, \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_IGNORE),
                ]
            );
        }

        $insert->execute();
    }

    public function mget(array $keys): Documents
    {
        $data = $this->connection->fetchAllAssociative(
            query: 'SELECT `key`, `value` FROM '.$this->table().' WHERE `key` IN (:keys)',
            params: ['keys' => $keys],
            types: ['keys' => ArrayParameterType::STRING]
        );

        $documents = [];
        foreach ($data as $row) {
            $documents[] = new Document(
                key: $row['key'],
                data: json_decode($row['value'], true, 512, \JSON_THROW_ON_ERROR)
            );
        }

        return new Documents($documents);
    }

    public function get(string $key): ?Document
    {
        $data = $this->connection->fetchAssociative(
            query: 'SELECT `key`, `value` FROM ' . $this->table() . ' WHERE `key` = :key',
            params: ['key' => $key]
        );

        if (!$data) {
            return null;
        }

        return new Document(
            key: $data['key'],
            data: json_decode($data['value'], true, 512, \JSON_THROW_ON_ERROR)
        );
    }
}
