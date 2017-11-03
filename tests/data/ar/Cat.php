<?php

namespace yiiunit\extensions\mongodb\data\ar;

/**
 * Cat
 *
 * @author Jose Lorente <jose.lorente.martin@gmail.com>
 */
class Cat extends Animal
{
    /**
     * {@inheritdoc}
     */
    public static function populateRecord($record, $row)
    {
        parent::populateRecord($record, $row);

        $record->does = 'meow';
    }
}
