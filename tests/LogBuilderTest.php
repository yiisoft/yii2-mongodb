<?php

namespace yiiunit\extensions\mongodb;

use MongoDB\BSON\Javascript;
use MongoDB\BSON\ObjectID;
use yii;

class LogBuilderTest extends TestCase
{
    /**
     * Data provider for [[testEncodeData]].
     * @return array test data
     */
    public function dataProviderEncodeData()
    {
        return [
            [
                'foo',
                '"foo"',
            ],
            [
                new ObjectID('57684eed962078354a21ec11'),
                '"MongoDB\\\\BSON\\\\ObjectID(57684eed962078354a21ec11)"',
            ],
            [
                new Javascript('function () {return 0;}'),
                '"MongoDB\\\\BSON\\\\Javascript(function () {return 0;})"'
            ],
        ];
    }

    /**
     * @dataProvider dataProviderEncodeData
     *
     * @param mixed $data
     * @param string $expectedResult
     */
    public function testEncodeData($data, $expectedResult)
    {
        $logBuilder = yii::$app->mongodb->getLogBuilder();
        $this->assertTrue(strcasecmp($expectedResult, $logBuilder->encodeData($data)) === 0);
    }
}