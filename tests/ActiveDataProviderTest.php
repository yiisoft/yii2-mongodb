<?php

namespace yiiunit\extensions\mongodb;

use MongoDB\BSON\ObjectID;
use yii\data\ActiveDataProvider;
use yii\mongodb\Query;
use yiiunit\extensions\mongodb\data\ar\ActiveRecord;
use yiiunit\extensions\mongodb\data\ar\Customer;
use yiiunit\extensions\mongodb\data\ar\CustomerOrder;

class ActiveDataProviderTest extends TestCase
{

    /**
     * @var array[] list of test rows.
     */
    protected $testRows = [];
    
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
        ActiveRecord::$db = $this->getConnection();
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

    public function testQuery()
    {
        $query = new Query();
        $query->from('customer');

        $provider = new ActiveDataProvider([
            'query' => $query,
            'db' => $this->getConnection(),
        ]);
        $models = $provider->getModels();
        $this->assertEquals(10, count($models));

        $provider = new ActiveDataProvider([
            'query' => $query,
            'db' => $this->getConnection(),
            'pagination' => [
                'pageSize' => 5,
            ]
        ]);
        $models = $provider->getModels();
        $this->assertEquals(5, count($models));
    }

    public function testActiveQuery()
    {
        $provider = new ActiveDataProvider([
            'query' => Customer::find()->orderBy('id ASC'),
        ]);
        $models = $provider->getModels();
        $this->assertEquals(10, count($models));
        $this->assertTrue($models[0] instanceof Customer);
        $keys = $provider->getKeys();
        $this->assertTrue($keys[0] instanceof ObjectID);

        $provider = new ActiveDataProvider([
            'query' => Customer::find(),
            'pagination' => [
                'pageSize' => 5,
            ]
        ]);
        $models = $provider->getModels();
        $this->assertEquals(5, count($models));
    }

    public function testRelationActiveQuery()
    {
        $orderCollection = $this->getConnection()->getCollection('customer_order');
        $rows = [];
        foreach ($this->testRows as $customer) {
            for($i = 1; $i <= 5; $i++) {
                $rows[] = [
                    'customer_id' => $customer['_id'],
                    'item_ids' => [],
                ];
            }   
        }
        
        $orderCollection->batchInsert($rows);
        
        $customer = Customer::findOne(['status' => 1]);
        $provider = new ActiveDataProvider([
            'query' => $customer->getOrders(),
        ]);
        $models = $provider->getModels();
        $this->assertEquals($provider->getTotalCount(), count($models));
    }
}
