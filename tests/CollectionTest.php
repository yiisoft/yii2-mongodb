<?php

namespace yiiunit\extensions\mongodb;

use MongoDB\BSON\ObjectID;
use MongoDB\Driver\Cursor;

class CollectionTest extends TestCase
{
    protected function tearDown()
    {
        $this->dropCollection('customer');
        $this->dropCollection('mapReduceOut');
        parent::tearDown();
    }

    // Tests :

    public function testGetName()
    {
        $collectionName = 'customer';
        $collection = $this->getConnection()->getCollection($collectionName);
        $this->assertEquals($collectionName, $collection->name);
        $this->assertEquals($this->getConnection()->getDefaultDatabaseName() . '.' . $collectionName, $collection->getFullName());
    }

    public function testFind()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $cursor = $collection->find();
        $this->assertTrue($cursor instanceof Cursor);
    }

    public function testInsert()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);
        $this->assertTrue($id instanceof ObjectID);
        $this->assertNotEmpty($id->__toString());
    }

    /**
     * @depends testInsert
     * @depends testFind
     */
    public function testFindOne()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);

        $row = $collection->findOne(['_id' => $id]);
        $this->assertEquals($data['name'], $row['name']);

        $row = $collection->findOne(['_id' => 'unexisting-id']);
        $this->assertNull($row);
    }

    /**
     * @depends testInsert
     * @depends testFind
     */
    public function testFindAll()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);

        $cursor = $collection->find();
        $rows = [];
        foreach ($cursor as $row) {
            $rows[] = $row;
        }
        $this->assertEquals(1, count($rows));
        $this->assertEquals($id, $rows[0]['_id']);
    }

    /**
     * @depends testFind
     */
    public function testBatchInsert()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [
            [
                'name' => 'customer 1',
                'address' => 'customer 1 address',
            ],
            [
                'name' => 'customer 2',
                'address' => 'customer 2 address',
            ],
        ];
        $insertedRows = $collection->batchInsert($rows);
        $this->assertTrue($insertedRows[0]['_id'] instanceof ObjectID);
        $this->assertTrue($insertedRows[1]['_id'] instanceof ObjectID);
        $this->assertCount(count($rows), $collection->find()->toArray());
    }

    public function testSave()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->save($data);
        $this->assertTrue($id instanceof ObjectID);
        $this->assertNotEmpty($id->__toString());
    }

    /**
     * @depends testSave
     */
    public function testUpdateBySave()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $newId = $collection->save($data);

        $data['_id'] = $newId;
        $updatedId = $collection->save($data);
        $this->assertEquals($newId, $updatedId, 'Unable to update data!');

        $data['_id'] = $newId->__toString();
        $updatedId = $collection->save($data);
        $this->assertEquals($newId, $updatedId, 'Unable to updated data by string id!');
    }

    /**
     * @depends testFindAll
     */
    public function testRemove()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);

        $count = $collection->remove(['_id' => $id]);
        $this->assertEquals(1, $count);

        $rows = $this->findAll($collection);
        $this->assertEquals(0, count($rows));
    }

    /**
     * @depends testBatchInsert
     * @depends testRemove
     */
    public function testRemoveComplexCondition()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $collection->batchInsert([
            [
                'name' => 'customer 1',
                'status' => 1,
            ],
            [
                'name' => 'customer 2',
                'status' => 2,
            ],
            [
                'name' => 'customer 3',
                'status' => 3,
            ],
        ]);

        $count = $collection->remove(['status' => [1, 3]]);
        $this->assertEquals(2, $count);

        $rows = $this->findAll($collection);
        $this->assertEquals(1, count($rows));
    }

    /**
     * @depends testFindAll
     */
    public function testUpdate()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);

        $newData = [
            'name' => 'new name'
        ];
        $count = $collection->update(['_id' => $id], $newData);
        $this->assertEquals(1, $count);

        list($row) = $this->findAll($collection);
        $this->assertEquals($newData['name'], $row['name']);
    }

    /**
     * @depends testBatchInsert
     */
    public function testGroup()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [
            [
                'name' => 'customer 1',
                'address' => 'customer 1 address',
            ],
            [
                'name' => 'customer 2',
                'address' => 'customer 2 address',
            ],
        ];
        $collection->batchInsert($rows);

        $keys = ['address' => 1];
        $initial = ['items' => []];
        $reduce = "function (obj, prev) { prev.items.push(obj.name); }";
        $result = $collection->group($keys, $initial, $reduce);
        $this->assertEquals(2, count($result));
        $this->assertNotEmpty($result[0]['address']);
        $this->assertNotEmpty($result[0]['items']);
    }

    public function testFindAndModify()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [
            [
                'name' => 'customer 1',
                'status' => 1,
                'amount' => 100,
            ],
            [
                'name' => 'customer 2',
                'status' => 1,
                'amount' => 200,
            ],
        ];
        $collection->batchInsert($rows);

        // increment field
        $result = $collection->findAndModify(['name' => 'customer 1'], ['$inc' => ['status' => 1]]);
        $this->assertEquals('customer 1', $result['name']);
        $this->assertEquals(1, $result['status']);
        $newResult = $collection->findOne(['name' => 'customer 1']);
        $this->assertEquals(2, $newResult['status']);

        // $set and return modified document
        $result = $collection->findAndModify(
            ['name' => 'customer 2'],
            ['$set' => ['status' => 2]],
            ['new' => true]
        );
        $this->assertEquals('customer 2', $result['name']);
        $this->assertEquals(2, $result['status']);

        // Full update document
        $data = [
            'name' => 'customer 3',
            'city' => 'Minsk'
        ];
        $result = $collection->findAndModify(
            ['name' => 'customer 2'],
            $data,
            ['new' => true]
        );
        $this->assertEquals('customer 3', $result['name']);
        $this->assertEquals('Minsk', $result['city']);
        $this->assertTrue(!isset($result['status']));

        // Test exceptions
        $this->expectException('\yii\mongodb\Exception');
        $collection->findAndModify(['name' => 'customer 1'], ['$wrongOperator' => ['status' => 1]]);
    }

    /**
     * @depends testBatchInsert
     */
    public function testMapReduce()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [
            [
                'name' => 'customer 1',
                'status' => 1,
                'amount' => 100,
            ],
            [
                'name' => 'customer 2',
                'status' => 1,
                'amount' => 200,
            ],
            [
                'name' => 'customer 2',
                'status' => 2,
                'amount' => 400,
            ],
            [
                'name' => 'customer 2',
                'status' => 3,
                'amount' => 500,
            ],
        ];
        $collection->batchInsert($rows);

        $result = $collection->mapReduce(
            'function () {emit(this.status, this.amount)}',
            'function (key, values) {return Array.sum(values)}',
            'mapReduceOut',
            ['status' => ['$lt' => 3]]
        );
        $this->assertEquals('mapReduceOut', $result);

        $outputCollection = $this->getConnection()->getCollection($result);
        $rows = $this->findAll($outputCollection);
        $expectedRows = [
            [
                '_id' => 1,
                'value' => 300,
            ],
            [
                '_id' => 2,
                'value' => 400,
            ],
        ];
        $this->assertEquals($expectedRows, $rows);
    }

    /**
     * @depends testMapReduce
     */
    public function testMapReduceInline()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [
            [
                'name' => 'customer 1',
                'status' => 1,
                'amount' => 100,
            ],
            [
                'name' => 'customer 2',
                'status' => 1,
                'amount' => 200,
            ],
            [
                'name' => 'customer 2',
                'status' => 2,
                'amount' => 400,
            ],
            [
                'name' => 'customer 2',
                'status' => 3,
                'amount' => 500,
            ],
        ];
        $collection->batchInsert($rows);

        $result = $collection->mapReduce(
            'function () {emit(this.status, this.amount)}',
            'function (key, values) {return Array.sum(values)}',
            ['inline' => true],
            ['status' => ['$lt' => 3]]
        );
        $expectedRows = [
            [
                '_id' => 1,
                'value' => 300,
            ],
            [
                '_id' => 2,
                'value' => 400,
            ],
        ];
        $this->assertEquals($expectedRows, $result);
    }

    public function testCreateIndex()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $columns = [
            'name',
            'status' => SORT_DESC
        ];
        $this->assertTrue($collection->createIndex($columns));
        $indexInfo = $collection->listIndexes();
        $this->assertEquals(2, count($indexInfo));
    }

    /**
     * @depends testCreateIndex
     */
    public function testDropIndex()
    {
        $collection = $this->getConnection()->getCollection('customer');

        $collection->createIndex('name');
        $this->assertTrue($collection->dropIndex('name'));
        $indexInfo = $collection->listIndexes();
        $this->assertEquals(1, count($indexInfo));

        $this->expectException('\yii\mongodb\Exception');
        $collection->dropIndex('name');
    }

    /**
     * @depends testCreateIndex
     */
    public function testDropAllIndexes()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $collection->createIndex('name');
        $this->assertEquals(2, $collection->dropAllIndexes());
        $indexInfo = $collection->listIndexes();
        $this->assertEquals(1, count($indexInfo));
    }

    public function testCreateIndexes()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $columns = [
            ['key' => ['name']],
            ['key' => ['status' => SORT_DESC]]
        ];
        $this->assertTrue($collection->createIndexes($columns));
        $indexInfo = $collection->listIndexes();
        $this->assertEquals(3, count($indexInfo));
    }

    /**
     * @depends testCreateIndexes
     */
    public function testDropIndexes()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $columns = [
            [
                'key' => ['name'],
                'name' => 'test_index'
            ],
            [
                'key' => ['status'],
                'name' => 'to_be_dropped'
            ],
        ];
        $collection->createIndexes($columns);

        $collection->dropIndexes('to_be_dropped');

        $indexInfo = $collection->listIndexes();
        $this->assertEquals(2, count($indexInfo));
    }

    /**
     * @depends testInsert
     * @depends testFind
     */
    public function testFindByNotObjectId()
    {
        $collection = $this->getConnection()->getCollection('customer');

        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);

        $cursor = $collection->find(['_id' => (string) $id]);
        $this->assertTrue($cursor instanceof Cursor);
        $row = current($cursor->toArray());
        $this->assertEquals($id, $row['_id']);

        $cursor = $collection->find(['_id' => 'fake']);
        $this->assertTrue($cursor instanceof Cursor);
        $this->assertCount(0, $cursor->toArray());
    }

    /**
     * @depends testInsert
     *
     * @see https://github.com/yiisoft/yii2/issues/2548
     */
    public function testInsertMongoBin()
    {
        $collection = $this->getConnection()->getCollection('customer');

        $fileName = __FILE__;
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
            'binData' => new \MongoDB\BSON\Binary(file_get_contents($fileName), 2),
        ];
        $id = $collection->insert($data);
        $this->assertTrue($id instanceof ObjectID);
        $this->assertNotEmpty($id->__toString());
    }

    /**
     * @depends testBatchInsert
     */
    public function testDistinct()
    {
        $collection = $this->getConnection()->getCollection('customer');

        $rows = [
            [
                'name' => 'customer 1',
                'status' => 1,
            ],
            [
                'name' => 'customer 1',
                'status' => 1,
            ],
            [
                'name' => 'customer 3',
                'status' => 2,
            ],
        ];
        $collection->batchInsert($rows);

        $rows = $collection->distinct('status');
        $this->assertFalse($rows === false);
        $this->assertCount(2, $rows);

        $rows = $collection->distinct('status', ['status' => 1]);
        $this->assertFalse($rows === false);
        $this->assertCount(1, $rows);
    }
}
