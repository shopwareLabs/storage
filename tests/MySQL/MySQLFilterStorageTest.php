<?php

namespace Shopware\StorageTests\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\MySQL\MySQLFilterStorage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

/**
 * @covers \Shopware\Storage\MySQL\MySQLFilterStorage
 */
class MySQLFilterStorageTest extends FilterStorageTestBase
{
    private static ?Connection $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()
            ->executeStatement('DROP TABLE IF EXISTS `test_storage`');

        $this->getConnection()
            ->executeStatement(file_get_contents(__DIR__ . '/test_storage.sql'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

//        $this->getConnection()
//            ->executeStatement('DROP TABLE IF EXISTS `test_storage`');
    }

    public function getStorage(): FilterStorage
    {
        return new MySQLFilterStorage(
            connection: $this->getConnection(),
            schema: $this->getSchema()
        );
    }

    private static function getConnection()
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
