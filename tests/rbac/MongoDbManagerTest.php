<?php

namespace yiiunit\extensions\mongodb\rbac;

use yii\mongodb\rbac\MongoDbManager;

/**
 * @group mongodb
 */
class MongoDbManagerTest extends ManagerTestCase
{

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
