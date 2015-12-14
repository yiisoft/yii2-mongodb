<?php

namespace yiiunit\extensions\mongodb;

use yii\base\Exception;
use yiiunit\extensions\mongodb\data\ar\ActiveRecord;
use yii\mongodb\Session;
use Yii;
use yiiunit\extensions\mongodb\data\ar\Animal;

class HardsettingTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        ActiveRecord::$db = $this->getConnection();
    }

    // Tests:

    /**
     * @group hardsetting
     */
    public function testConnection()
    {
        /*$conn = new \MongoDB\Client('mongodb://localhost:27017');
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
        );*/

        /*$result = $collection->find([], ['other']);
        foreach($result as $row) {
            $x  = $row;
        }*/
    }

    /**
     * @group hardsetting
     */
    public function testActiveRecord()
    {
        /*$connection = $this->getConnection();
        $collection = $connection->getCollection('test_animals');*/

        $query = Animal::find();
        $animals = $query->one();

        $x = 2;
    }

    /**
     * @group hardsetting
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