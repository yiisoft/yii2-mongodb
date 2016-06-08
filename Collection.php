<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use MongoDB\BSON\ObjectID;
use yii\base\InvalidParamException;
use yii\base\Object;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Collection represents the Mongo collection information.
 *
 * A collection object is usually created by calling [[Database::getCollection()]] or [[Connection::getCollection()]].
 *
 * Collection provides the basic interface for the Mongo queries, mostly: insert, update, delete operations.
 * For example:
 *
 * ```php
 * $collection = Yii::$app->mongodb->getCollection('customer');
 * $collection->insert(['name' => 'John Smith', 'status' => 1]);
 * ```
 *
 * To perform "find" queries, please use [[Query]] instead.
 *
 * Mongo uses JSON format to specify query conditions with quite specific syntax.
 * However Collection class provides the ability of "translating" common condition format used "yii\db\*"
 * into Mongo condition.
 * For example:
 *
 * ```php
 * $condition = [
 *     [
 *         'OR',
 *         ['AND', ['first_name' => 'John'], ['last_name' => 'Smith']],
 *         ['status' => [1, 2, 3]]
 *     ],
 * ];
 * print_r($collection->buildCondition($condition));
 * // outputs :
 * [
 *     '$or' => [
 *         [
 *             'first_name' => 'John',
 *             'last_name' => 'John',
 *         ],
 *         [
 *             'status' => ['$in' => [1, 2, 3]],
 *         ]
 *     ]
 * ]
 * ```
 *
 * Note: condition values for the key '_id' will be automatically cast to [[\MongoId]] instance,
 * even if they are plain strings. However, if you have other columns, containing [[\MongoId]], you
 * should take care of possible typecast on your own.
 *
 * @property string $fullName Full name of this collection, including database name. This property is
 * read-only.
 * @property array $lastError Last error information. This property is read-only.
 * @property string $name Name of this collection. This property is read-only.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Collection extends Object
{
    /**
     * @var Database MongoDB database instance.
     */
    public $database;
    /**
     * @var string name of this collection.
     */
    public $name;


    /**
     * @return string full name of this collection, including database name.
     */
    public function getFullName()
    {
        return $this->database->name . '.' . $this->name;
    }

    /**
     * Pre-processes the log data before sending it to `json_encode()`.
     * @param mixed $data raw data.
     * @return mixed the processed data.
     */
    private function processLogData($data)
    {
        if (is_object($data)) {
            if ($data instanceof \MongoId ||
                $data instanceof \MongoRegex ||
                $data instanceof \MongoDate ||
                $data instanceof \MongoInt32 ||
                $data instanceof \MongoInt64 ||
                $data instanceof \MongoTimestamp
            ) {
                $data = get_class($data) . '(' . $data->__toString() . ')';
            } elseif ($data instanceof \MongoCode) {
                $data = 'MongoCode( ' . $data->__toString() . ' )';
            } elseif ($data instanceof \MongoBinData) {
                $data = 'MongoBinData(...)';
            } elseif ($data instanceof \MongoDBRef) {
                $data = 'MongoDBRef(...)';
            } elseif ($data instanceof \MongoMinKey || $data instanceof \MongoMaxKey) {
                $data = get_class($data);
            } else {
                $result = [];
                foreach ($data as $name => $value) {
                    $result[$name] = $value;
                }
                $data = $result;
            }

            if ($data === []) {
                return new \stdClass();
            }
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = $this->processLogData($value);
                }
            }
        }

        return $data;
    }

    /**
     * Drops this collection.
     * @throws Exception on failure.
     * @return boolean whether the operation successful.
     */
    public function drop()
    {
        return $this->database->dropCollection($this->name);
    }

    /**
     * Returns the list of defined indexes.
     * @return array list of indexes info.
     * @since 2.1
     */
    public function listIndexes()
    {
        return $this->database->createCommand()->listIndexes($this->name);
    }

    /**
     * Creates an index on the collection and the specified fields.
     * @param array|string $columns column name or list of column names.
     * If array is given, each element in the array has as key the field name, and as
     * value either 1 for ascending sort, or -1 for descending sort.
     * You can specify field using native numeric key with the field name as a value,
     * in this case ascending sort will be used.
     * For example:
     *
     * ```php
     * [
     *     'name',
     *     'status' => -1,
     * ]
     * ```
     *
     * @param array $options list of options in format: optionName => optionValue.
     * @throws Exception on failure.
     * @return boolean whether the operation successful.
     */
    public function createIndex($columns, $options = [])
    {
        $index = array_merge(['key' => $columns], $options);
        return $this->database->createCommand()->createIndexes($this->name, [$index]);
    }

    /**
     * Drop indexes for specified column(s).
     * @param string|array $columns column name or list of column names.
     * If array is given, each element in the array has as key the field name, and as
     * value either 1 for ascending sort, or -1 for descending sort.
     * Use value 'text' to specify text index.
     * You can specify field using native numeric key with the field name as a value,
     * in this case ascending sort will be used.
     * For example:
     *
     * ```php
     * [
     *     'name',
     *     'status' => -1,
     *     'description' => 'text',
     * ]
     * ```
     *
     * @throws Exception on failure.
     * @return boolean whether the operation successful.
     */
    public function dropIndex($columns)
    {
        $existingIndexes = $this->listIndexes();

        $indexKey = $this->database->connection->getQueryBuilder()->buildSortFields($columns);

        foreach ($existingIndexes as $index) {
            if ($index['key'] == $indexKey) {
                $this->database->createCommand()->dropIndexes($this->name, $index['name']);
                return true;
            }
        }

        throw new Exception('Index to be dropped does not exist.');
    }

    /**
     * Drops all indexes for this collection.
     * @throws Exception on failure.
     * @return integer count of dropped indexes.
     */
    public function dropAllIndexes()
    {
        $result = $this->database->createCommand()->dropIndexes($this->name, '*');
        return $result['nIndexesWas'];
    }

    /**
     * Returns a cursor for the search results.
     * In order to perform "find" queries use [[Query]] class.
     * @param array $condition query condition
     * @param array $fields fields to be selected
     * @param array $options query options (available since 2.1).
     * @return \MongoDB\Driver\Cursor cursor for the search results
     * @see Query
     */
    public function find($condition = [], $fields = [], $options = [])
    {
        if (!empty($fields)) {
            $options['projection'] = $fields;
        }
        return $this->database->createCommand()->find($this->name, $condition, $options);
    }

    /**
     * Returns a single document.
     * @param array $condition query condition
     * @param array $fields fields to be selected
     * @param array $options query options (available since 2.1).
     * @return array|null the single document. Null is returned if the query results in nothing.
     */
    public function findOne($condition = [], $fields = [], $options = [])
    {
        $options['limit'] = 1;
        $cursor = $this->find($condition, $fields, $options);
        return current($cursor->toArray());
    }

    /**
     * Updates a document and returns it.
     * @param array $condition query condition
     * @param array $update update criteria
     * @param array $options list of options in format: optionName => optionValue.
     * @return array|null the original document, or the modified document when $options['new'] is set.
     * @throws Exception on failure.
     */
    public function findAndModify($condition, $update, $options = [])
    {
        return $this->database->createCommand()->findAndModify($this->name, $condition, $update, $options);
    }

    /**
     * Inserts new data into collection.
     * @param array|object $data data to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoDB\BSON\ObjectID new record ID instance.
     * @throws Exception on failure.
     */
    public function insert($data, $options = [])
    {
        return $this->database->createCommand()->insert($this->name, $data, $options);
    }

    /**
     * Inserts several new rows into collection.
     * @param array $rows array of arrays or objects to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return array inserted data, each row will have "_id" key assigned to it.
     * @throws Exception on failure.
     */
    public function batchInsert($rows, $options = [])
    {
        $insertedIds = $this->database->createCommand()->batchInsert($this->name, $rows, $options);
        foreach ($rows as $key => $row) {
            $rows[$key]['_id'] = $insertedIds[$key];
        }
        return $rows;
    }

    /**
     * Updates the rows, which matches given criteria by given data.
     * Note: for "multi" mode Mongo requires explicit strategy "$set" or "$inc"
     * to be specified for the "newData". If no strategy is passed "$set" will be used.
     * @param array $condition description of the objects to update.
     * @param array $newData the object with which to update the matching records.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer|boolean number of updated documents or whether operation was successful.
     * @throws Exception on failure.
     */
    public function update($condition, $newData, $options = [])
    {
        $writeResult = $this->database->createCommand()->update($this->name, $condition, $newData, $options);
        return $writeResult->getModifiedCount() + $writeResult->getUpsertedCount();
    }

    /**
     * Update the existing database data, otherwise insert this data
     * @param array|object $data data to be updated/inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoId updated/new record id instance.
     * @throws Exception on failure.
     */
    public function save($data, $options = [])
    {
        if (empty($data['_id'])) {
            return $this->insert($data, $options);
        } else {
            $id = $data['_id'];
            unset($data['_id']);
            $this->update(['_id' => $id], ['$set' => $data], ['upsert' => true]);
            return is_object($id) ? $id : new ObjectID($id);
        }
    }

    /**
     * Removes data from the collection.
     * @param array $condition description of records to remove.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer|boolean number of updated documents or whether operation was successful.
     * @throws Exception on failure.
     */
    public function remove($condition = [], $options = [])
    {
        $options = array_merge(['limit' => 0], $options);
        $writeResult = $this->database->createCommand()->delete($this->name, $condition, $options);
        return $writeResult->getDeletedCount();
    }

    /**
     * Counts records in this collection.
     * @param array $condition query condition
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer records count.
     * @since 2.1
     */
    public function count($condition = [], $options = [])
    {
        return $this->database->createCommand()->count($this->name, $condition, $options);
    }

    /**
     * Returns a list of distinct values for the given column across a collection.
     * @param string $column column to use.
     * @param array $condition query parameters.
     * @param array $options list of options in format: optionName => optionValue.
     * @return array|boolean array of distinct values, or "false" on failure.
     * @throws Exception on failure.
     */
    public function distinct($column, $condition = [], $options = [])
    {
        return $this->database->createCommand()->distinct($this->name, $column, $condition, $options);
    }

    /**
     * Performs aggregation using Mongo Aggregation Framework.
     * @param array $pipelines list of pipeline operators.
     * @param array $options optional parameters.
     * @return array the result of the aggregation.
     * @throws Exception on failure.
     */
    public function aggregate($pipelines, $options = [])
    {
        return $this->database->createCommand()->aggregate($this->name, $pipelines, $options);
    }

    /**
     * Performs aggregation using Mongo "group" command.
     * @param mixed $keys fields to group by. If an array or non-code object is passed,
     * it will be the key used to group results. If instance of [[\MongoDB\BSON\Javascript]] passed,
     * it will be treated as a function that returns the key to group by.
     * @param array $initial Initial value of the aggregation counter object.
     * @param \MongoDB\BSON\Javascript|string $reduce function that takes two arguments (the current
     * document and the aggregation to this point) and does the aggregation.
     * Argument will be automatically cast to [[\MongoDB\BSON\Javascript]].
     * @param array $options optional parameters to the group command. Valid options include:
     *  - condition - criteria for including a document in the aggregation.
     *  - finalize - function called once per unique key that takes the final output of the reduce function.
     * @return array the result of the aggregation.
     * @throws Exception on failure.
     */
    public function group($keys, $initial, $reduce, $options = [])
    {
        return $this->database->createCommand()->group($this->name, $keys, $initial, $reduce, $options);
    }

    /**
     * Performs aggregation using Mongo "map reduce" mechanism.
     * Note: this function will not return the aggregation result, instead it will
     * write it inside the another Mongo collection specified by "out" parameter.
     * For example:
     *
     * ```php
     * $customerCollection = Yii::$app->mongo->getCollection('customer');
     * $resultCollectionName = $customerCollection->mapReduce(
     *     'function () {emit(this.status, this.amount)}',
     *     'function (key, values) {return Array.sum(values)}',
     *     'mapReduceOut',
     *     ['status' => 3]
     * );
     * $query = new Query();
     * $results = $query->from($resultCollectionName)->all();
     * ```
     *
     * @param \MongoCode|string $map function, which emits map data from collection.
     * Argument will be automatically cast to [[\MongoCode]].
     * @param \MongoCode|string $reduce function that takes two arguments (the map key
     * and the map values) and does the aggregation.
     * Argument will be automatically cast to [[\MongoCode]].
     * @param string|array $out output collection name. It could be a string for simple output
     * ('outputCollection'), or an array for parametrized output (['merge' => 'outputCollection']).
     * You can pass ['inline' => true] to fetch the result at once without temporary collection usage.
     * @param array $condition criteria for including a document in the aggregation.
     * @param array $options additional optional parameters to the mapReduce command. Valid options include:
     *  - sort - array - key to sort the input documents. The sort key must be in an existing index for this collection.
     *  - limit - the maximum number of documents to return in the collection.
     *  - finalize - function, which follows the reduce method and modifies the output.
     *  - scope - array - specifies global variables that are accessible in the map, reduce and finalize functions.
     *  - jsMode - boolean -Specifies whether to convert intermediate data into BSON format between the execution of the map and reduce functions.
     *  - verbose - boolean - specifies whether to include the timing information in the result information.
     * @return string|array the map reduce output collection name or output results.
     * @throws Exception on failure.
     */
    public function mapReduce($map, $reduce, $out, $condition = [], $options = [])
    {
        return $this->database->createCommand()->mapReduce($this->name, $map, $reduce, $out, $condition, $options);
    }
}
