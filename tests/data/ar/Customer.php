<?php

namespace yiiunit\extensions\mongodb\data\ar;

use yiiunit\extensions\mongodb\data\ar\file\CustomerFile;

/**
 * @property string $name
 * @property string $email
 * @property string $address
 * @property string $status
 * @property string $file_id
 */
class Customer extends ActiveRecord
{
    public static function collectionName()
    {
        return 'customer';
    }

    public function attributes()
    {
        return [
            '_id',
            'name',
            'email',
            'address',
            'status',
            'file_id',
        ];
    }

    public function getOrders()
    {
        return $this->hasMany(CustomerOrder::className(), ['customer_id' => '_id']);
    }

    public function getFile()
    {
        return $this->hasOne(CustomerFile::className(), ['_id' => 'file_id']);
    }

    /**
     * @inheritdoc
     * @return CustomerQuery
     */
    public static function find()
    {
        return new CustomerQuery(get_called_class());
    }
}
