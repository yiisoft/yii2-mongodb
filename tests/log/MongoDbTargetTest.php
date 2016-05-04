<?php

namespace yiiunit\extensions\mongodb\log;

use yii\log\Logger;
use yii\mongodb\log\MongoDbTarget;
use yiiunit\extensions\mongodb\TestCase;

class MongoDbTargetTest extends TestCase
{
    protected function tearDown()
    {
        $this->dropCollection('log');
        parent::tearDown();
    }

    /**
     * @return MongoDbTarget test log target
     */
    protected function createLogTarget()
    {
        return new MongoDbTarget([
            'db' => $this->getConnection(),
        ]);
    }

    // Tests :

    public function testExport()
    {
        $target = $this->createLogTarget();

        $target->messages = [
            [
                'test',
                Logger::LEVEL_WARNING,
                'test',
                time() - 1,
            ],
            [
                'info',
                Logger::LEVEL_INFO,
                'test',
                time(),
            ]
        ];

        $target->export();

        $rows = $this->findAll($this->mongodb->getCollection($target->logCollection));
        $this->assertCount(2, $rows);

        $this->assertEquals($target->messages[0][0], $rows[0]['message']);
        $this->assertEquals($target->messages[0][1], $rows[0]['level']);
        $this->assertEquals($target->messages[0][2], $rows[0]['category']);
        $this->assertEquals($target->messages[0][3], $rows[0]['log_time']);

        $this->assertEquals($target->messages[1][0], $rows[1]['message']);
        $this->assertEquals($target->messages[1][1], $rows[1]['level']);
        $this->assertEquals($target->messages[1][2], $rows[1]['category']);
        $this->assertEquals($target->messages[1][3], $rows[1]['log_time']);
    }
}