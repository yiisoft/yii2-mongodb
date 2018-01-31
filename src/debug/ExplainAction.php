<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\debug;

use yii\base\Action;
use yii\helpers\Json;
use yii\web\HttpException;

/**
 * ExplainAction provides EXPLAIN information for MongoDB queries
 *
 * @author Sergey Smirnov <webdevsega@yandex.ru>
 * @author Klimov Paul <klimov@zfort.com>
 * @since 2.0.5
 */
class ExplainAction extends Action
{
    /**
     * @var MongoDbPanel related debug toolbar panel
     */
    public $panel;


    /**
     * Runs the explain action
     * @param int $seq
     * @param string $tag
     * @return string explain result content
     * @throws HttpException if requested log not found
     */
    public function run($seq, $tag)
    {
        $this->controller->loadData($tag);

        $timings = $this->panel->calculateTimings();

        if (!isset($timings[$seq])) {
            throw new HttpException(404, 'Log message not found.');
        }

        $query = $timings[$seq]['info'];

        if (strpos($query, 'find({') !== 0) {
            return '';
        }

        $query = substr($query, strlen('find('), -1);
        $result = $this->explainQuery($query);
        if (!$result) {
            return '';
        }

        return Json::encode($result, JSON_PRETTY_PRINT);
    }

    /**
     * Runs explain command over the query
     *
     * @param string $queryString query log string.
     * @return array|false explain results, `false` on failure.
     */
    protected function explainQuery($queryString)
    {
        /* @var $connection \yii\mongodb\Connection */
        $connection = $this->panel->getDb();

        $queryInfo = Json::decode($queryString);
        if (!isset($queryInfo['ns'])) {
            return false;
        }

        list($databaseName, $collectionName) = explode('.', $queryInfo['ns'], 2);
        unset($queryInfo['ns']);

        if (!empty($queryInfo['filer'])) {
            $queryInfo['filer'] = $this->prepareQueryFiler($queryInfo['filer']);
        }

        return $connection->createCommand($databaseName)->explain($collectionName, $queryInfo);
    }

    /**
     * Prepare query filer for explain.
     * Converts BSON object log entries into actual objects.
     *
     * @param array $query raw query filter.
     * @return array|string prepared query
     */
    private function prepareQueryFiler($query)
    {
        $result = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->prepareQueryFiler($value);
            } elseif (is_string($value) && preg_match('#^(MongoDB\\\\BSON\\\\[A-Za-z]+)\\((.*)\\)$#s', $value, $matches)) {
                $class = $matches[1];
                $objectValue = $matches[1];

                try {
                    $result[$key] = new $class($objectValue);
                } catch (\Exception $e) {
                    $result[$key] = $value;
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
