<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use yii\base\BaseObject;
use Yii;

/**
 * BatchQueryResult represents a batch query from which you can retrieve data in batches.
 *
 * You usually do not instantiate BatchQueryResult directly. Instead, you obtain it by
 * calling [[Query::batch()]] or [[Query::each()]]. Because BatchQueryResult implements the `Iterator` interface,
 * you can iterate it to obtain a batch of data in each iteration. For example,
 *
 * ```php
 * $query = (new Query())->from('user');
 * foreach ($query->batch() as $i => $users) {
 *     // $users represents the rows in the $i-th batch
 * }
 * foreach ($query->each() as $user) {
 * }
 * ```
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class BatchQueryResult extends BaseObject implements \Iterator
{
    /**
     * @var Connection the MongoDB connection to be used when performing batch query.
     * If null, the "mongodb" application component will be used.
     */
    public $db;
    /**
     * @var Query the query object associated with this batch query.
     * Do not modify this property directly unless after [[reset()]] is called explicitly.
     */
    public $query;
    /**
     * @var int the number of rows to be returned in each batch.
     */
    public $batchSize = 100;
    /**
     * @var bool whether to return a single row during each iteration.
     * If false, a whole batch of rows will be returned in each iteration.
     */
    public $each = false;

    /**
     * @var array the data retrieved in the current batch
     */
    private $_batch;
    /**
     * @var mixed the value for the current iteration
     */
    private $_value;
    /**
     * @var string|int the key for the current iteration
     */
    private $_key;
    /**
     * @var \Iterator
     */
    private $_iterator;


    /**
     * Resets the batch query.
     * This method will clean up the existing batch query so that a new batch query can be performed.
     */
    public function reset()
    {
        $this->_iterator = null;
        $this->_batch = null;
        $this->_value = null;
        $this->_key = null;
    }

    /**
     * Resets the iterator to the initial state.
     * This method is required by the interface Iterator.
     */
    public function rewind()
    {
        $this->reset();
        $this->next();
    }

    /**
     * Moves the internal pointer to the next dataset.
     * This method is required by the interface Iterator.
     */
    public function next()
    {
        if ($this->_batch === null || !$this->each || $this->each && next($this->_batch) === false) {
            $this->_batch = $this->fetchData();
            reset($this->_batch);
        }

        if ($this->each) {
            $this->_value = current($this->_batch);
            if ($this->query->indexBy !== null) {
                $this->_key = key($this->_batch);
            } elseif (key($this->_batch) !== null) {
                $this->_key++;
            } else {
                $this->_key = null;
            }
        } else {
            $this->_value = $this->_batch;
            $this->_key = $this->_key === null ? 0 : $this->_key + 1;
        }
    }

    /**
     * Fetches the next batch of data.
     * @return array the data fetched
     */
    protected function fetchData()
    {
        if ($this->_iterator === null) {
            if (empty($this->query->orderBy)) {
                // setting cursor batch size may setup implicit limit on the query with 'sort'
                // @see https://jira.mongodb.org/browse/PHP-457
                $this->query->addOptions(['batchSize' => $this->batchSize]);
            }
            $cursor = $this->query->buildCursor($this->db);
            $token = 'fetch cursor id = ' . $cursor->getId();
            Yii::info($token, __METHOD__);

            if ($cursor instanceof \Iterator) {
                $this->_iterator = $cursor;
            } else {
                $this->_iterator = new \IteratorIterator($cursor);
            }

            $this->_iterator->rewind();
        }

        $rows = [];
        $count = 0;

        while ($count++ < $this->batchSize) {
            $row = $this->_iterator->current();
            if ($row === null) {
                break;
            }
            $this->_iterator->next();
            //var_dump($row);
            $rows[] = $row;
        }
        return $this->query->populate($rows);
    }

    /**
     * Returns the index of the current dataset.
     * This method is required by the interface Iterator.
     * @return int the index of the current row.
     */
    public function key()
    {
        return $this->_key;
    }

    /**
     * Returns the current dataset.
     * This method is required by the interface Iterator.
     * @return mixed the current dataset.
     */
    public function current()
    {
        return $this->_value;
    }

    /**
     * Returns whether there is a valid dataset at the current position.
     * This method is required by the interface Iterator.
     * @return bool whether there is a valid dataset at the current position.
     */
    public function valid()
    {
        return !empty($this->_batch);
    }
}