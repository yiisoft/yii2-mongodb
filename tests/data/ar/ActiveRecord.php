<?php

namespace yiiunit\extensions\mongodb\data\ar;

/**
 * Test Mongo ActiveRecord
 */
class ActiveRecord extends \yii\mongodb\ActiveRecord
{
    public static $db;


    /**
     * {@inheritdoc}
     */
    public static function getDb()
    {
        return self::$db;
    }
}
