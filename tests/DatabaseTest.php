<?php

namespace yiiunit\extensions\mongodb;

use yii\mongodb\Collection;
use yii\mongodb\file\Collection as FileCollection;

/**
 * @group mongodb
 */
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
        $database = $connection = $this->getConnection()->getDatabase();

        $collection = $database->getCollection('customer');
        $this->assertTrue($collection instanceof Collection);
        // Should be \MongoDB\Collection
        $this->assertTrue($collection->mongoCollection instanceof \yii\mongodb\library\Collection);

        $collection2 = $database->getCollection('customer');
        $this->assertTrue($collection === $collection2);

        $collectionRefreshed = $database->getCollection('customer', true);
        $this->assertFalse($collection === $collectionRefreshed);
    }

    public function testGetFileCollection()
    {
        $database = $connection = $this->getConnection()->getDatabase();

        $collection = $database->getFileCollection('testfs');
        $this->assertTrue($collection instanceof FileCollection);
        // Should be \MongoDB\Collection or whatever
        $this->assertTrue($collection->mongoCollection instanceof \yii\mongodb\library\Collection);

        $collection2 = $database->getFileCollection('testfs');
        $this->assertTrue($collection === $collection2);

        $collectionRefreshed = $database->getFileCollection('testfs', true);
        $this->assertFalse($collection === $collectionRefreshed);
    }

    public function testExecuteCommand()
    {
        $database = $connection = $this->getConnection()->getDatabase();

        $result = $database->executeCommand([
            'distinct' => 'customer',
            'key' => 'name'
        ]);
        $this->assertTrue(array_key_exists('ok', $result));
        $this->assertTrue(array_key_exists('values', $result));
    }

    public function testCreateCollection()
    {
        $database = $connection = $this->getConnection()->getDatabase();
        $collection = $database->createCollection('customer');
        // Should be \MongoDB\Collection
        $this->assertTrue($collection instanceof \yii\mongodb\Collection);
    }
}
