<?php

namespace yiiunit\extensions\mongodb;

use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;
use yii\mongodb\ActiveQuery;
use yiiunit\extensions\mongodb\data\ar\ActiveRecord;
use yiiunit\extensions\mongodb\data\ar\Customer;
use yiiunit\extensions\mongodb\data\ar\Animal;
use yiiunit\extensions\mongodb\data\ar\Dog;
use yiiunit\extensions\mongodb\data\ar\Cat;

class ActiveRecordTest extends TestCase
{
    /**
     * @var array[] list of test rows.
     */
    protected $testRows = [];

    protected function setUp()
    {
        parent::setUp();
        ActiveRecord::$db = $this->getConnection();
        $this->setUpTestRows();
    }

    protected function tearDown()
    {
        $this->dropCollection(Customer::collectionName());
        parent::tearDown();
    }

    /**
     * Sets up test rows.
     */
    protected function setUpTestRows()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'name' => 'name' . $i,
                'email' => 'email' . $i,
                'address' => 'address' . $i,
                'status' => $i,
            ];
        }
        $this->testRows = $collection->batchInsert($rows);
    }

    // Tests :

    public function testFind()
    {
        // find one
        $result = Customer::find();
        $this->assertTrue($result instanceof ActiveQuery);
        $customer = $result->one();
        $this->assertTrue($customer instanceof Customer);

        // find all
        $customers = Customer::find()->all();
        $this->assertEquals(10, count($customers));
        $this->assertTrue($customers[0] instanceof Customer);
        $this->assertTrue($customers[1] instanceof Customer);

        // find by _id
        $testId = $this->testRows[0]['_id'];
        $customer = Customer::findOne($testId);
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals($testId, $customer->_id);

        // find by column values
        $customer = Customer::findOne(['name' => 'name5']);
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals($this->testRows[4]['_id'], $customer->_id);
        $this->assertEquals('name5', $customer->name);
        $customer = Customer::findOne(['name' => 'unexisting name']);
        $this->assertNull($customer);

        // find by attributes
        $customer = Customer::find()->where(['status' => 4])->one();
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals(4, $customer->status);

        // find count, sum, average, min, max, distinct
        $this->assertEquals(10, Customer::find()->count());
        $this->assertEquals(1, Customer::find()->where(['status' => 2])->count());
        $this->assertEquals((1+10)/2*10, Customer::find()->sum('status'));
        $this->assertEquals((1+10)/2, Customer::find()->average('status'));
        $this->assertEquals(1, Customer::find()->min('status'));
        $this->assertEquals(10, Customer::find()->max('status'));
        $this->assertEquals(range(1, 10), Customer::find()->distinct('status'));

        // scope
        $this->assertEquals(1, Customer::find()->activeOnly()->count());

        // asArray
        $testRow = $this->testRows[2];
        $customer = Customer::find()->where(['_id' => $testRow['_id']])->asArray()->one();
        $this->assertEquals($testRow, $customer);

        // indexBy
        $customers = Customer::find()->indexBy('name')->all();
        $this->assertTrue($customers['name1'] instanceof Customer);
        $this->assertTrue($customers['name2'] instanceof Customer);

        // indexBy callable
        $customers = Customer::find()->indexBy(function ($customer) {
            return $customer->status . '-' . $customer->status;
        })->all();
        $this->assertTrue($customers['1-1'] instanceof Customer);
        $this->assertTrue($customers['2-2'] instanceof Customer);
    }

    public function testInsert()
    {
        $record = new Customer();
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = 'new address';
        $record->status = 7;

        $this->assertTrue($record->isNewRecord);

        $record->save();

        $this->assertTrue($record->_id instanceof ObjectID);
        $this->assertFalse($record->isNewRecord);
    }

    /**
     * @depends testInsert
     */
    public function testUpdate()
    {
        $record = new Customer();
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = 'new address';
        $record->status = 7;
        $record->save();

        // save
        $record = Customer::findOne($record->_id);
        $this->assertTrue($record instanceof Customer);
        $this->assertEquals(7, $record->status);
        $this->assertFalse($record->isNewRecord);

        $record->status = 9;
        $record->save();
        $this->assertEquals(9, $record->status);
        $this->assertFalse($record->isNewRecord);
        $record2 = Customer::findOne($record->_id);
        $this->assertEquals(9, $record2->status);
        $this->assertEquals('new name', $record2->name);

        // updateAll
        $pk = ['_id' => $record->_id];
        $ret = Customer::updateAll(['status' => 55], $pk);
        $this->assertEquals(1, $ret);
        $record = Customer::findOne($pk);
        $this->assertEquals(55, $record->status);
    }

    /**
     * @depends testInsert
     */
    public function testDelete()
    {
        // delete
        $record = new Customer();
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = 'new address';
        $record->status = 7;
        $record->save();

        $record = Customer::findOne($record->_id);
        $record->delete();
        $record = Customer::findOne($record->_id);
        $this->assertNull($record);

        // deleteAll
        $record = new Customer();
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = 'new address';
        $record->status = 7;
        $record->save();

        $ret = Customer::deleteAll(['name' => 'new name']);
        $this->assertEquals(1, $ret);
        $records = Customer::find()->where(['name' => 'new name'])->all();
        $this->assertEquals(0, count($records));
    }

    public function testUpdateAllCounters()
    {
        $this->assertEquals(1, Customer::updateAllCounters(['status' => 10], ['status' => 10]));

        $record = Customer::findOne(['status' => 10]);
        $this->assertNull($record);
    }

    /**
     * @depends testUpdateAllCounters
     */
    public function testUpdateCounters()
    {
        $record = Customer::findOne($this->testRows[9]);

        $originalCounter = $record->status;
        $counterIncrement = 20;
        $record->updateCounters(['status' => $counterIncrement]);
        $this->assertEquals($originalCounter + $counterIncrement, $record->status);

        $refreshedRecord = Customer::findOne($record->_id);
        $this->assertEquals($originalCounter + $counterIncrement, $refreshedRecord->status);
    }

    /**
     * @depends testUpdate
     */
    public function testUpdateNestedAttribute()
    {
        $record = new Customer();
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = [
            'city' => 'SomeCity',
            'street' => 'SomeStreet',
        ];
        $record->status = 7;
        $record->save();

        // save
        $record = Customer::findOne($record->_id);
        $newAddress = [
            'city' => 'AnotherCity'
        ];
        $record->address = $newAddress;
        $record->save();
        $record2 = Customer::findOne($record->_id);
        $this->assertEquals($newAddress, $record2->address);
    }

    /**
     * @depends testFind
     * @depends testInsert
     */
    public function testQueryByIntegerField()
    {
        $record = new Customer();
        $record->name = 'new name';
        $record->status = 7;
        $record->save();

        $row = Customer::find()->where(['status' => 7])->one();
        $this->assertNotEmpty($row);
        $this->assertEquals(7, $row->status);

        $rowRefreshed = Customer::find()->where(['status' => $row->status])->one();
        $this->assertNotEmpty($rowRefreshed);
        $this->assertEquals(7, $rowRefreshed->status);
    }

    public function testExists()
    {
        $exists = Customer::find()
            ->where(['name' => 'name1'])
            ->exists();
        $this->assertTrue($exists);

        $exists = Customer::find()
            ->where(['name' => 'not existing name'])
            ->exists();
        $this->assertFalse($exists);
    }

    public function testScalar()
    {
        $connection = $this->getConnection();

        $result = Customer::find()
            ->select(['name' => true, '_id' => false])
            ->orderBy(['name' => SORT_ASC])
            ->limit(1)
            ->scalar($connection);
        $this->assertSame('name1', $result);

        $result = Customer::find()
            ->select(['name' => true, '_id' => false])
            ->andWhere(['status' => -1])
            ->scalar($connection);
        $this->assertSame(false, $result);

        $result = Customer::find()
            ->select(['name'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(1)
            ->scalar($connection);
        $this->assertSame('name1', $result);

        $result = Customer::find()
            ->select(['_id'])
            ->limit(1)
            ->scalar($connection);
        $this->assertTrue($result instanceof ObjectID);
    }

    public function testColumn()
    {
        $connection = $this->getConnection();

        $result = Customer::find()
            ->select(['name' => true, '_id' => false])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column($connection);
        $this->assertEquals(['name1', 'name10'], $result);

        $result = Customer::find()
            ->select(['name' => true, '_id' => false])
            ->andWhere(['status' => -1])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column($connection);
        $this->assertEquals([], $result);

        $result = Customer::find()
            ->select(['name'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column($connection);
        $this->assertEquals(['name1', 'name10'], $result);

        $result = Customer::find()
            ->select(['_id'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column($connection);
        $this->assertTrue($result[0] instanceof ObjectID);
        $this->assertTrue($result[1] instanceof ObjectID);
    }

    public function testModify()
    {
        $searchName = 'name7';
        $newName = 'new name';

        $customer = Customer::find()
            ->where(['name' => $searchName])
            ->modify(['$set' => ['name' => $newName]], ['new' => true]);
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals($newName, $customer->name);

        $customer = Customer::find()
            ->where(['name' => 'not existing name'])
            ->modify(['$set' => ['name' => $newName]], ['new' => false]);
        $this->assertNull($customer);
    }

    /**
     * @depends testInsert
     *
     * @see https://github.com/yiisoft/yii2/issues/6026
     */
    public function testInsertEmptyAttributes()
    {
        $record = new Customer();
        $record->save(false);

        $this->assertTrue($record->_id instanceof ObjectID);
        $this->assertFalse($record->isNewRecord);
    }

    /**
     * @depends testFind
     * @depends testInsert
     */
    public function testPopulateRecordCallWhenQueryingOnParentClass()
    {
        (new Cat())->save(false);
        (new Dog())->save(false);

        $animal = Animal::find()->where(['type' => Dog::className()])->one();
        $this->assertEquals('bark', $animal->getDoes());

        $animal = Animal::find()->where(['type' => Cat::className()])->one();
        $this->assertEquals('meow', $animal->getDoes());
    }

    /**
     * @see https://github.com/yiisoft/yii2-mongodb/issues/79
     */
    public function testToArray()
    {
        $record = new Customer();
        $record->name = 'test name';
        $record->email = new Regex('[a-z]@[a-z]', 'i');
        $record->address = new Binary('abcdef', Binary::TYPE_MD5);
        $record->status = 1;
        $record->file_id = new Binary('Test Binary', Binary::TYPE_GENERIC);;
        $record->save(false);

        $this->assertEquals($record->attributes, $record->toArray([], [], false));

        $array = $record->toArray([], [], true);
        $this->assertTrue(is_string($array['_id']));
        $this->assertEquals('/[a-z]@[a-z]/i', $array['email']);
        $this->assertEquals('abcdef', $array['address']);
        $this->assertEquals('Test Binary', $array['file_id']);
    }

    /**
     * @depends testInsert
     *
     * @see https://github.com/yiisoft/yii2-mongodb/pull/146
     */
    public function testInsertCustomId()
    {
        $record = new Customer();
        $record->_id = 'custom';
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = 'new address';
        $record->status = 7;

        $record->save(false);

        $this->assertEquals('custom', $record->_id);
    }

    public function testEmulateExecution()
    {
        if (!Customer::find()->hasMethod('emulateExecution')) {
            $this->markTestSkipped('"yii2" version 2.0.11 or higher required');
        }

        $this->assertGreaterThan(0, Customer::find()->from('customer')->count());

        $rows = Customer::find()
            ->from('customer')
            ->emulateExecution()
            ->all();
        $this->assertSame([], $rows);

        $row = Customer::find()
            ->from('customer')
            ->emulateExecution()
            ->one();
        $this->assertSame(null, $row);

        $exists = Customer::find()
            ->from('customer')
            ->emulateExecution()
            ->exists();
        $this->assertSame(false, $exists);

        $count = Customer::find()
            ->from('customer')
            ->emulateExecution()
            ->count();
        $this->assertSame(0, $count);

        $sum = Customer::find()
            ->from('customer')
            ->emulateExecution()
            ->sum('id');
        $this->assertSame(0, $sum);

        $sum = Customer::find()
            ->from('customer')
            ->emulateExecution()
            ->average('id');
        $this->assertSame(0, $sum);

        $max = Customer::find()
            ->from('customer')
            ->emulateExecution()
            ->max('id');
        $this->assertSame(null, $max);

        $min = Customer::find()
            ->from('customer')
            ->emulateExecution()
            ->min('id');
        $this->assertSame(null, $min);

        $scalar = Customer::find()
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->scalar();
        $this->assertSame(null, $scalar);

        $column = Customer::find()
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->column();
        $this->assertSame([], $column);

        $row = Customer::find()
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->modify(['name' => 'new name']);
        $this->assertSame(null, $row);

        $values = Customer::find()
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->distinct('name');
        $this->assertSame([], $values);
    }
}