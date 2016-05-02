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
     * @param integer $seq
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
        preg_match('/^.+\((.*)\)$/', $query, $matches);
        if (!isset($matches[1])) {
            return '';
        }

        $cursor = $this->getCursorFromQueryLog($matches[1]);
        if (!$cursor) {
            return '';
        }
        $result = $cursor->explain();

        return Json::encode($result, JSON_PRETTY_PRINT);
    }

    /**
     * Create MongoCursor from string query log
     *
     * @param string $queryString
     * @return \MongoCursor|null
     */
    protected function getCursorFromQueryLog($queryString)
    {
        $cursor = null;

        $connection = $this->panel->getDb();
        $connection->open();

        if ($connection->isActive) {
            $queryInfo = Json::decode($queryString);
            $query = $this->prepareQuery(isset($queryInfo['query']['$query']) ? $queryInfo['query']['$query'] : $queryInfo['query']);

            $cursor = new \MongoCursor($connection->mongoClient, $queryInfo['ns'], $query, $queryInfo['fields']);
            $cursor->limit($queryInfo['limit']);
            $cursor->skip($queryInfo['skip']);
            if (isset($queryInfo['query']['$orderby'])) {
                $cursor->sort($queryInfo['query']['$orderby']);
            }
        }

        return $cursor;
    }

    /**
     * Prepare query for using in MongoCursor.
     * Converts array contains `$id` key into [[MongoId]] instance.
     * If array given, each element of it will be processed.
     *
     * @param mixed $query raw query
     * @return array|string prepared query
     */
    protected function prepareQuery($query)
    {
        if (is_array($query)) {
            if (count($query) === 1 && isset($query['$id'])) {
                return new \MongoId($query['$id']);
            } else {
                $result = [];
                foreach ($query as $key => $value) {
                    $result[$key] = $this->prepareQuery($value);
                }
                return $result;
            }
        } else {
            return $query;
        }
    }
}
