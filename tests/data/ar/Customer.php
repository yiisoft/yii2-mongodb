<?php

namespace yiiunit\mongodb\data\ar;

use yiiunit\mongodb\data\ar\file\CustomerFile;

/**
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property string $status
 * @property string $file_id
 */
class Customer extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function collectionName()
    {
        return 'customer';
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * @return \yii\mongodb\ActiveQuery
     */
    public function getOrders()
    {
        return $this->hasMany(CustomerOrder::class, ['customer_id' => '_id']);
    }

    /**
     * @return \yii\mongodb\ActiveQuery
     */
    public function getFile()
    {
        return $this->hasOne(CustomerFile::class, ['_id' => 'file_id']);
    }

    /**
     * {@inheritdoc}
     * @return CustomerQuery
     */
    public static function find()
    {
        return new CustomerQuery(get_called_class());
    }
}
