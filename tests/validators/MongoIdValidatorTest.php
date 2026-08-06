<?php

namespace yiiunit\extensions\mongodb\validators;

use MongoDB\BSON\ObjectId;
use yii\base\Model;
use yii\mongodb\validators\MongoIdValidator;
use yiiunit\extensions\mongodb\TestCase;

class MongoIdValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockApplication();
    }

    public function testValidateValue()
    {
        $validator = new MongoIdValidator();
        $this->assertFalse($validator->validate('id'));
        $this->assertTrue($validator->validate(new ObjectId('4d3ed089fb60ab534684b7e9')));
        $this->assertTrue($validator->validate('4d3ed089fb60ab534684b7e9'));
    }

    public function testValidateValueRejectsUnsupportedTypes(): void
    {
        $validator = new MongoIdValidator();

        $this->assertFalse($validator->validate([]), 'Empty array must be rejected.');
        $this->assertFalse($validator->validate(['4d3ed089fb60ab534684b7e9']), 'Array must be rejected.');
        $this->assertFalse($validator->validate(new \stdClass()), 'Object without `__toString()` must be rejected.');
        $this->assertFalse($validator->validate(null), '`null` must not yield a freshly generated ID.');
    }

    public function testValidateValueAcceptsStringable(): void
    {
        $validator = new MongoIdValidator();

        $value = new MongoIdStringable('4d3ed089fb60ab534684b7e9');

        $this->assertTrue($validator->validate($value), 'Stringable holding a valid hex ID must be accepted.');
    }

    public function testValidateAttributeRejectsArrayWithoutFatalError(): void
    {
        $model = new MongoIdTestModel();
        $model->id = ['4d3ed089fb60ab534684b7e9'];

        $validator = new MongoIdValidator();
        $validator->validateAttribute($model, 'id');

        $this->assertTrue($model->hasErrors('id'), 'Array attribute must record an error, not raise `TypeError`.');
    }

    public function testValidateAttribute()
    {
        $model = new MongoIdTestModel();
        $validator = new MongoIdValidator(['attributes' => ['id']]);
        $model->getValidators()->append($validator);

        $model->id = 'id';
        $this->assertFalse($model->validate());
        $model->id = new ObjectId('4d3ed089fb60ab534684b7e9');
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
        $model->id = new ObjectId('4d3ed089fb60ab534684b7e9');
        $model->validate();
        $this->assertTrue($model->id instanceof ObjectId);

        $validator->forceFormat = 'object';
        $model->id = '4d3ed089fb60ab534684b7e9';
        $model->validate();
        $this->assertTrue($model->id instanceof ObjectId);

        $validator->forceFormat = 'string';
        $model->id = new ObjectId('4d3ed089fb60ab534684b7e9');
        $model->validate();
        $this->assertTrue(is_string($model->id));
    }
}

class MongoIdTestModel extends Model
{
    public $id;
}

class MongoIdStringable
{
    private string $_value;

    public function __construct(string $value)
    {
        $this->_value = $value;
    }

    public function __toString(): string
    {
        return $this->_value;
    }
}
