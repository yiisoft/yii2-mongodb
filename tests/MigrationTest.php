<?php

namespace yiiunit\extensions\mongodb;

use MongoDB\BSON\ObjectID;

class MigrationTest extends TestCase
{
    protected function tearDown()
    {
        $this->dropCollection('customer');
        parent::tearDown();
    }

    /**
     * @return Migration migration instance.
     */
    protected function createMigration()
    {
        return new Migration(['db' => $this->getConnection()]);
    }

    // Tests :

    public function testCollectionOperations()
    {
        $migration = $this->createMigration();

        $migration->createCollection('customer');
        $this->assertNotEmpty($migration->db->getDatabase()->listCollections(['name' => 'customer']));

        $migration->dropCollection('customer');
        $this->assertEmpty($migration->db->getDatabase()->listCollections(['name' => 'customer']));
    }

    public function testIndexOperations()
    {
        $migration = $this->createMigration();

        $migration->createIndexes('customer', [
            ['key' => 'name']
        ]);
        $this->assertCount(2, $migration->db->getCollection('customer')->listIndexes());

        $migration->dropIndexes('customer', '*');
        $this->assertCount(1, $migration->db->getCollection('customer')->listIndexes());

        $migration->createIndex('customer', ['name']);
        $this->assertCount(2, $migration->db->getCollection('customer')->listIndexes());

        $migration->dropIndex('customer', ['name']);
        $this->assertCount(1, $migration->db->getCollection('customer')->listIndexes());

        $migration->createIndex('customer', ['name']);
        $migration->dropAllIndexes('customer');
        $this->assertCount(1, $migration->db->getCollection('customer')->listIndexes());
    }

    public function testDataOperations()
    {
        $migration = $this->createMigration();

        $id = $migration->insert('customer', ['name' => 'John Doe']);
        $this->assertTrue($id instanceof ObjectID);

        $migration->update('customer', ['_id' => $id], ['name' => 'new name']);
        list($row) = $this->findAll($migration->db->getCollection('customer'));
        $this->assertEquals('new name', $row['name']);

        $migration->save('customer', ['_id' => $id, 'name' => 'save']);
        list($row) = $this->findAll($migration->db->getCollection('customer'));
        $this->assertEquals('save', $row['name']);

        $rows = $migration->batchInsert('customer', [
            ['name' => 'customer 1'],
            ['name' => 'customer 2'],
        ]);
        $this->assertCount(2, $rows);

        $this->assertEquals(3, $migration->remove('customer', []));
    }

    /**
     * @depends testCollectionOperations
     */
    public function testCommandOutput()
    {
        $migration = $this->createMigration();

        $migration->compact = false;
        $migration->createCollection('customer');
        $this->assertCount(2, $migration->logs);

        $migration->compact = true;
        $migration->logs = [];
        $migration->dropCollection('customer');
        $this->assertEmpty($migration->logs);
    }
}

/**
 * Migration is mock of [[\yii\mongodb\Migration]] used for the unit tests.
 */
class Migration extends \yii\mongodb\Migration
{
    /**
     * @var array list of log messages
     */
    public $logs = [];


    /**
     * {@inheritdoc}
     */
    public function up()
    {
        // blank
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        // blank
    }

    /**
     * {@inheritdoc}
     */
    protected function log($string)
    {
        $this->logs[] = $string;
    }
}