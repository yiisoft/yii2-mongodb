<?php

namespace yiiunit\extensions\mongodb;

use yii\mongodb\Collection;
use yii\mongodb\Command;
use yii\mongodb\file\Collection as FileCollection;

class DatabaseTest extends TestCase
{
    protected function tearDown()
    {
        $this->dropCollection('customer');
        $this->dropFileCollection('testfs');
        parent::tearDown();
    }

    // Tests :

    public function testGetCollection()
    {
        $database = $this->getConnection()->getDatabase();

        $collection = $database->getCollection('customer');
        $this->assertTrue($collection instanceof Collection);
        $this->assertSame($database, $collection->database);

        $collection2 = $database->getCollection('customer');
        $this->assertSame($collection, $collection2);

        $collectionRefreshed = $database->getCollection('customer', true);
        $this->assertNotSame($collection, $collectionRefreshed);
    }

    public function testGetFileCollection()
    {
        $database = $this->getConnection()->getDatabase();

        $collection = $database->getFileCollection('testfs');
        $this->assertTrue($collection instanceof FileCollection);
        $this->assertSame($database, $collection->database);

        $collection2 = $database->getFileCollection('testfs');
        $this->assertSame($collection, $collection2);

        $collectionRefreshed = $database->getFileCollection('testfs', true);
        $this->assertNotSame($collection, $collectionRefreshed);
    }

    public function testCreateCommand()
    {
        $database = $this->getConnection()->getDatabase();

        $command = $database->createCommand();
        $this->assertTrue($command instanceof Command);
        $this->assertEquals($database->name, $command->databaseName);
    }

    public function testCreateCollection()
    {
        $database = $this->getConnection()->getDatabase();
        $this->assertTrue($database->createCollection('customer'));
    }
}
