<?php

namespace yiiunit\extensions\mongodb;

use yiiunit\extensions\mongodb\data\ar\ActiveRecord;

use Yii;
use yiiunit\extensions\mongodb\data\ar\Animal;
use yiiunit\extensions\mongodb\data\ar\Cat;

class UpgradeTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        ActiveRecord::$db = $this->getConnection();
    }

    // Tests:

    /**
     * @group upgrade
     */
    public function testConnection()
    {
        $conn = new \MongoDB\Client('mongodb://localhost:27017');
        $db = $conn->selectDatabase('yii2test');
        $collection = $db->selectCollection('test_animals');

        $result = $collection->findOneAndUpdate([
            'type' => 'yiiunit\extensions\mongodb\data\ar\Dog'],
            ['$set' => ['type' => 'yiiunit\extensions\mongodb\data\ar\NotDog']]
        );

        $connection = $this->getConnection();
        $collection = $connection->getCollection('test_animals');

        $result = $collection->findAndModify([
            'type' => 'yiiunit\extensions\mongodb\data\ar\NotDog'],
            ['$set' => ['type' => 'yiiunit\extensions\mongodb\data\ar\Dog']]
        );

        /*$result = $collection->find([], ['other']);
        foreach($result as $row) {
            $x  = $row;
        }*/
    }

    /**
     * @group upgrade
     */
    public function testActiveRecord()
    {
        /*$connection = $this->getConnection();
        $collection = $connection->getCollection('test_animals');*/

        $query = Animal::find();
        $animal = $query->one();
        $animals = $query->all();

        $cats = $query->where(['type' => "yiiunit\\extensions\\mongodb\\data\\ar\\Cat"])->all();
        $first = $query->where(['_id' => '566e9d70ca4ab6980a0000b2'])->one();

        $newAnimal = new Animal([
            'type' => Cat::className()
        ]);
        $newAnimal->save();
        $newAnimal->delete();
    }

    /**
     * @group upgrade
     */
    public function testCompatibility()
    {
        $conn = new \MongoDB\Client('mongodb://localhost:27017');
        $db = $conn->selectDatabase('yii2test');
        $collection = $db->selectCollection('test_animals');
        $result = $collection->find()->toArray();

        $conn = new \MongoClient();
        $db = $conn->selectDB('yii2test');
        $collection = $db->selectCollection('test_animals');

        $cursor = $collection->find();
        $result = [];
        while($cursor->hasNext()) $result[] = $cursor->getNext();
    }
}