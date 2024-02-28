<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\KeyValue\KeyAware;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\MySQL\Util\MultiInsert;

class MySQLKeyStorage implements KeyAware, Storage
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

    public function mget(array $keys): Documents
    {
        $data = $this->connection->fetchFirstColumn(
            query: 'SELECT `value` FROM ' . $this->table() . ' WHERE `key` IN (:keys)',
            params: ['keys' => $keys],
            types: ['keys' => ArrayParameterType::STRING]
        );

        $documents = [];
        foreach ($data as $row) {
            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: json_decode($row, true)
            );
        }

        return new Documents($documents);
    }

    public function get(string $key): ?Document
    {
        $data = $this->connection->fetchOne(
            query: 'SELECT `value` FROM ' . $this->table() . ' WHERE `key` = :key',
            params: ['key' => $key]
        );

        if (!$data) {
            return null;
        }

        return $this->hydrator->hydrate(
            collection: $this->collection,
            data: json_decode((string) $data, true)
        );
    }
}
