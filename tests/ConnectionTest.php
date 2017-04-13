<?php

namespace yiiunit\extensions\mongodb;

use yii\mongodb\Collection;
use yii\mongodb\Command;
use yii\mongodb\file\Collection as FileCollection;
use yii\mongodb\Connection;
use yii\mongodb\Database;
use yii\mongodb\QueryBuilder;

class ConnectionTest extends TestCase
{
    public function testConstruct()
    {
        $connection = $this->getConnection(false);
        $params = $this->mongoDbConfig;

        $connection->open();

        $this->assertEquals($params['dsn'], $connection->dsn);
        $this->assertEquals($params['options'], $connection->options);
        $this->assertEquals($params['driverOptions'], $connection->driverOptions);
    }

    public function testOpenClose()
    {
        $connection = $this->getConnection(false, false);

        $this->assertFalse($connection->isActive);
        $this->assertEquals(null, $connection->manager);

        $connection->open();
        $this->assertTrue($connection->isActive);
        $this->assertTrue(is_object($connection->manager));

        $connection->close();
        $this->assertFalse($connection->isActive);
        $this->assertEquals(null, $connection->manager);

        $connection = new Connection();
        $connection->dsn = 'unknown::memory:';
        $this->expectException('yii\mongodb\Exception');
        $connection->open();
    }

    public function testGetDatabase()
    {
        $connection = $this->getConnection();

        $database = $connection->getDatabase($connection->defaultDatabaseName);
        $this->assertTrue($database instanceof Database);
        $this->assertSame($connection, $database->connection);
        $this->assertSame($connection->defaultDatabaseName, $database->name);

        $database2 = $connection->getDatabase($connection->defaultDatabaseName);
        $this->assertTrue($database === $database2);

        $databaseRefreshed = $connection->getDatabase($connection->defaultDatabaseName, true);
        $this->assertFalse($database === $databaseRefreshed);
    }

    /**
     * Data provider for [[testFetchDefaultDatabaseName()]]
     * @return array test data
     */
    public function dataProviderFetchDefaultDatabaseName()
    {
        return [
            [
                'mongodb://travis:test@localhost:27017/dbname',
                'dbname',
            ],
            [
                'mongodb://travis:test@localhost:27017/dbname?replicaSet=test&connectTimeoutMS=300000',
                'dbname',
            ],
        ];
    }

    /**
     * @dataProvider dataProviderFetchDefaultDatabaseName
     *
     * @param string $dsn
     * @param string $databaseName
     */
    public function testGetDefaultDatabaseName($dsn, $databaseName)
    {
        $connection = new Connection();
        $connection->dsn = $dsn;

        $connection->getDefaultDatabaseName();

        $this->assertEquals($databaseName, $connection->getDefaultDatabaseName());
    }

    /**
     * @depends testGetDatabase
     */
    public function testGetDefaultDatabase()
    {
        $connection = new Connection();
        $connection->dsn = $this->mongoDbConfig['dsn'];
        $database = $connection->getDatabase();
        $this->assertTrue($database instanceof Database, 'Unable to determine default database from dsn!');
    }

    /**
     * @depends testGetDefaultDatabase
     */
    public function testGetCollection()
    {
        $connection = $this->getConnection();

        $collection = $connection->getCollection('customer');
        $this->assertTrue($collection instanceof Collection);

        $collection2 = $connection->getCollection('customer');
        $this->assertTrue($collection === $collection2);

        $collection2 = $connection->getCollection('customer', true);
        $this->assertFalse($collection === $collection2);
    }

    /**
     * @depends testGetDefaultDatabase
     */
    public function testGetFileCollection()
    {
        $connection = $this->getConnection();

        $collection = $connection->getFileCollection('testfs');
        $this->assertTrue($collection instanceof FileCollection);

        $collection2 = $connection->getFileCollection('testfs');
        $this->assertTrue($collection === $collection2);

        $collection2 = $connection->getFileCollection('testfs', true);
        $this->assertFalse($collection === $collection2);
    }

    public function testGetQueryBuilder()
    {
        $connection = $this->getConnection();

        $this->assertTrue($connection->getQueryBuilder() instanceof QueryBuilder);
    }

    public function testCreateCommand()
    {
        $connection = $this->getConnection();

        $command = $connection->createCommand();
        $this->assertTrue($command instanceof Command);
        $this->assertSame($connection, $command->db);
    }
}
