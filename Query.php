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
use yii\helpers\Json;
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
 * $query = new Query;
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
     * @see \MongoCursor::addOption()
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
     * @see yii\mongodb\Collection::buildCondition()
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
     * Builds the Mongo cursor for this query.
     * @param Connection $db the database connection used to execute the query.
     * @return \MongoCursor mongo cursor instance.
     */
    protected function buildCursor($db = null)
    {
        $cursor = $this->getCollection($db)->find($this->composeCondition(), $this->composeSelectFields());
        if (!empty($this->orderBy)) {
            $cursor->sort($this->composeSort());
        }
        $cursor->limit($this->limit);
        $cursor->skip($this->offset);

        foreach ($this->options as $key => $value) {
            $cursor->addOption($key, $value);
        }

        return $cursor;
    }

    /**
     * Fetches rows from the given Mongo cursor.
     * @param \MongoCursor $cursor Mongo cursor instance to fetch data from.
     * @param boolean $all whether to fetch all rows or only first one.
     * @param string|callable $indexBy the column name or PHP callback,
     * by which the query results should be indexed by.
     * @throws Exception on failure.
     * @return array|boolean result.
     */
    protected function fetchRows($cursor, $all = true, $indexBy = null)
    {
        $token = 'find(' . Json::encode($cursor->info()) . ')';
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $result = $this->fetchRowsInternal($cursor, $all, $indexBy);
            Yii::endProfile($token, __METHOD__);

            return $result;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @param \MongoCursor $cursor Mongo cursor instance to fetch data from.
     * @param boolean $all whether to fetch all rows or only first one.
     * @param string|callable $indexBy value to index by.
     * @return array|boolean result.
     * @see Query::fetchRows()
     */
    protected function fetchRowsInternal($cursor, $all, $indexBy)
    {
        $result = [];
        if ($all) {
            foreach ($cursor as $row) {
                $result[] = $row;
            }
        } else {
            if ($row = $cursor->getNext()) {
                $result = $row;
            } else {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Executes the query and returns all results as an array.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all($db = null)
    {
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
     * @return array|boolean the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     */
    public function one($db = null)
    {
        $cursor = $this->buildCursor($db);
        return $this->fetchRows($cursor, false);
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
        $collection = $this->getCollection($db);
        if (!empty($this->orderBy)) {
            $options['sort'] = $this->composeSort();
        }

        return $collection->findAndModify($this->composeCondition(), $update, $this->composeSelectFields(), $options);
    }

    /**
     * Returns the number of records.
     * @param string $q kept to match [[QueryInterface]], its value is ignored.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return integer number of records
     * @throws Exception on failure.
     */
    public function count($q = '*', $db = null)
    {
        $cursor = $this->buildCursor($db);
        $token = 'find.count(' . Json::encode($cursor->info()) . ')';
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $result = $cursor->count();
            Yii::endProfile($token, __METHOD__);

            return $result;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Returns a value indicating whether the query result contains any row of data.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return boolean whether the query result contains any row of data.
     */
    public function exists($db = null)
    {
        return $this->one($db) !== null;
    }

    /**
     * Returns the sum of the specified column values.
     * @param string $q the column name.
     * Make sure you properly quote column names in the expression.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return integer the sum of the specified column values
     */
    public function sum($q, $db = null)
    {
        return $this->aggregate($q, 'sum', $db);
    }

    /**
     * Returns the average of the specified column values.
     * @param string $q the column name.
     * Make sure you properly quote column names in the expression.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return integer the average of the specified column values.
     */
    public function average($q, $db = null)
    {
        return $this->aggregate($q, 'avg', $db);
    }

    /**
     * Returns the minimum of the specified column values.
     * @param string $q the column name.
     * Make sure you properly quote column names in the expression.
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return integer the minimum of the specified column values.
     */
    public function min($q, $db = null)
    {
        return $this->aggregate($q, 'min', $db);
    }

    /**
     * Returns the maximum of the specified column values.
     * @param string $q the column name.
     * Make sure you properly quote column names in the expression.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return integer the maximum of the specified column values.
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
     * @return integer aggregation result.
     */
    protected function aggregate($column, $operator, $db)
    {
        $collection = $this->getCollection($db);
        $pipelines = [];
        if ($this->where !== null) {
            $pipelines[] = ['$match' => $collection->buildCondition($this->where)];
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
        } else {
            return 0;
        }
    }

    /**
     * Returns a list of distinct values for the given column across a collection.
     * @param string $q column to use.
     * @param Connection $db the Mongo connection used to execute the query.
     * If this parameter is not given, the `mongodb` application component will be used.
     * @return array array of distinct values
     */
    public function distinct($q, $db = null)
    {
        $collection = $this->getCollection($db);
        if ($this->where !== null) {
            $condition = $this->where;
        } else {
            $condition = [];
        }
        $result = $collection->distinct($q, $condition);
        if ($result === false) {
            return [];
        } else {
            return $result;
        }
    }

    /**
     * Composes condition from raw [[where]] value.
     * @return array conditions.
     */
    private function composeCondition()
    {
        if ($this->where === null) {
            return [];
        } else {
            return $this->where;
        }
    }

    /**
     * Composes select fields from raw [[select]] value.
     * @return array select fields.
     */
    private function composeSelectFields()
    {
        $selectFields = [];
        if (!empty($this->select)) {
            foreach ($this->select as $key => $value) {
                if (is_numeric($key)) {
                    $selectFields[$value] = true;
                } else {
                    $selectFields[$key] = $value;
                }
            }
        }
        return $selectFields;
    }

    /**
     * Composes sort specification from raw [[orderBy]] value.
     * @return array sort specification.
     */
    private function composeSort()
    {
        $sort = [];
        foreach ($this->orderBy as $fieldName => $sortOrder) {
            switch ($sortOrder) {
                case SORT_ASC:
                    $sort[$fieldName] = \MongoCollection::ASCENDING;
                    break;
                case SORT_DESC:
                    $sort[$fieldName] = \MongoCollection::DESCENDING;
                    break;
                default:
                    $sort[$fieldName] = $sortOrder;
            }
        }
        return $sort;
    }
}
