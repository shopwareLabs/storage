<?php

namespace Shopware\StorageTests\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\MySQL\MySQLAccessorBuilder;
use Shopware\Storage\MySQL\MySQLMatchInterpreter;
use Shopware\Storage\MySQL\MySQLParser;
use Shopware\Storage\MySQL\MySQLStorage;
use Shopware\StorageTests\Common\TestSchema;

trait MySQLTestTrait
{
    private static ?Connection $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getStorage()->destroy();

        $this->getStorage()->setup();
    }

    public function createStorage(): MySQLStorage
    {
        $accessor = new MySQLAccessorBuilder();

        $parser = new MySQLParser(accessor: $accessor);

        return new MySQLStorage(
            parser: $parser,
            hydrator: new Hydrator(),
            interpreter: new MySQLMatchInterpreter(),
            accessor: $accessor,
            caster: new AggregationCaster(),
            connection: $this->getConnection(),
            collection: TestSchema::getCollection()
        );
    }

    private static function getConnection(): Connection
    {
        if (self::$connection) {
            return self::$connection;
        }

        $params = [
            'url' => 'mysql://shopware:shopware@127.0.0.1:3306/shopware',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
                \PDO::ATTR_TIMEOUT => 5,
            ],
        ];

        return self::$connection = DriverManager::getConnection($params);
    }
}
