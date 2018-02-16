<?php

namespace yiiunit\mongodb\data\ar\file;

/**
 * Test Mongo ActiveRecord
 */
class ActiveRecord extends \yii\mongodb\file\ActiveRecord
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
