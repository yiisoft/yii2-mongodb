<?php

namespace yiiunit\extensions\mongodb;

use MongoDB\BSON\ObjectID;
use MongoDB\Driver\Cursor;
use yii\helpers\ArrayHelper;

class CommandTest extends TestCase
{
    protected function tearDown()
    {
        $this->dropCollection('customer');
        parent::tearDown();
    }

    public function testCreateCollection()
    {
        $command = yii::$app->mongodb->createCommand();
        $this->assertTrue($command->createCollection('customer'));
    }

    /**
     * @depends testCreateCollection
     */
    public function testDropCollection()
    {
        $command = yii::$app->mongodb->createCommand();
        $command->createCollection('customer');
        $this->assertTrue($command->dropCollection('customer'));
    }

    public function testCount()
    {
        $command = yii::$app->mongodb->createCommand();
        $this->assertEquals(0, $command->count('customer'));
    }

    public function testCreateIndexes()
    {
        $command = yii::$app->mongodb->createCommand();
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
        $command = yii::$app->mongodb->createCommand();
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
        $command = yii::$app->mongodb->createCommand();
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

        $this->expectException('yii\mongodb\Exception');
        $this->expectExceptionMessage('index not found with name');
        $command->dropIndexes('customer', 'desc_index');
    }

    public function testInsert()
    {
        $command = yii::$app->mongodb->createCommand();
        $insertedId = $command->insert('customer', ['name' => 'John']);
        $this->assertTrue($insertedId instanceof ObjectID);
    }

    /**
     * @depends testInsert
     */
    public function testBatchInsert()
    {
        $command = yii::$app->mongodb->createCommand();
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

        $newRecordId = yii::$app->mongodb->createCommand()->insert('customer', ['name' => 'John']);

        $result = yii::$app->mongodb->createCommand()->update('customer', ['_id' => $newRecordId], ['name' => 'Mike']);

        $this->assertEquals(1, $result->getModifiedCount());
    }

    /**
     * @depends testInsert
     */
    public function testDelete()
    {

        $newRecordId = yii::$app->mongodb->createCommand()->insert('customer', ['name' => 'John']);

        $result = yii::$app->mongodb->createCommand()->delete('customer', ['_id' => $newRecordId]);

        $this->assertEquals(1, $result->getDeletedCount());
    }

    /**
     * @depends testInsert
     */
    public function testFind()
    {

        yii::$app->mongodb->createCommand()->insert('customer', ['name' => 'John']);

        $cursor = yii::$app->mongodb->createCommand()->find('customer', []);
        $rows = $cursor->toArray();
        $this->assertCount(1, $rows);
        $this->assertEquals('John', $rows[0]['name']);
    }

    /**
     * @depends testBatchInsert
     */
    public function testFindAndModify()
    {
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
        $command = yii::$app->mongodb->createCommand();
        $command->batchInsert('customer', $rows);

        // increment field
        $result = yii::$app->mongodb->createCommand()->findAndModify('customer', ['name' => 'customer 1'], ['$inc' => ['status' => 1]]);
        $this->assertEquals('customer 1', $result['name']);
        $this->assertEquals(1, $result['status']);

        $cursor = yii::$app->mongodb->createCommand()->find('customer', ['name' => 'customer 1']);
        $newResult = current($cursor->toArray());
        $this->assertEquals(2, $newResult['status']);

        // $set and return modified document
        $result = yii::$app->mongodb->createCommand()->findAndModify(
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
        $result = yii::$app->mongodb->createCommand()->findAndModify(
            'customer',
            ['name' => 'customer 2'],
            $data,
            ['new' => true]
        );
        $this->assertEquals('customer 3', $result['name']);
        $this->assertEquals('Minsk', $result['city']);
        $this->assertTrue(!isset($result['status']));

        // Test exceptions
        $this->expectException('\yii\mongodb\Exception');
        yii::$app->mongodb->createCommand()->findAndModify('customer',['name' => 'customer 1'], ['$wrongOperator' => ['status' => 1]]);
    }

    /**
     * @depends testBatchInsert
     */
    public function testAggregate()
    {
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
        $command = yii::$app->mongodb->createCommand();
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
        $result = yii::$app->mongodb->createCommand()->aggregate('customer', $pipelines);

        $this->assertEquals(['_id' => '1', 'total' => 300], $result[0]);
    }

    /**
     * @depends testAggregate
     *
     * @see https://github.com/yiisoft/yii2-mongodb/issues/228
     */
    public function testAggregateCursor()
    {
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
                'name' => 'customer 3',
                'status' => 1,
                'amount' => 100,
            ],
            [
                'name' => 'customer 4',
                'status' => 1,
                'amount' => 200,
            ],
        ];
        $command = yii::$app->mongodb->createCommand();
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
        $result = yii::$app->mongodb->createCommand()->aggregate('customer', $pipelines, ['cursor' => ['batchSize' => 2]]);
        $this->assertTrue($result instanceof Cursor);

        $this->assertEquals(['_id' => '1', 'total' => 600], $result->toArray()[0]);
    }

    /**
     * @depends testFind
     */
    public function testExplain()
    {

        yii::$app->mongodb->createCommand()->insert('customer', ['name' => 'John']);

        $result = yii::$app->mongodb->createCommand()->explain('customer', [
            'filter' => [
                'name' => 'John'
            ],
        ]);

        $this->assertArrayHasKey('queryPlanner', $result);
        $this->assertArrayHasKey('executionStats', $result);
    }

    /**
     * @depends testCreateCollection
     */
    public function testListCollections()
    {

        yii::$app->mongodb->createCommand()->createCollection('customer');

        $collections = yii::$app->mongodb->createCommand()->listCollections();
        $collectionNames = ArrayHelper::getColumn($collections, 'name');
        $this->assertContains('customer', $collectionNames);
    }

    /**
     * @depends testUpdate
     * @depends testCount
     *
     * @see https://github.com/yiisoft/yii2-mongodb/issues/168
     */
    public function testUpdateUpsert()
    {

        yii::$app->mongodb->createCommand()->insert('customer', ['name' => 'John']);

        $result = yii::$app->mongodb->createCommand()
            ->update('customer', ['name' => 'Mike'], ['name' => 'Jack']);

        $this->assertEquals(0, $result->getModifiedCount());
        $this->assertEquals(0, $result->getUpsertedCount());
        $this->assertEquals(1, yii::$app->mongodb->createCommand()->count('customer'));

        $result = yii::$app->mongodb->createCommand()
            ->update('customer', ['name' => 'Mike'], ['name' => 'Jack'], ['upsert' => true]);

        $this->assertEquals(0, $result->getModifiedCount());
        $this->assertEquals(1, $result->getUpsertedCount());
        $this->assertEquals(2, yii::$app->mongodb->createCommand()->count('customer'));
    }
}