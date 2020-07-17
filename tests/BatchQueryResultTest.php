<?php

namespace yiiunit\extensions\mongodb;

use yii\mongodb\BatchQueryResult;
use yii\mongodb\Query;
use yiiunit\extensions\mongodb\data\ar\Customer;
use yiiunit\extensions\mongodb\data\ar\CustomerOrder;

class BatchQueryResultTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->setUpTestRows();
    }

    protected function tearDown()
    {
        $this->dropCollection(Customer::collectionName());
        $this->dropCollection(CustomerOrder::collectionName());
        parent::tearDown();
    }

    /**
     * Sets up test rows.
     */
    protected function setUpTestRows()
    {
        $customers = [];
        for ($i = 1; $i <= 9; $i++) {
            $customers[] = [
                'name' => 'name' . $i,
                'email' => 'email' . $i,
                'address' => 'address' . $i,
                'status' => $i,
            ];
        }
        $customerCollection = yii::$app->mongodb->getCollection('customer');
        $customers = $customerCollection->batchInsert($customers);

        $customerOrders = [];
        foreach ($customers as $i => $customer) {
            $customerOrders[] = [
                'customer_id' => $customer['_id'],
                'number' => $customer['status'],
            ];
            $customerOrders[] = [
                'customer_id' => $customer['_id'],
                'number' => $customer['status'] + 100,
            ];
        }
        $customerOrderCollection = yii::$app->mongodb->getCollection('customer_order');
        $customerOrderCollection->batchInsert($customerOrders);
    }

    // Tests :

    public function testQuery()
    {

        // initialize property test
        $query = new Query();
        $query->from('customer')->orderBy('id');
        $result = $query->batch(2);
        $this->assertTrue($result instanceof BatchQueryResult);
        $this->assertEquals(2, $result->batchSize);
        $this->assertTrue($result->query === $query);

        // normal query
        $query = new Query();
        $query->from('customer');
        $allRows = [];
        $batch = $query->batch(2);
        foreach ($batch as $rows) {
            $allRows = array_merge($allRows, $rows);
        }
        $this->assertEquals(9, count($allRows));

        // sorted query
        $query = new Query();
        $query->from('customer')->orderBy('name');
        $allRows = [];
        $batch = $query->batch(2);
        foreach ($batch as $rows) {
            $allRows = array_merge($allRows, $rows);
        }
        $this->assertEquals(9, count($allRows));
        $this->assertEquals('name1', $allRows[0]['name']);
        $this->assertEquals('name2', $allRows[1]['name']);
        $this->assertEquals('name3', $allRows[2]['name']);

        // rewind
        $allRows = [];
        foreach ($batch as $rows) {
            $allRows = array_merge($allRows, $rows);
        }
        $this->assertEquals(9, count($allRows));
        // reset
        $batch->reset();

        // empty query
        $query = new Query();
        $query->from('customer')->where(['name' => 'unexistingName']);
        $allRows = [];
        $batch = $query->batch(2);
        foreach ($batch as $rows) {
            $allRows = array_merge($allRows, $rows);
        }
        $this->assertEquals(0, count($allRows));

        // query with index
        $query = new Query();
        $query->from('customer')->indexBy('name');
        $allRows = [];
        foreach ($query->batch(2) as $rows) {
            $allRows = array_merge($allRows, $rows);
        }
        $this->assertEquals(9, count($allRows));
        $this->assertEquals('address1', $allRows['name1']['address']);
        $this->assertEquals('address2', $allRows['name2']['address']);
        $this->assertEquals('address3', $allRows['name3']['address']);

        // each
        $query = new Query();
        $query->from('customer')->orderBy('name');
        $allRows = [];
        foreach ($query->each(100) as $rows) {
            $allRows[] = $rows;
        }
        $this->assertEquals(9, count($allRows));
        $this->assertEquals('name1', $allRows[0]['name']);
        $this->assertEquals('name2', $allRows[1]['name']);
        $this->assertEquals('name3', $allRows[2]['name']);

        // each with key
        $query = new Query();
        $query->from('customer')->orderBy('name')->indexBy('name');
        $allRows = [];
        foreach ($query->each(100) as $key => $row) {
            $allRows[$key] = $row;
        }
        $this->assertEquals(9, count($allRows));
        $this->assertEquals('address1', $allRows['name1']['address']);
        $this->assertEquals('address2', $allRows['name2']['address']);
        $this->assertEquals('address3', $allRows['name3']['address']);
    }

    public function testActiveQuery()
    {

        $query = Customer::find()->orderBy('id');
        $customers = [];
        foreach ($query->batch(2) as $models) {
            $customers = array_merge($customers, $models);
        }
        $this->assertEquals(9, count($customers));
        $this->assertEquals('name1', $customers[0]->name);
        $this->assertEquals('name2', $customers[1]->name);
        $this->assertEquals('name3', $customers[2]->name);

        // batch with eager loading
        $query = Customer::find()->with('orders')->orderBy('id');
        $customers = [];
        foreach ($query->batch(2) as $models) {
            $customers = array_merge($customers, $models);
            foreach ($models as $model) {
                $this->assertTrue($model->isRelationPopulated('orders'));
            }
        }
        $this->assertEquals(9, count($customers));
        $this->assertEquals(2, count($customers[0]->orders));
        $this->assertEquals(2, count($customers[1]->orders));
        $this->assertEquals(2, count($customers[2]->orders));
    }
}