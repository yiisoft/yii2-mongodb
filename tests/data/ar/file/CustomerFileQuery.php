<?php

namespace yiiunit\extensions\mongodb\data\ar\file;

use yii\mongodb\file\ActiveQuery;

/**
 * CustomerFileQuery
 */
class CustomerFileQuery extends ActiveQuery
{
    public function activeOnly()
    {
        $this->andWhere(['status' => 2]);

        return $this;
    }
}
