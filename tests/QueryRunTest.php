<?php

namespace yiiunit\extensions\mongodb;

use MongoDB\BSON\ObjectID;
use yii;
use yii\mongodb\Query;

class QueryRunTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->setUpTestRows();
    }

    protected function tearDown()
    {
        $this->dropCollection('customer');
        parent::tearDown();
    }

    /**
     * Sets up test rows.
     */
    protected function setUpTestRows()
    {
        $collection = yii::$app->mongodb->getCollection('customer');
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'name' => 'name' . $i,
                'status' => $i,
                'address' => 'address' . $i,
                'group' => ($i % 2 === 0) ? 'even' : 'odd',
                'avatar' => [
                    'width' => 50 + $i,
                    'height' => 100 + $i,
                    'url' => 'http://some.url/' . $i,
                ],
            ];
        }
        $collection->batchInsert($rows);
    }

    // Tests :

    public function testAll()
    {
        $query = new Query();
        $rows = $query->from('customer')->all();
        $this->assertEquals(10, count($rows));
    }

    public function testDirectMatch()
    {
        $query = new Query();
        $rows = $query->from('customer')
            ->where(['name' => 'name1'])
            ->all();
        $this->assertEquals(1, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
    }

    public function testIndexBy()
    {
        $query = new Query();
        $rows = $query->from('customer')
            ->indexBy('name')
            ->all();
        $this->assertEquals(10, count($rows));
        $this->assertNotEmpty($rows['name1']);
    }

    public function testInCondition()
    {
        $query = new Query();
        $rows = $query->from('customer')
            ->where([
                'name' => ['name1', 'name5']
            ])
            ->all();
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name5', $rows[1]['name']);
    }

    public function testNotInCondition()
    {

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not in', 'name', ['name1', 'name5']])
            ->all();
        $this->assertEquals(8, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not in', 'name', ['name1']])
            ->all();
        $this->assertEquals(9, count($rows));
    }

    /**
     * @depends testInCondition
     */
    public function testCompositeInCondition()
    {
        $query = new Query();
        $rows = $query->from('customer')
            ->where([
                'in',
                ['status', 'name'],
                [
                    ['status' => 1, 'name' => 'name1'],
                    ['status' => 3, 'name' => 'name3'],
                    ['status' => 5, 'name' => 'name7'],
                ]
            ])
            ->all();
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name3', $rows[1]['name']);
    }

    public function testOrCondition()
    {
        $query = new Query();
        $rows = $query->from('customer')
            ->where(['name' => 'name1'])
            ->orWhere(['address' => 'address5'])
            ->all();
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('address5', $rows[1]['address']);
    }

    public function testCombinedInAndCondition()
    {
        $query = new Query();
        $rows = $query->from('customer')
            ->where([
                'name' => ['name1', 'name5']
            ])
            ->andWhere(['name' => 'name1'])
            ->all();
        $this->assertEquals(1, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
    }

    public function testCombinedInLikeAndCondition()
    {
        $query = new Query();
        $rows = $query->from('customer')
            ->where([
                'name' => ['name1', 'name5', 'name10']
            ])
            ->andWhere(['LIKE', 'name', 'me1'])
            ->andWhere(['name' => 'name10'])
            ->all();
        $this->assertEquals(1, count($rows));
        $this->assertEquals('name10', $rows[0]['name']);
    }

    public function testNestedCombinedInAndCondition()
    {
        $query = new Query();
        $rows = $query->from('customer')
            ->where([
                'and',
                ['name' => ['name1', 'name2', 'name3']],
                ['name' => 'name1']
            ])
            ->orWhere([
                'and',
                ['name' => ['name4', 'name5', 'name6']],
                ['name' => 'name6']
            ])
            ->all();
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name6', $rows[1]['name']);
    }

    public function testOrder()
    {

        $query = new Query();
        $rows = $query->from('customer')
            ->orderBy(['name' => SORT_DESC])
            ->all();
        $this->assertEquals('name9', $rows[0]['name']);

        $query = new Query();
        $rows = $query->from('customer')
            ->orderBy(['avatar.height' => SORT_DESC])
            ->all();
        $this->assertEquals('name10', $rows[0]['name']);
    }

    public function testMatchPlainId()
    {
        $query = new Query();
        $row = $query->from('customer')->one();
        $query = new Query();
        $rows = $query->from('customer')
            ->where(['_id' => $row['_id']->__toString()])
            ->all();
        $this->assertEquals(1, count($rows));
    }

    public function testRegex()
    {
        $query = new Query();
        $rows = $query->from('customer')
            ->where(['REGEX', 'name', '/me1/'])
            ->all();
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name10', $rows[1]['name']);
    }

    public function testLike()
    {

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['LIKE', 'name', 'me1'])
            ->all();
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name10', $rows[1]['name']);

        $query = new Query();
        $rowsUppercase = $query->from('customer')
            ->where(['LIKE', 'name', 'ME1'])
            ->all();
        $this->assertEquals($rows, $rowsUppercase);
    }

    public function testCompare()
    {

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['$gt', 'status', 8])
            ->all();
        $this->assertEquals(2, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['>', 'status', 8])
            ->all();
        $this->assertEquals(2, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['<=', 'status', 3])
            ->all();
        $this->assertEquals(3, count($rows));
    }

    public function testNot()
    {

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not', 'status', ['$gte' => 10]])
            ->all();
        $this->assertEquals(9, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not', 'name', 'name1'])
            ->all();
        $this->assertEquals(9, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not', 'name', null])
            ->all();
        $this->assertEquals(10, count($rows));
    }

    public function testExists()
    {

        $query = new Query();
        $exists = $query->from('customer')
            ->where(['name' => 'name1'])
            ->exists();
        $this->assertTrue($exists);

        $query = new Query();
        $exists = $query->from('customer')
            ->where(['name' => 'un-existing-name'])
            ->exists();
        $this->assertFalse($exists);
    }

    public function testModify()
    {

        $query = new Query();

        $searchName = 'name5';
        $newName = 'new name';
        $row = $query->from('customer')
            ->where(['name' => $searchName])
            ->modify(['$set' => ['name' => $newName]], ['new' => false]);
        $this->assertEquals($searchName, $row['name']);

        $searchName = 'name7';
        $newName = 'new name';
        $row = $query->from('customer')
            ->where(['name' => $searchName])
            ->modify(['$set' => ['name' => $newName]], ['new' => true]);
        $this->assertEquals($newName, $row['name']);

        $row = $query->from('customer')
            ->where(['name' => 'not existing name'])
            ->modify(['$set' => ['name' => 'new name']], ['new' => false]);
        $this->assertNull($row);
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/4879
     * @see https://github.com/yiisoft/yii2-mongodb/issues/101
     *
     * @depends testInCondition
     */
    public function testInConditionIgnoreKeys()
    {
        $query = new Query();
        $rows = $query->from('customer')
            /*->where([
                'name' => [
                    0 => 'name1',
                    15 => 'name5'
                ]
            ])*/
            ->where(['in', 'name', [
                10 => 'name1',
                15 => 'name5'
            ]])
            ->all();
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name5', $rows[1]['name']);

        // @see https://github.com/yiisoft/yii2-mongodb/issues/101
        $query = new Query();
        $rows = $query->from('customer')
            ->where(['_id' => [
                10 => $rows[0]['_id'],
                15 => $rows[1]['_id']
            ]])
            ->all();
        $this->assertEquals(2, count($rows));
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/7010
     */
    public function testSelect()
    {
        $query = new Query();
        $rows = $query->from('customer')
            ->select(['name' => true, '_id' => false])
            ->limit(1)
            ->all();
        $row = array_pop($rows);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayNotHasKey('address', $row);
        $this->assertArrayNotHasKey('_id', $row);
    }

    public function testScalar()
    {

        $result = (new Query())
            ->select(['name' => true, '_id' => false])
            ->from('customer')
            ->orderBy(['name' => SORT_ASC])
            ->limit(1)
            ->scalar();
        $this->assertSame('name1', $result);

        $result = (new Query())
            ->select(['name' => true, '_id' => false])
            ->from('customer')
            ->andWhere(['status' => -1])
            ->scalar();
        $this->assertSame(false, $result);

        $result = (new Query())
            ->select(['name'])
            ->from('customer')
            ->orderBy(['name' => SORT_ASC])
            ->limit(1)
            ->scalar();
        $this->assertSame('name1', $result);

        $result = (new Query())
            ->select(['_id'])
            ->from('customer')
            ->limit(1)
            ->scalar();
        $this->assertTrue($result instanceof ObjectID);
    }

    public function testColumn()
    {

        $result = (new Query())->from('customer')
            ->select(['name' => true, '_id' => false])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column();
        $this->assertEquals(['name1', 'name10'], $result);

        $result = (new Query())->from('customer')
            ->select(['name' => true, '_id' => false])
            ->andWhere(['status' => -1])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column();
        $this->assertEquals([], $result);

        $result = (new Query())->from('customer')
            ->select(['name'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column();
        $this->assertEquals(['name1', 'name10'], $result);

        $result = (new Query())->from('customer')
            ->select(['_id'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column();
        $this->assertTrue($result[0] instanceof ObjectID);
        $this->assertTrue($result[1] instanceof ObjectID);
    }

    /**
     * @depends testColumn
     */
    public function testColumnIndexBy()
    {

        $result = (new Query())->from('customer')
            ->select(['name'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->indexBy('status')
            ->column();
        $this->assertEquals([1 => 'name1', 10 => 'name10'], $result);

        $result = (new Query())->from('customer')
            ->select(['name', 'status'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->indexBy(function ($row) {
                return $row['status'] * 2;
            })
            ->column();
        $this->assertEquals([2 => 'name1', 20 => 'name10'], $result);

        $result = (new Query())->from('customer')
            ->select(['name'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->indexBy('name')
            ->column();
        $this->assertEquals(['name1' => 'name1', 'name10' => 'name10'], $result);
    }

    public function testEmulateExecution()
    {
        $query = new Query();
        if (!$query->hasMethod('emulateExecution')) {
            $this->markTestSkipped('"yii2" version 2.0.11 or higher required');
        }

        $this->assertGreaterThan(0, $query->from('customer')->count());

        $rows = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->all();
        $this->assertSame([], $rows);

        $row = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->one();
        $this->assertSame(false, $row);

        $exists = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->exists();
        $this->assertSame(false, $exists);

        $count = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->count();
        $this->assertSame(0, $count);

        $sum = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->sum('id');
        $this->assertSame(0, $sum);

        $sum = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->average('id');
        $this->assertSame(0, $sum);

        $max = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->max('id');
        $this->assertSame(null, $max);

        $min = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->min('id');
        $this->assertSame(null, $min);

        $scalar = (new Query())
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->scalar();
        $this->assertSame(null, $scalar);

        $column = (new Query())
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->column();
        $this->assertSame([], $column);

        $row = (new Query())
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->modify(['name' => 'new name']);
        $this->assertSame(null, $row);

        $values = (new Query())
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->distinct('name');
        $this->assertSame([], $values);
    }

    /**
     * @depends testAll
     *
     * @see https://github.com/yiisoft/yii2-mongodb/issues/205
     */
    public function testOffsetLimit()
    {

        $rows = (new Query())
            ->from('customer')
            ->limit(2)
            ->all();
        $this->assertCount(2, $rows);

        $rows = (new Query())
            ->from('customer')
            ->limit(-1)
            ->all();
        $this->assertCount(10, $rows);

        $rows = (new Query())
            ->from('customer')
            ->orderBy(['name' => SORT_ASC])
            ->offset(2)
            ->limit(1)
            ->all();
        $this->assertCount(1, $rows);
        $this->assertEquals('name2', $rows[0]['name']);

        $rows = (new Query())
            ->from('customer')
            ->orderBy(['name' => SORT_ASC])
            ->offset(-1)
            ->limit(1)
            ->all();
        $this->assertCount(1, $rows);
        $this->assertEquals('name1', $rows[0]['name']);
    }

    public function testDistinct()
    {

        $rows = (new Query())
            ->from('customer')
            ->distinct('group');

        $this->assertSame(['odd', 'even'], $rows);
    }

    public function testAggregationShortcuts()
    {

        $max = (new Query())
            ->from('customer')
            ->where(['group' => 'odd'])
            ->count();
        $this->assertSame(5, $max);

        $max = (new Query())
            ->from('customer')
            ->where(['group' => 'even'])
            ->max('status');
        $this->assertSame(10, $max);

        $max = (new Query())
            ->from('customer')
            ->where(['group' => 'even'])
            ->min('status');
        $this->assertSame(2, $max);

        $max = (new Query())
            ->from('customer')
            ->where(['group' => 'even'])
            ->sum('status');
        $this->assertSame(30, $max);

        $max = (new Query())
            ->from('customer')
            ->where(['group' => 'even'])
            ->average('status');
        $this->assertEquals(6, $max);
    }
}