<?php

namespace Shopware\StorageTests\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Shopware\Storage\Common\KeyValue\KeyValueStorage;
use Shopware\Storage\MySQL\MySQLKeyValueStorage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;

/**
 * @covers \Shopware\Storage\MySQL\MySQLKeyValueStorage
 */
class MySQLKeyValueStorageTest extends KeyValueStorageTestBase
{
    private static ?Connection $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()
            ->executeStatement('CREATE TABLE IF NOT EXISTS `test_key_value` (`key` VARCHAR(255) NOT NULL, `value` JSON NOT NULL, PRIMARY KEY (`key`))');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->getConnection()
            ->executeStatement('DROP TABLE IF EXISTS `test_key_value`');
    }

    public function getStorage(): KeyValueStorage
    {
        return new MySQLKeyValueStorage(
            connection: $this->getConnection(),
            source: 'test_key_value'
        );
    }

    private static function getConnection(): Connection
    {
        if (self::$connection) {
            return self::$connection;
        }

        $params = [
            'url' => 'mysql://shopware:shopware@localhost:3306/shopware',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
                \PDO::ATTR_TIMEOUT => 5,
            ],
        ];

        return self::$connection = DriverManager::getConnection($params);
    }
}
