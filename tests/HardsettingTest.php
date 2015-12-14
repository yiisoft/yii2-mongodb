<?php

namespace yiiunit\extensions\mongodb;

use yii\mongodb\Session;
use Yii;

class HardsettingTest extends TestCase
{
    /** {@inheritdoc} */
    protected $mongoDbConfig = [
        'dsn' => 'mongodb://localhost:27017',
        'defaultDatabaseName' => 'yii2test',
        'options' => [],
    ];

    protected function tearDown()
    {
        parent::tearDown();
    }

    // Tests:

    /**
     * @group hardsetting
     */
    public function testGne()
    {
        $conn = new \MongoDB\Client();
        $db = $conn->selectDatabase('yii2test');
        $collection = $db->selectCollection('test_animals');

        $result = $collection->findOneAndUpdate([
            'type' => 'yiiunit\extensions\mongodb\data\ar\Dog'],
            ['$set' => ['type' => 'yiiunit\extensions\mongodb\data\ar\NotDog']]
        );

        $connection = $this->getConnection();
        $collection = $connection->getCollection('test_animals');

        /*$result = $collection->findAndModify([
            'type' => 'yiiunit\extensions\mongodb\data\ar\NotDog'],
            ['$set' => ['type' => 'yiiunit\extensions\mongodb\data\ar\Dog']]
        );*/

        $result = $collection->find([], ['other']);
        foreach($result as $row) {
            $x  = $row;
        }


        $x = 2;
    }
}