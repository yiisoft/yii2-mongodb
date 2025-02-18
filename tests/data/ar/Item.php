<?php

namespace yiiunit\extensions\mongodb\data\ar;

use \yii\mongodb\ActiveRecord;

/**
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $name
 * @property float $price
 */
class Item extends ActiveRecord
{
    public static function collectionName()
    {
        return 'item';
    }

    public function attributes()
    {
        return [
            '_id',
            'name',
            'price',
        ];
    }
}