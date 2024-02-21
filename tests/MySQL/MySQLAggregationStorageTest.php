<?php

namespace Shopware\StorageTests\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\MySQL\MySQLStorage;
use Shopware\StorageTests\Common\AggregationStorageTestBase;

class MySQLAggregationStorageTest extends AggregationStorageTestBase
{
    private static ?Connection $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()
            ->executeStatement('DROP TABLE IF EXISTS `test_storage`');

        $this->getConnection()
            ->executeStatement((string) file_get_contents(__DIR__ . '/test_storage.sql'));
    }

    public function getStorage(): AggregationAware&Storage
    {
        return new MySQLStorage(
            caster: new AggregationCaster(),
            connection: $this->getConnection(),
            schema: $this->getSchema()
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
