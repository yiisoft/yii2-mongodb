<?php

namespace yiiunit\extensions\mongodb\validators;

use MongoDB\BSON\ObjectID;
use yii\base\Model;
use yii\mongodb\validators\MongoIdValidator;
use yiiunit\extensions\mongodb\TestCase;

class MongoIdValidatorTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
    }

    public function testValidateValue()
    {
        $validator = new MongoIdValidator();
        $this->assertFalse($validator->validate('id'));
        $this->assertTrue($validator->validate(new ObjectID('4d3ed089fb60ab534684b7e9')));
        $this->assertTrue($validator->validate('4d3ed089fb60ab534684b7e9'));
    }

    public function testValidateAttribute()
    {
        $model = new MongoIdTestModel();
        $validator = new MongoIdValidator(['attributes' => ['id']]);
        $model->getValidators()->append($validator);

        $model->id = 'id';
        $this->assertFalse($model->validate());
        $model->id = new ObjectID('4d3ed089fb60ab534684b7e9');
        $this->assertTrue($model->validate());
        $model->id = '4d3ed089fb60ab534684b7e9';
        $this->assertTrue($model->validate());
    }

    /**
     * @depends testValidateAttribute
     */
    public function testConvertValue()
    {
        $model = new MongoIdTestModel();
        $validator = new MongoIdValidator(['attributes' => ['id']]);
        $model->getValidators()->append($validator);

        $validator->forceFormat = null;
        $model->id = '4d3ed089fb60ab534684b7e9';
        $model->validate();
        $this->assertTrue(is_string($model->id));
        $model->id = new ObjectID('4d3ed089fb60ab534684b7e9');
        $model->validate();
        $this->assertTrue($model->id instanceof ObjectID);

        $validator->forceFormat = 'object';
        $model->id = '4d3ed089fb60ab534684b7e9';
        $model->validate();
        $this->assertTrue($model->id instanceof ObjectID);

        $validator->forceFormat = 'string';
        $model->id = new ObjectID('4d3ed089fb60ab534684b7e9');
        $model->validate();
        $this->assertTrue(is_string($model->id));
    }
}

class MongoIdTestModel extends Model
{
    public $id;
}