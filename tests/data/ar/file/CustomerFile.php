<?php

namespace yiiunit\extensions\mongodb\data\ar\file;

class CustomerFile extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function collectionName()
    {
        return 'customer_fs';
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return array_merge(
            parent::attributes(),
            [
                'tag',
                'status',
            ]
        );
    }

    /**
     * @inheritdoc
     * @return CustomerFileQuery
     */
    public static function find()
    {
        return new CustomerFileQuery(get_called_class());
    }
}
