<?php

namespace yiiunit\extensions\mongodb\embed;

use yii\base\Object;
use yii\mongodb\embed\ContainerInterface;
use yii\mongodb\embed\ContainerTrait;
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
        $this->assertTrue($container->getEmbed('model') instanceof \stdClass);
        $this->assertTrue($container->getEmbed('model') === $container->model);
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
        $this->assertTrue($container->getEmbed('list') === $container->list);
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

        $embedValues = $container->getEmbedValues();
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

        $embedValues = $container->getEmbedValues();
        $expectedEmbedValues = [
            'selfData' => [
                'modelData' => [
                    'name' => 'self name'
                ],
                'listData' => [],
                'selfData' => [],
            ],
        ];
        $this->assertEquals($expectedEmbedValues, $embedValues);
    }

    /**
     * @depends testGetEmbedValues
     */
    public function testSynchronizeWithEmbed()
    {
        $container = new Container();
        $container->modelData = [
            'name' => 'value1',
        ];

        $container->model->name = 'new name';
        $container->synchronizeWithEmbed();

        $this->assertEquals('new name', $container->modelData['name']);
    }
}

/**
 * @property \stdClass $model
 * @property \stdClass[] $list
 * @property Container $self
 */
class Container extends Object implements ContainerInterface
{
    use ContainerTrait;

    public $modelData = [];
    public $listData = [];
    public $selfData = [];

    public function embedModel()
    {
        return $this->hasEmbed('modelData', 'stdClass');
    }

    public function embedList()
    {
        return $this->hasEmbedList('listData', 'stdClass');
    }

    public function embedSelf()
    {
        return $this->hasEmbed('selfData', __CLASS__);
    }
}