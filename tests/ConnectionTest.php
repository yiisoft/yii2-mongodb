<?php

namespace yiiunit\extensions\mongodb;

use yii;
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
        $connection = $this->getConnection(true,false);
        $params = $this->mongoDbConfig;

        $connection->open();

        $this->assertEquals($params['dsn'], $connection->dsn);
        $this->assertEquals($params['options'], $connection->options);
        $this->assertEquals($params['driverOptions'], $connection->driverOptions);
    }

    public function testOpenClose()
    {
        $connection = $this->getConnection(true, false);

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

        $database = yii::$app->mongodb->getDatabase(yii::$app->mongodb->defaultDatabaseName);
        $this->assertTrue($database instanceof Database);
        $this->assertSame(yii::$app->mongodb, $database->connection);
        $this->assertSame(yii::$app->mongodb->defaultDatabaseName, $database->name);

        $database2 = yii::$app->mongodb->getDatabase(yii::$app->mongodb->defaultDatabaseName);
        $this->assertTrue($database === $database2);

        $databaseRefreshed = yii::$app->mongodb->getDatabase(yii::$app->mongodb->defaultDatabaseName, true);
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

        $collection = yii::$app->mongodb->getCollection('customer');
        $this->assertTrue($collection instanceof Collection);

        $collection2 = yii::$app->mongodb->getCollection('customer');
        $this->assertTrue($collection === $collection2);

        $collection2 = yii::$app->mongodb->getCollection('customer', true);
        $this->assertFalse($collection === $collection2);
    }

    /**
     * @depends testGetDefaultDatabase
     */
    public function testGetFileCollection()
    {

        $collection = yii::$app->mongodb->getFileCollection('testfs');
        $this->assertTrue($collection instanceof FileCollection);

        $collection2 = yii::$app->mongodb->getFileCollection('testfs');
        $this->assertTrue($collection === $collection2);

        $collection2 = yii::$app->mongodb->getFileCollection('testfs', true);
        $this->assertFalse($collection === $collection2);
    }

    public function testGetQueryBuilder()
    {

        $this->assertTrue(yii::$app->mongodb->getQueryBuilder() instanceof QueryBuilder);
    }

    public function testCreateCommand()
    {

        $command = yii::$app->mongodb->createCommand();
        $this->assertTrue($command instanceof Command);
        $this->assertSame(yii::$app->mongodb, $command->db);
    }
}
