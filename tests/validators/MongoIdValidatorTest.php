<?php

namespace yiiunit\extensions\mongodb\validators;

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
        $this->assertTrue($validator->validate(MongoIdTestModel::$objectId));
        $this->assertTrue($validator->validate(MongoIdTestModel::$stringId));
        $this->assertFalse($validator->validate([MongoIdTestModel::$stringId]));
    }

    public function testValidateAttribute()
    {
        $model = new MongoIdTestModel();
        $validator = new MongoIdValidator();
        $validator->attributes = ['id'];
        $model->getValidators()->append($validator);

        $model->id = 'id';
        $this->assertFalse($model->validate());
        $model->id = $model::$objectId;
        $this->assertTrue($model->validate());
        $model->id = $model::$stringId;
        $this->assertTrue($model->validate());

        $validator->attributes = ['ids'];
        $model->ids = [$model::$stringId];
        $this->assertFalse($model->validate());
        $validator->expectArray = true;
        $this->assertTrue($model->validate());
        array_push($model->ids, $model::$objectId);
        $this->assertTrue($model->validate());
        array_push($model->ids, 'id');
        $this->assertFalse($model->validate());
        $model->ids = $model::$stringId;
        $this->assertFalse($model->validate());
    }

    /**
     * @depends testValidateAttribute
     */
    public function testConvertValue()
    {
        $model = new MongoIdTestModel();
        $validator = new MongoIdValidator();
        $validator->attributes = ['id'];
        $model->getValidators()->append($validator);

        $validator->forceFormat = null;
        $model->id = $model::$stringId;
        $model->validate();
        $this->assertInternalType('string', $model->id);
        $model->id = $model::$objectId;
        $model->validate();
        $this->assertInstanceOf($model::$objectIdClass, $model->id);

        $validator->forceFormat = 'object';
        $model->id = $model::$stringId;
        $model->validate();
        $this->assertInstanceOf($model::$objectIdClass, $model->id);

        $validator->forceFormat = 'string';
        $model->id = $model::$objectId;
        $model->validate();
        $this->assertInternalType('string', $model->id);

        $validator->attributes = ['ids'];
        $validator->expectArray = true;
        $model->ids = [$model::$stringId, $model::$objectId];

        $validator->forceFormat = null;
        $model->validate();
        $this->assertSame($model::$stringId, $model->ids[0]);
        $this->assertSame($model::$objectId, $model->ids[1]);

        $validator->forceFormat = 'object';
        $model->validate();
        $this->assertInstanceOf($model::$objectIdClass, $model->ids[0]);
        $this->assertInstanceOf($model::$objectIdClass, $model->ids[1]);

        $validator->forceFormat = 'string';
        $model->ids = [$model::$stringId, $model::$objectId];
        $model->validate();
        $this->assertInternalType('string', $model->ids[0]);
        $this->assertInternalType('string', $model->ids[1]);
    }
}

class MongoIdTestModel extends Model
{
    public static $stringId = '4d3ed089fb60ab534684b7e9';
    public static $objectId;
    public static $objectIdClass = '\MongoDB\BSON\ObjectID';

    public $id;
    public $ids;

    public function init()
    {
        parent::init();
        self::$objectId = new self::$objectIdClass(self::$stringId);
    }
}
