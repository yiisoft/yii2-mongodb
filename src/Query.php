<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use yii\base\Component;
use yii\db\QueryInterface;
use yii\db\QueryTrait;
use Yii;

/**
 * Query represents Mongo "find" operation.
 *
 * Query provides a set of methods to facilitate the specification of "find" command.
 * These methods can be chained together.
 *
 * For example,
 *
 * ```php
 * $query = new Query();
 * // compose the query
 * $query->select(['name', 'status'])
 *     ->from('customer')
 *     ->limit(10);
 * // execute the query
 * $rows = $query->all();
 * ```
 *
 * @property Collection $collection Collection instance. This property is read-only.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Query extends Component implements QueryInterface
{
    use QueryTrait;

    /**
     * @var array the fields of the results to return. For example: `['name', 'group_id']`, `['name' => true, '_id' => false]`.
     * Unless directly excluded, the "_id" field is always returned. If not set, it means selecting all columns.
     * @see select()
     */
    public $select = [];
    /**
     * @var string|array the collection to be selected from. If string considered as the name of the collection
     * inside the default database. If array - first element considered as the name of the database,
     * second - as name of collection inside that database
     * @see from()
     */
    public $from;
    /**
     * @var array cursor options in format: optionKey => optionValue
     * @see \MongoDB\Driver\Cursor::addOption()
     * @see options()
     */
    public $options = [];


    /**
     * Returns the Mongo collection for this query.
     * @param Connection $db Mongo connection.
     * @return Collection collection instance.
     */
    public function getCollection($db = null)
    {
        if ($db === null) {
            $db = Yii::$app->get('mongodb');
        }

        return $db->getCollection($this->from);
    }

    /**
     * Sets the list of fields of the results to return.
     * @param array $fields fields of the results to return.
     * @return $this the query object itself.
     */
    public function select(array $fields)
    {
        $this->select = $fields;

        return $this;
    }

    /**
     * Sets the collection to be selected from.
     * @param string|array the collection to be selected from. If string considered as the name of the collection
     * inside the default database. If array - first element considered as the name of the database,
     * second - as name of collection inside that database
     * @return $this the query object itself.
     */
    public function from($collection)
    {
        $this->from = $collection;

        return $this;
    }

    /**
     * Sets the cursor options.
     * @param array $options cursor options in format: optionName => optionValue
     * @return $this the query object itself
     * @see addOptions()
     */
    public function options($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Adds additional cursor options.
     * @param array $options cursor options in format: optionName => optionValue
     * @return $this the query object itself
     * @see options()
     */
    public function addOptions($options)
    {
        if (is_array($this->options)) {
            $this->options = array_merge($this->options, $options);
        } else {
            $this->options = $options;
        }

        return $this;
    }

    /**
     * Helper method for easy querying on values containing some common operators.
     *
     * The comparison operator is intelligently determined based on the first few characters in the given value and
     * internally translated to a MongoDB operator.
     * In particular, it recognizes the following operators if they appear as the leading characters in the given value:
     * <: the column must be less than the given value ($lt).
     * >: the column must be greater than the given value ($gt).
     * <=: the column must be less than or equal to the given value ($lte).
     * >=: the column must be greater than or equal to the given value ($gte).
     * <>: the column must not be the same as the given value ($ne). Note that when $partialMatch is true, this would mean the value must not be a substring of the column.
     * =: the column must be equal to the given value ($eq).
     * none of the above: use the $defaultOperator
     *
     * Note that when the value is empty, no comparison expression will be added to the search condition.
     *
     * @param string $name column name
     * @param string $value column value
     * @param string $defaultOperator Defaults to =, performing an exact match.
     * For example: use 'LIKE' or 'REGEX' for partial cq regex matching
     * @see Collection::buildCondition()
     * @return $this the query object itself.
     * @since 2.0.5
     */
    public function andFilterCompare($name, $value, $defaultOperator = '=')
    {
        $matches = [];
        if (preg_match('/^(<>|>=|>|<=|<|=)/', $value, $matches)) {
            $op = $matches[1];
            $value = substr($value, strlen($op));
        } else {
            $op = $defaultOperator;
        }

        return $this->andFilterWhere([$op, $name, $value]);
    }

    /**
     * Prepares for query building.
     * This method is called before actual query composition, e.g. building cursor, count etc.
     * You may override this method to do some final preparation work before query execution.
     * @return $this a prepared query instance.
     * @since 2.1.3
     */
    public function prepare()
    {
        return $this;
    }

    /**
     * Builds the MongoDB cursor for this query.
     * @param Connection $db the MongoDB connection used to execute the query.
     * @return \MongoDB\Driver\Cursor mongo cursor instance.
     */
    public function buildCursor($db = null)
    {
        $this->prepare();

        $options = $this->options;
        if (!empty($this->orderBy)) {
            $options['sort'] = $this->orderBy;
        }
        $options['limit'] = $this->limit;
        $options['skip'] = $this->offset;

        $cursor = $this->getCollection($db)->find($this->composeCondition(), $this->select, $options);

        return $cursor;
    }

    /**
     * Fetches rows from the given Mongo cursor.
     * @param \MongoDB\Driver\Cursor $cursor Mongo cursor instance to fetch data from.
     * @param bool $all whether to fetch all rows or only first one.
     * @param string|callable $indexBy the column name or PHP callback,
     * by which the query results should be indexed by.
     * @throws Exception on failure.
     * @return array|bool result.
     */
    protected function fetchRows($cursor, $all = true, $indexBy = null)
    {
        $token = 'fetch cursor id = ' . $cursor->getId();
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $result = $this->fetchRowsInternal($cursor, $all);
            Yii::endProfile($token, __METHOD__);

            return $result;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @param \MongoDB\Driver\Cursor $cursor Mongo cursor instance to fetch data from.
     * @param bool $all whether to fetch all rows or only first one.
     * @return array|bool result.
     * @see Query::fetchRows()
     */
    protected function fetchRowsInternal($cursor, $all)
    {
        $result = [];
        if ($all) {
            foreach ($cursor as $row) {
                $result[] = $row;
            }
        } else {
            if ($row = current($cursor->toArray())) {
                $result = $row;
            } else {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Starts a batch query.
     *
     * A batch query supports fetching data in batches, which can keep the memory usage under a limit.
     * This method will return a [[BatchQueryResult]] object which implements the `Iterator` interface
     * and can be traversed to retrieve the data in batches.
     *
     * For example,
     *
     * ```php
     * $query = (new Query)->from('user');
     * foreach ($query->batch() as $rows) {
     *     // $rows is an array of 10 or fewer rows from user collection
     * }
     * ```
     *
     * @param int $batchSize the number of records to be fetched in each batch.
     * @param Connection $db the MongoDB connection. If not set, the "mongodb" application component will be used.
     * @return BatchQueryResult the batch query result. It implements the `Iterator` interface
     * and can be traversed to retrieve the data in batches.
     * @since 2.1
     */
    public function batch($batchSize = 100, $db = null)
    {
        return Yii::createObject([
            'class' => BatchQueryResult::className(),
            'query' => $this,
            'batchSize' => $batchSize,
            'db' => $db,
            'each' => false,
        ]);
    }

    /**
     * Starts a batch query and retrieves data row by row.
     * This method is similar to [[batch()]] except that in each iteration of the result,
     * only one row of data is returned. For example,
     *
     * ```php
     * $query = (new Query)->from('user');
     * foreach ($query->each() as $row) {
     * }
     * ```
     *
     * @param int $batchSize the number of records to be fetched in each batch.
     * @param Connection $db the MongoDB connection. If not set, the "mongodb" application component will be used.
     * @return BatchQueryResult the batch query result. It implements the `Iterator` interface
     * and can be traversed to retrieve the data in batches.
     * @since 2.1
     */
    public function each($batchSize = 100, $db = null)
    {
        return Yii::createObject([
            'class' => BatchQueryResult::className(),
            'query' => $this,
            'batchSize' => $batchSize,
            'db' => $db,
            'each' => true,
        ]);
    }

    /**
     * Executes the query and returns all results as an array.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all($db = null)
    {
        if (!empty($this->emulateExecution)) {
            return [];
        }
        $cursor = $this->buildCursor($db);
        $rows = $this->fetchRows($cursor, true, $this->indexBy);
        return $this->populate($rows);
    }

    /**
     * Converts the raw query results into the format as specified by this query.
     * This method is internally used to convert the data fetched from database
     * into the format as required by this query.
     * @param array $rows the raw query result from database
     * @return array the converted query result
     */
    public function populate($rows)
    {
        if ($this->indexBy === null) {
            return $rows;
        }
        $result = [];
        foreach ($rows as $row) {
            if (is_string($this->indexBy)) {
                $key = $row[$this->indexBy];
            } else {
                $key = call_user_func($this->indexBy, $row);
            }
            $result[$key] = $row;
        }
        return $result;
    }

    /**
     * Executes the query and returns a single row of result.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return array|false the first row (in terms of an array) of the query result. `false` is returned if the query
     * results in nothing.
     */
    public function one($db = null)
    {
        if (!empty($this->emulateExecution)) {
            return false;
        }
        $cursor = $this->buildCursor($db);
        return $this->fetchRows($cursor, false);
    }

    /**
     * Returns the query result as a scalar value.
     * The value returned will be the first column in the first row of the query results.
     * Column `_id` will be automatically excluded from select fields, if [[select]] is not empty and
     * `_id` is not selected explicitly.
     * @param Connection $db the MongoDB connection used to generate the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return string|null|false the value of the first column in the first row of the query result.
     * `false` is returned if the query result is empty.
     * @since 2.1.2
     */
    public function scalar($db = null)
    {
        if (!empty($this->emulateExecution)) {
            return null;
        }

        $originSelect = (array)$this->select;
        if (!isset($originSelect['_id']) && array_search('_id', $originSelect, true) === false) {
            $this->select['_id'] = false;
        }

        $cursor = $this->buildCursor($db);
        $row = $this->fetchRows($cursor, false);

        if (empty($row)) {
            return false;
        }

        return reset($row);
    }

    /**
     * Executes the query and returns the first column of the result.
     * Column `_id` will be automatically excluded from select fields, if [[select]] is not empty and
     * `_id` is not selected explicitly.
     * @param Connection $db the MongoDB connection used to generate the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return array the first column of the query result. An empty array is returned if the query results in nothing.
     * @since 2.1.2
     */
    public function column($db = null)
    {
        if (!empty($this->emulateExecution)) {
            return [];
        }

        $originSelect = (array)$this->select;
        if (!isset($originSelect['_id']) && array_search('_id', $originSelect, true) === false) {
            $this->select['_id'] = false;
        }
        if (is_string($this->indexBy) && $originSelect && count($originSelect) === 1) {
            $this->select[] = $this->indexBy;
        }

        $cursor = $this->buildCursor($db);
        $rows = $this->fetchRows($cursor, true);

        if (empty($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            $value = reset($row);

            if ($this->indexBy === null) {
                $results[] = $value;
            } else {
                if ($this->indexBy instanceof \Closure) {
                    $results[call_user_func($this->indexBy, $row)] = $value;
                } else {
                    $results[$row[$this->indexBy]] = $value;
                }
            }
        }

        return $results;
    }

    /**
     * Performs 'findAndModify' query and returns a single row of result.
     * @param array $update update criteria
     * @param array $options list of options in format: optionName => optionValue.
     * @param Connection $db the Mongo connection used to execute the query.
     * @return array|null the original document, or the modified document when $options['new'] is set.
     */
    public function modify($update, $options = [], $db = null)
    {
        if (!empty($this->emulateExecution)) {
            return null;
        }

        $this->prepare();

        $collection = $this->getCollection($db);
        if (!empty($this->orderBy)) {
            $options['sort'] = $this->orderBy;
        }

        $options['fields'] = $this->select;
        return $collection->findAndModify($this->composeCondition(), $update, $options);
    }

    /**
     * Returns the number of records.
     * @param string $q kept to match [[QueryInterface]], its value is ignored.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return int number of records
     * @throws Exception on failure.
     */
    public function count($q = '*', $db = null)
    {
        if (!empty($this->emulateExecution)) {
            return 0;
        }
        $this->prepare();
        $collection = $this->getCollection($db);
        return $collection->count($this->where, $this->options);
    }

    /**
     * Returns a value indicating whether the query result contains any row of data.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return bool whether the query result contains any row of data.
     */
    public function exists($db = null)
    {
        if (!empty($this->emulateExecution)) {
            return false;
        }
        $cursor = $this->buildCursor($db);
        foreach ($cursor as $row) {
            return true;
        }
        return false;
    }

    /**
     * Returns the sum of the specified column values.
     * @param string $q the column name.
     * Make sure you properly quote column names in the expression.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return int the sum of the specified column values
     */
    public function sum($q, $db = null)
    {
        if (!empty($this->emulateExecution)) {
            return 0;
        }
        return $this->aggregate($q, 'sum', $db);
    }

    /**
     * Returns the average of the specified column values.
     * @param string $q the column name.
     * Make sure you properly quote column names in the expression.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return int the average of the specified column values.
     */
    public function average($q, $db = null)
    {
        if (!empty($this->emulateExecution)) {
            return 0;
        }
        return $this->aggregate($q, 'avg', $db);
    }

    /**
     * Returns the minimum of the specified column values.
     * @param string $q the column name.
     * Make sure you properly quote column names in the expression.
     * @param Connection $db the MongoDB connection used to execute the query.
     * If this parameter is not given, the `db` application component will be used.
     * @return int the minimum of the specified column values.
     */
    public function min($q, $db = null)
    {
        return $this->aggregate($q, 'min', $db);
    }

    /**
     * Returns the maximum of the specified column values.
     * @param string $q the column name.
     * Make sure you properly quote column names in the expression.
     * @param Connection $db the MongoDB connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return int the maximum of the specified column values.
     */
    public function max($q, $db = null)
    {
        return $this->aggregate($q, 'max', $db);
    }

    /**
     * Performs the aggregation for the given column.
     * @param string $column column name.
     * @param string $operator aggregation operator.
     * @param Connection $db the database connection used to execute the query.
     * @return int aggregation result.
     */
    protected function aggregate($column, $operator, $db)
    {
        if (!empty($this->emulateExecution)) {
            return null;
        }

        $this->prepare();
        $collection = $this->getCollection($db);
        $pipelines = [];
        if ($this->where !== null) {
            $pipelines[] = ['$match' => $this->where];
        }
        $pipelines[] = [
            '$group' => [
                '_id' => '1',
                'total' => [
                    '$' . $operator => '$' . $column
                ],
            ]
        ];
        $result = $collection->aggregate($pipelines);
        if (array_key_exists(0, $result)) {
            return $result[0]['total'];
        }
        return null;
    }

    /**
     * Returns a list of distinct values for the given column across a collection.
     * @param string $q column to use.
     * @param Connection $db the MongoDB connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return array array of distinct values
     */
    public function distinct($q, $db = null)
    {
        if (!empty($this->emulateExecution)) {
            return [];
        }

        $this->prepare();
        $collection = $this->getCollection($db);
        if ($this->where !== null) {
            $condition = $this->where;
        } else {
            $condition = [];
        }
        $result = $collection->distinct($q, $condition);
        if ($result === false) {
            return [];
        }
        return $result;
    }

    /**
     * Composes condition from raw [[where]] value.
     * @return array conditions.
     */
    private function composeCondition()
    {
        if ($this->where === null) {
            return [];
        }
        return $this->where;
    }
}
