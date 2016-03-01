<?php

namespace yiiunit\extensions\mongodb\rbac;

use yii\mongodb\rbac\MongoDbManager;

/**
 * @group mongodb
 */
class MongoDbManagerTest extends ManagerTestCase
{

    private static $collections = [
        'auth_item',
        'auth_item_child',
        'auth_assignment',
        'auth_rule',
    ];

    protected function setUp()
    {
        parent::setUp();
        $this->auth = $this->createManager();
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->auth->removeAll();
    }

    /**
     * @return \yii\rbac\ManagerInterface
     */
    protected function createManager()
    {
        return new MongoDbManager(['db' => $this->getConnection()]);
    }

}
