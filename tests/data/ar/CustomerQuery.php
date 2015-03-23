<?php

namespace yiiunit\extensions\mongodb\data\ar;

use yii\mongodb\ActiveQuery;

/**
 * CustomerQuery
 */
class CustomerQuery extends ActiveQuery
{
    public function activeOnly()
    {
        $this->andWhere(['status' => 2]);

        return $this;
    }
}
