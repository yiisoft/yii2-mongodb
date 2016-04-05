<?php

namespace yiiunit\extensions\mongodb;

use yii\mongodb\ActiveFixture;
use yiiunit\extensions\mongodb\data\ar\Customer;

class ActiveFixtureTest extends TestCase
{
    protected function tearDown()
    {
        $this->dropCollection(Customer::collectionName());
        parent::tearDown();
    }

    public function testLoadCollection()
    {
        /* @var $fixture ActiveFixture|\PHPUnit_Framework_MockObject_MockObject */
        $fixture = $this->getMock(
            ActiveFixture::className(),
            ['getData'],
            [
                [
                    'db' => $this->getConnection(),
                    'collectionName' => Customer::collectionName()
                ]
            ]
        );
        $fixture->expects($this->any())->method('getData')->will($this->returnValue([
            ['name' => 'name1'],
            ['name' => 'name2'],
        ]));

        $fixture->load();

        $rows = $this->findAll($this->getConnection()->getCollection(Customer::collectionName()));
        $this->assertCount(2, $rows);
    }

    public function testLoadClass()
    {
        /* @var $fixture ActiveFixture|\PHPUnit_Framework_MockObject_MockObject */
        $fixture = $this->getMock(
            ActiveFixture::className(),
            ['getData'],
            [
                [
                    'db' => $this->getConnection(),
                    'modelClass' => Customer::className()
                ]
            ]
        );
        $fixture->expects($this->any())->method('getData')->will($this->returnValue([
            ['name' => 'name1'],
            ['name' => 'name2'],
        ]));

        $fixture->load();

        $rows = $this->findAll($this->getConnection()->getCollection(Customer::collectionName()));
        $this->assertCount(2, $rows);
    }
}