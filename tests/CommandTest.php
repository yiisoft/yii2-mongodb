<?php

namespace yiiunit\extensions\mongodb;

use MongoDB\BSON\ObjectID;

class CommandTest extends TestCase
{
    protected function tearDown()
    {
        $this->dropCollection('customer');
        parent::tearDown();
    }

    public function testCreateCollection()
    {
        $command = $this->getConnection()->createCommand();
        $this->assertTrue($command->createCollection('customer'));
    }

    /**
     * @depends testCreateCollection
     */
    public function testDropCollection()
    {
        $command = $this->getConnection()->createCommand();
        $command->createCollection('customer');
        $this->assertTrue($command->dropCollection('customer'));
    }

    public function testCount()
    {
        $command = $this->getConnection()->createCommand();
        $this->assertEquals(0, $command->count('customer'));
    }

    public function testCreateIndexes()
    {
        $command = $this->getConnection()->createCommand();
        $this->assertTrue($command->createIndexes('customer', [
            [
                'key' => ['name' => +1],
            ],
            [
                'key' => ['email'],
            ],
            [
                'key' => 'address',
            ],
        ]));
    }

    /**
     * @depends testCreateIndexes
     */
    public function testListIndexes()
    {
        $command = $this->getConnection()->createCommand();
        $command->createIndexes('customer', [
            [
                'key' => ['name' => +1],
                'name' => 'asc_index'
            ],
        ]);

        $result = $command->listIndexes('customer');
        $this->assertEquals('_id_', $result[0]['name']);
        $this->assertEquals('asc_index', $result[1]['name']);
    }

    /**
     * @depends testCreateIndexes
     */
    public function testDropIndexes()
    {
        $command = $this->getConnection()->createCommand();
        $command->createIndexes('customer', [
            [
                'key' => ['name' => +1],
                'name' => 'asc_index'
            ],
            [
                'key' => ['name' => -1],
                'name' => 'desc_index'
            ],
        ]);

        $result = $command->dropIndexes('customer', 'asc_index');
        $this->assertEquals(3, $result['nIndexesWas']);

        $result = $command->dropIndexes('customer', '*');
        $this->assertEquals(2, $result['nIndexesWas']);

        $this->setExpectedException('yii\mongodb\Exception', 'index not found with name');
        $command->dropIndexes('customer', 'desc_index');
    }

    public function testInsert()
    {
        $command = $this->getConnection()->createCommand();
        $insertedId = $command->insert('customer', ['name' => 'John']);
        $this->assertTrue($insertedId instanceof ObjectID);
    }

    /**
     * @depends testInsert
     */
    public function testBatchInsert()
    {
        $command = $this->getConnection()->createCommand();
        $insertedIds = $command->batchInsert('customer', [
            ['name' => 'John'],
            ['name' => 'Sara'],
        ]);
        $this->assertTrue($insertedIds[0] instanceof ObjectID);
        $this->assertTrue($insertedIds[1] instanceof ObjectID);
    }

    /**
     * @depends testInsert
     */
    public function testUpdate()
    {
        $connection = $this->getConnection();

        $newRecordId = $connection->createCommand()->insert('customer', ['name' => 'John']);

        $result = $connection->createCommand()->update('customer', ['_id' => $newRecordId], ['name' => 'Mike']);

        $this->assertEquals(1, $result->getModifiedCount());
    }

    /**
     * @depends testInsert
     */
    public function testDelete()
    {
        $connection = $this->getConnection();

        $newRecordId = $connection->createCommand()->insert('customer', ['name' => 'John']);

        $result = $connection->createCommand()->delete('customer', ['_id' => $newRecordId]);

        $this->assertEquals(1, $result->getDeletedCount());
    }

    /**
     * @depends testInsert
     */
    public function testFind()
    {
        $connection = $this->getConnection();

        $connection->createCommand()->insert('customer', ['name' => 'John']);

        $cursor = $connection->createCommand()->find('customer', []);
        $rows = $cursor->toArray();
        $this->assertCount(1, $rows);
        $this->assertEquals('John', $rows[0]['name']);
    }

    /**
     * @depends testBatchInsert
     */
    public function testFindAndModify()
    {
        $connection = $this->getConnection();
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
        $command = $connection->createCommand();
        $command->batchInsert('customer', $rows);

        // increment field
        $result = $connection->createCommand()->findAndModify('customer', ['name' => 'customer 1'], ['$inc' => ['status' => 1]]);
        $this->assertEquals('customer 1', $result['name']);
        $this->assertEquals(1, $result['status']);

        $cursor = $connection->createCommand()->find('customer', ['name' => 'customer 1']);
        $newResult = current($cursor->toArray());
        $this->assertEquals(2, $newResult['status']);

        // $set and return modified document
        $result = $connection->createCommand()->findAndModify(
            'customer',
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
        $result = $connection->createCommand()->findAndModify(
            'customer',
            ['name' => 'customer 2'],
            $data,
            ['new' => true]
        );
        $this->assertEquals('customer 3', $result['name']);
        $this->assertEquals('Minsk', $result['city']);
        $this->assertTrue(!isset($result['status']));

        // Test exceptions
        $this->setExpectedException('\yii\mongodb\Exception');
        $connection->createCommand()->findAndModify('customer',['name' => 'customer 1'], ['$wrongOperator' => ['status' => 1]]);
    }

    /**
     * @depends testBatchInsert
     */
    public function testAggregate()
    {
        $connection = $this->getConnection();
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
        $command = $connection->createCommand();
        $command->batchInsert('customer', $rows);

        $pipelines = [
            [
                '$match' => ['status' => 1]
            ],
            [
                '$group' => [
                    '_id' => '1',
                    'total' => [
                        '$sum' => '$amount'
                    ],
                ]
            ]
        ];
        $result = $connection->createCommand()->aggregate('customer', $pipelines);

        $this->assertEquals(['_id' => '1', 'total' => 300], $result[0]);
    }
}