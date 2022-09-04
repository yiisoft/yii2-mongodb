<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb\file;

use Yii;

/**
 * Query represents Mongo "find" operation for GridFS collection.
 *
 * Query behaves exactly as regular [[\yii\mongodb\Query]].
 * Found files will be represented as arrays of file document attributes with
 * additional 'file' key, which stores [[\MongoGridFSFile]] instance.
 *
 * @property-read Collection $collection Collection instance.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Query extends \yii\mongodb\Query
{
    /**
     * Returns the Mongo collection for this query.
     * @param \yii\mongodb\Connection $db Mongo connection.
     * @return Collection collection instance.
     */
    public function getCollection($db = null)
    {
        if ($db === null) {
            $db = Yii::$app->get('mongodb');
        }

        return $db->getFileCollection($this->from);
    }
}
