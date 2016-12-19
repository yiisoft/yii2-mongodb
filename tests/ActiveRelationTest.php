<?php

namespace yiiunit\extensions\mongodb;

use yiiunit\extensions\mongodb\data\ar\ActiveRecord;
use yiiunit\extensions\mongodb\data\ar\Customer;
use yiiunit\extensions\mongodb\data\ar\CustomerOrder;
use yiiunit\extensions\mongodb\data\ar\Item;

class ActiveRelationTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        ActiveRecord::$db = $this->getConnection();
        $this->setUpTestRows();
    }

    protected function tearDown()
    {
        $this->dropCollection(Customer::collectionName());
        $this->dropCollection(CustomerOrder::collectionName());
        $this->dropCollection(Item::collectionName());
        parent::tearDown();
    }

    /**
     * Sets up test rows.
     */
    protected function setUpTestRows()
    {
        $customers = [];
        for ($i = 1; $i <= 5; $i++) {
            $customers[] = [
                'name' => 'name' . $i,
                'email' => 'email' . $i,
                'address' => 'address' . $i,
                'status' => $i,
            ];
        }
        $customerCollection = $this->getConnection()->getCollection('customer');
        $customers = $customerCollection->batchInsert($customers);

        $items = [];
        for ($i = 1; $i <= 10; $i++) {
            $items[] = [
                'name' => 'name' . $i,
                'price' => $i,
            ];
        }
        $itemCollection = $this->getConnection()->getCollection('item');
        $items = $itemCollection->batchInsert($items);

        $customerOrders = [];
        foreach ($customers as $i => $customer) {
            $customerOrders[] = [
                'customer_id' => $customer['_id'],
                'number' => $customer['status'],
                'item_ids' => [
                    $items[$i]['_id'],
                    $items[$i+5]['_id'],
                ],
            ];
            $customerOrders[] = [
                'customer_id' => $customer['_id'],
                'number' => $customer['status'] + 100,
                'item_ids' => [
                    $items[$i]['_id'],
                    $items[$i+5]['_id'],
                ],
            ];
        }
        $customerOrderCollection = $this->getConnection()->getCollection('customer_order');
        $customerOrderCollection->batchInsert($customerOrders);
    }

    // Tests :

    public function testFindLazy()
    {
        /* @var $order CustomerOrder */
        $order = CustomerOrder::findOne(['number' => 2]);
        $this->assertFalse($order->isRelationPopulated('customer'));
        $customer = $order->customer;
        $this->assertTrue($order->isRelationPopulated('customer'));
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals((string) $customer->_id, (string) $order->customer_id);
        $this->assertEquals(1, count($order->relatedRecords));

        /* @var $customer Customer */
        $customer = Customer::findOne(['status' => 2]);
        $this->assertFalse($customer->isRelationPopulated('orders'));
        $orders = $customer->orders;
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertTrue($orders[0] instanceof CustomerOrder);
        $this->assertEquals((string) $customer->_id, (string) $orders[0]->customer_id);
    }

    public function testFindEager()
    {
        /* @var $orders CustomerOrder[] */
        $orders = CustomerOrder::find()->with('customer')->all();
        $this->assertCount(10, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->customer instanceof Customer);
        $this->assertEquals((string) $orders[0]->customer->_id, (string) $orders[0]->customer_id);
        $this->assertTrue($orders[1]->customer instanceof Customer);
        $this->assertEquals((string) $orders[1]->customer->_id, (string) $orders[1]->customer_id);

        /* @var $customers Customer[] */
        $customers = Customer::find()->with('orders')->all();
        $this->assertCount(5, $customers);
        $this->assertTrue($customers[0]->isRelationPopulated('orders'));
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
        $this->assertNotEmpty($customers[0]->orders);
        $this->assertTrue($customers[0]->orders[0] instanceof CustomerOrder);
        $this->assertEquals((string) $customers[0]->_id, (string) $customers[0]->orders[0]->customer_id);
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/5411
     *
     * @depends testFindEager
     */
    public function testFindEagerHasManyByArrayKey()
    {
        $order = CustomerOrder::find()->where(['number' => 1])->with('items')->one();
        $this->assertNotEmpty($order->items);
    }

    /**
     * @see https://github.com/yiisoft/yii2-mongodb/issues/173
     */
    public function testApplyLink()
    {
        /* @var $customer Customer */
        $customer = Customer::find()->orderBy(['_id' => SORT_DESC])->limit(1)->one();

        $this->assertCount(2, $customer->getOrders()->all());
        $this->assertEquals(2, $customer->getOrders()->count());
        $this->assertEquals(110, $customer->getOrders()->sum('number'));
        $this->assertEquals(55, $customer->getOrders()->average('number'));
        $this->assertEquals([5, 105], $customer->getOrders()->distinct('number'));
        $this->assertEquals((string)$customer->_id, (string)$customer->getOrders()->modify(['$set' => ['number' => 5]])->customer_id);
    }
}
