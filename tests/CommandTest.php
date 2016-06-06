<?php

namespace yiiunit\extensions\mongodb;

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
            ['status' => -1],
            'email',
            ['address'],
        ]));
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
        $this->assertEquals(3, $result->nIndexesWas);

        $result = $command->dropIndexes('customer', '*');
        $this->assertEquals(2, $result->nIndexesWas);

        $this->setExpectedException('yii\mongodb\Exception', 'index not found with name');
        $command->dropIndexes('customer', 'desc_index');
    }
}