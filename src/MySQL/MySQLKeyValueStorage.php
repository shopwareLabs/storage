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
            if (!isset($row['key']) || !is_string($row['key'])) {
                throw new \LogicException('Invalid data, missing key for document');
            }

            $key = $row['key'];
            $documents[] = new Document(
                key: $key,
                data: $this->decode($key, $row)
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
            key: $key,
            data: $this->decode($key, $data)
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function decode(string $key, array $data): array
    {
        if (!is_string($data['value'])) {
            throw new \RuntimeException(sprintf('Stored data, for key "%s" is invalid, expected type of string', $key));
        }

        $value = json_decode($data['value'], true, 512, \JSON_THROW_ON_ERROR);

        if (!is_array($value)) {
            throw new \RuntimeException(sprintf('Invalid data type for key "%s"', $key));
        }

        return $value;
    }
}
