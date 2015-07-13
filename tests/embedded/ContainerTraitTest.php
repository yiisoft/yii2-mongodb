<?php

namespace yiiunit\extensions\mongodb\embedded;

use yii\base\Object;
use yii\mongodb\embedded\ContainerInterface;
use yii\mongodb\embedded\ContainerTrait;
use yiiunit\extensions\mongodb\TestCase;

class ContainerTraitTest extends TestCase
{
    public function testFillUpEmbed()
    {
        $container = new Container();
        $container->modelData = [
            'name1' => 'value1',
            'name2' => 'value2',
        ];
        $this->assertTrue($container->getEmbedded('model') instanceof \stdClass);
        $this->assertTrue($container->getEmbedded('model') === $container->model);
        $this->assertEquals('value1', $container->model->name1);
        $this->assertEquals('value2', $container->model->name2);
    }

    public function testFillUpEmbedList()
    {
        $container = new Container();
        $container->listData = [
            [
                'name' => 'name1',
            ],
            [
                'name' => 'name2',
            ],
        ];
        $this->assertTrue($container->getEmbedded('list') === $container->list);
        $this->assertTrue($container->list[0] instanceof \stdClass);
        $this->assertTrue($container->list[1] instanceof \stdClass);

        $this->assertEquals('name1', $container->list[0]->name);
        $this->assertEquals('name2', $container->list[1]->name);
    }

    /**
     * @depends testFillUpEmbed
     */
    public function testSetupEmbed()
    {
        $container = new Container();

        $model = new \stdClass();
        $model->name = 'new';
        $container->model = $model;

        $this->assertEquals('new', $container->model->name);
    }

    /**
     * @depends testFillUpEmbed
     */
    public function testSetupEmbedList()
    {
        $container = new Container();

        $model = new \stdClass();
        $model->name = 'new';
        $list = [
            $model,
        ];
        $container->list = $list;

        $this->assertEquals('new', $container->list[0]->name);
        $this->assertTrue(is_object($container->list));
    }

    /**
     * @depends testFillUpEmbed
     * @depends testFillUpEmbedList
     */
    public function testGetEmbedValues()
    {
        $container = new Container();
        $container->modelData = [
            'name' => 'value1',
        ];
        $container->listData = [
            [
                'name' => 'name1',
            ],
        ];

        $container->model->name = 'new name';
        $container->list[0]->name = 'new list name';

        $embedValues = $container->getEmbeddedValues();
        $expectedEmbedValues = [
            'modelData' => [
                'name' => 'new name'
            ],
            'listData' => [
                [
                    'name' => 'new list name'
                ]
            ]
        ];
        $this->assertEquals($expectedEmbedValues, $embedValues);
    }

    /**
     * @depends testGetEmbedValues
     * @depends testSetupEmbed
     */
    public function testGetNestedEmbedValues()
    {
        $container = new Container();
        $container->self = new Container();
        $container->self->model->name = 'self name';

        $embedValues = $container->getEmbeddedValues();
        $expectedEmbedValues = [
            'selfData' => [
                'modelData' => [
                    'name' => 'self name'
                ],
                'listData' => [],
                'selfData' => [],
                'nullData' => null,
            ],
        ];
        $this->assertEquals($expectedEmbedValues, $embedValues);
    }

    /**
     * @depends testGetEmbedValues
     */
    public function testRefreshFromEmbedded()
    {
        $container = new Container();
        $container->modelData = [
            'name' => 'value1',
        ];

        $container->model->name = 'new name';
        $container->refreshFromEmbedded();

        $this->assertEquals('new name', $container->modelData['name']);
    }

    /**
     * @depends testRefreshFromEmbedded
     */
    public function testRefreshFromEmbeddedObjectValue()
    {
        $container = new Container();
        $container->modelData = [
            'object' => new \stdClass(),
        ];
        $container->model->object->name = 'object name';
        $container->refreshFromEmbedded();

        $this->assertTrue(is_object($container->modelData['object']));
        $this->assertEquals('object name', $container->modelData['object']->name);
    }

    public function testCreateFromNull()
    {
        $container = new Container();

        $this->assertNull($container->null);
        $this->assertTrue(is_object($container->nullAutoCreate));
    }

    /**
     * @depends testFillUpEmbed
     */
    public function testUnsetSource()
    {
        $container = new Container();
        $container->modelData = [
            'name1' => 'value1',
            'name2' => 'value2',
        ];
        $embedded = $container->getEmbedded('model');
        $this->assertNull($container->modelData);

        $container = new Container();
        $container->nullData = [
            'name1' => 'value1',
            'name2' => 'value2',
        ];
        $embedded = $container->getEmbedded('null');
        $this->assertNotNull($container->modelData);
    }
}

/**
 * @property \stdClass $model
 * @property \stdClass[] $list
 * @property Container $self
 * @property \stdClass[] $null
 * @property \stdClass[] $nullAutoCreate
 */
class Container extends Object implements ContainerInterface
{
    use ContainerTrait;

    public $modelData = [];
    public $listData = [];
    public $selfData = [];
    public $nullData;

    public function embedModel()
    {
        return $this->mapEmbedded('modelData', 'stdClass');
    }

    public function embedList()
    {
        return $this->mapEmbeddedList('listData', 'stdClass');
    }

    public function embedSelf()
    {
        return $this->mapEmbedded('selfData', __CLASS__);
    }

    public function embedNull()
    {
        return $this->mapEmbedded('nullData', 'stdClass', ['createFromNull' => false, 'unsetSource' => false]);
    }

    public function embedNullAutoCreate()
    {
        return $this->mapEmbedded('nullData', 'stdClass');
    }
}