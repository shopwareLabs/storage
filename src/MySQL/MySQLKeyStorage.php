<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\MySQL\Util\MultiInsert;

class MySQLKeyStorage implements Storage
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Hydrator $hydrator,
        private readonly Collection $collection
    ) {}

    private function table(): string
    {
        return '`' . $this->collection->name . '`';
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
                table: $this->collection->name,
                data: ['key' => $document->key, 'value' => $document->encode()]
            );
        }

        $insert->execute();
    }

    public function mget(array $keys, StorageContext $context): Documents
    {
        $data = $this->connection->fetchFirstColumn(
            query: 'SELECT `value` FROM ' . $this->table() . ' WHERE `key` IN (:keys)',
            params: ['keys' => $keys],
            types: ['keys' => ArrayParameterType::STRING]
        );

        $documents = [];
        foreach ($data as $row) {
            if (!is_string($row)) {
                continue;
            }
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($row, true, 512, \JSON_THROW_ON_ERROR);

            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $decoded,
                context: $context
            );
        }

        return new Documents($documents);
    }

    public function get(string $key, StorageContext $context): ?Document
    {
        $data = $this->connection->fetchOne(
            query: 'SELECT `value` FROM ' . $this->table() . ' WHERE `key` = :key',
            params: ['key' => $key]
        );

        if (!is_string($data)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($data, true, 512, \JSON_THROW_ON_ERROR);

        return $this->hydrator->hydrate(
            collection: $this->collection,
            data: $decoded,
            context: $context
        );
    }
}
