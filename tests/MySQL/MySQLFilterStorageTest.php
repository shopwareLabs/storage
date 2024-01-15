<?php

namespace Shopware\StorageTests\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Filter\Result;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Filter\Type\Equals;
use Shopware\Storage\Common\Filter\Type\Not;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\MySQL\MySQLStorage;
use Shopware\StorageTests\Common\FilterStorageTestBase;

/**
 * @covers \Shopware\Storage\MySQL\MySQLStorage
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
            ->executeStatement((string) file_get_contents(__DIR__ . '/test_storage.sql'));
    }

    public function getStorage(): FilterAware&Storage
    {
        return new MySQLStorage(
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
