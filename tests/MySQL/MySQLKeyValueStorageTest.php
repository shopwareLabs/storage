<?php

namespace Shopware\StorageTests\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\KeyValue\KeyAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\MySQL\MySQLKeyStorage;
use Shopware\StorageTests\Common\KeyValueStorageTestBase;
use Shopware\StorageTests\Common\TestSchema;

/**
 * @covers \Shopware\Storage\MySQL\MySQLKeyStorage
 */
class MySQLKeyValueStorageTest extends KeyValueStorageTestBase
{
    private static ?Connection $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()
            ->executeStatement('DROP TABLE IF EXISTS `' . TestSchema::getCollection()->name . '`');

        $this->getConnection()
            ->executeStatement('CREATE TABLE IF NOT EXISTS `' . TestSchema::getCollection()->name . '` (`key` VARCHAR(255) NOT NULL, `value` JSON NOT NULL, PRIMARY KEY (`key`))');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->getConnection()
            ->executeStatement('DROP TABLE IF EXISTS `' . TestSchema::getCollection()->name . '`');
    }

    public function getStorage(): KeyAware&Storage
    {
        return new MySQLKeyStorage(
            connection: $this->getConnection(),
            hydrator: new Hydrator(),
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
