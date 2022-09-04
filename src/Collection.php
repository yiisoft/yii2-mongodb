<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use MongoDB\BSON\ObjectID;
use yii\base\BaseObject;
use Yii;

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
 * Collection also provides shortcut for [[Command]] methods, such as [[group()]], [[mapReduce()]] and so on.
 *
 * To perform "find" queries, please use [[Query]] instead.
 *
 * @property-read string $fullName Full name of this collection, including database name.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Collection extends BaseObject
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
     * Drops this collection.
     * @param array $execOptions {@see Command::dropCollection()}
     * @throws Exception on failure.
     * @return bool whether the operation successful.
     */
    public function drop($execOptions = [])
    {
        return $this->database->dropCollection($this->name, $execOptions);
    }

    /**
     * Returns the list of defined indexes.
     * @return array list of indexes info.
     * @param array $options list of options in format: optionName => optionValue.
     * @param array $execOptions {@see Command::listIndexes()}
     * @since 2.1
     */
    public function listIndexes($options = [], $execOptions = [])
    {
        return $this->database->createCommand()->listIndexes($this->name, $options, $execOptions);
    }

    /**
     * Creates several indexes at once.
     * Example:
     *
     * ```php
     * $collection = Yii::$app->mongo->getCollection('customer');
     * $collection->createIndexes([
     *     [
     *         'key' => ['name'],
     *     ],
     *     [
     *         'key' => [
     *             'email' => 1,
     *             'address' => -1,
     *         ],
     *         'name' => 'my_index'
     *     ],
     * ]);
     * ```
     *
     * @param array $indexes indexes specification, each index should be specified as an array.
     * @param array[] $indexes indexes specification. Each specification should be an array in format: optionName => value
     * The main options are:
     *
     * - keys: array, column names with sort order, to be indexed. This option is mandatory.
     * - unique: bool, whether to create unique index.
     * - name: string, the name of the index, if not set it will be generated automatically.
     * - background: bool, whether to bind index in the background.
     * - sparse: bool, whether index should reference only documents with the specified field.
     *
     * See [[https://docs.mongodb.com/manual/reference/method/db.collection.createIndex/#options-for-all-index-types]]
     * for the full list of options.
     * @param array $execOptions {@see Command::createIndexes()}
     * @return bool whether operation was successful.
     * @since 2.1
     */
    public function createIndexes($indexes, $execOptions = [])
    {
        return $this->database->createCommand()->createIndexes($this->name, $indexes, $execOptions);
    }

    /**
     * Drops collection indexes by name.
     * @param string $indexes wildcard for name of the indexes to be dropped.
     * You can use `*` to drop all indexes.
     * @param array $execOptions {@see Command::dropIndexes()}
     * @return int count of dropped indexes.
     */
    public function dropIndexes($indexes, $execOptions = [])
    {
        $result = $this->database->createCommand()->dropIndexes($this->name, $indexes, $execOptions);
        return $result['nIndexesWas'];
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
     * @param array $execOptions {@see Command::createIndexes()}
     * @throws Exception on failure.
     * @return bool whether the operation successful.
     */
    public function createIndex($columns, $options = [], $execOptions = [])
    {
        $index = array_merge(['key' => $columns], $options);
        return $this->database->createCommand()->createIndexes($this->name, [$index], $execOptions);
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
     * @param array $execOptions {@see Command::dropIndexes()}
     * @throws Exception on failure.
     * @return bool whether the operation successful.
     */
    public function dropIndex($columns, $execOptions = [])
    {
        $existingIndexes = $this->listIndexes();

        $indexKey = $this->database->connection->getQueryBuilder()->buildSortFields($columns);
        foreach ($existingIndexes as $index) {
            if ($index['key'] == $indexKey) {
                $this->database->createCommand()->dropIndexes($this->name, $index['name'], $execOptions);
                return true;
            }
        }

        // Index plugin usage such as 'text' may cause unpredictable index 'key' structure, thus index name should be used
        $indexName = $this->database->connection->getQueryBuilder()->generateIndexName($indexKey);
        foreach ($existingIndexes as $index) {
            if ($index['name'] === $indexName) {
                $this->database->createCommand()->dropIndexes($this->name, $index['name'], $execOptions);
                return true;
            }
        }

        throw new Exception('Index to be dropped does not exist.');
    }

    /**
     * Drops all indexes for this collection.
     * @param array $execOptions {@see Command::dropIndexes()}
     * @throws Exception on failure.
     * @return int count of dropped indexes.
     */
    public function dropAllIndexes($execOptions = [])
    {
        $result = $this->database->createCommand()->dropIndexes($this->name, '*', $execOptions);
        return isset($result['nIndexesWas']) ? $result['nIndexesWas'] : 0;
    }

    /**
     * Returns a cursor for the search results.
     * In order to perform "find" queries use [[Query]] class.
     * @param array $condition query condition
     * @param array $fields fields to be selected
     * @param array $options query options (available since 2.1).
     * @param array $execOptions {@see Command::find()}
     * @return \MongoDB\Driver\Cursor cursor for the search results
     * @see Query
     */
    public function find($condition = [], $fields = [], $options = [], $execOptions = [])
    {
        if (!empty($fields)) {
            $options['projection'] = $fields;
        }
        return $this->database->createCommand()->find($this->name, $condition, $options, $execOptions);
    }

    /**
     * Returns a single document.
     * @param array $condition query condition
     * @param array $fields fields to be selected
     * @param array $options query options (available since 2.1).
     * @param array $execOptions {@see find()}
     * @return array|null the single document. Null is returned if the query results in nothing.
     */
    public function findOne($condition = [], $fields = [], $options = [], $execOptions = [])
    {
        $options['limit'] = 1;
        $cursor = $this->find($condition, $fields, $options, $execOptions);
        $rows = $cursor->toArray();
        return empty($rows) ? null : current($rows);
    }

    /**
     * Returns if a document exists.
     * @param array $condition Query condition.
     * @return bool
     * @since 2.0.39
     */
    public function documentExists($condition = [])
    {
        return static::findOne($condition, ['_id' => 1]) !== null;
    }

    /**
     * Updates a document and returns it.
     * @param array $condition query condition
     * @param array $update update criteria
     * @param array $options list of options in format: optionName => optionValue.
     * @param array $execOptions {@see Command::findAndModify()}
     * @return array|null the original document, or the modified document when $options['new'] is set.
     * @throws Exception on failure.
     */
    public function findAndModify($condition, $update, $options = [], $execOptions = [])
    {
        return $this->database->createCommand()->findAndModify($this->name, $condition, $update, $options, $execOptions);
    }

    /**
     * Inserts new data into collection.
     * @param array|object $data data to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoDB\BSON\ObjectID new record ID instance.
     * @param array $execOptions {@see Command::insert()}
     * @throws Exception on failure.
     */
    public function insert($data, $options = [], $execOptions = [])
    {
        return $this->database->createCommand()->insert($this->name, $data, $options, $execOptions);
    }

    /**
     * Inserts several new rows into collection.
     * @param array $rows array of arrays or objects to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @param array $execOptions {@see Command::batchInsert()}
     * @return array inserted data, each row will have "_id" key assigned to it.
     * @throws Exception on failure.
     */
    public function batchInsert($rows, $options = [], $execOptions = [])
    {
        $insertedIds = $this->database->createCommand()->batchInsert($this->name, $rows, $options, $execOptions);
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
     * @param array $execOptions {@see Command::update()}
     * @return int|bool number of updated documents or whether operation was successful.
     * @throws Exception on failure.
     */
    public function update($condition, $newData, $options = [], $execOptions = [])
    {
        $writeResult = $this->database->createCommand()->update($this->name, $condition, $newData, $options, $execOptions);
        return $writeResult->getModifiedCount() + $writeResult->getUpsertedCount();
    }

    /**
     * Update the existing database data, otherwise insert this data
     * @param array|object $data data to be updated/inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoDB\BSON\ObjectID updated/new record id instance.
     * @param array $execOptions {@see Command::insert()}
     * @throws Exception on failure.
     */
    public function save($data, $options = [], $execOptions = [])
    {
        if (empty($data['_id'])) {
            return $this->insert($data, $options, $execOptions);
        }
        $id = $data['_id'];
        unset($data['_id']);
        $this->update(['_id' => $id], ['$set' => $data], ['upsert' => true], $execOptions);

        return is_object($id) ? $id : new ObjectID($id);
    }

    /**
     * Removes data from the collection.
     * @param array $condition description of records to remove.
     * @param array $options list of options in format: optionName => optionValue.
     * @param array $execOptions {@see Command::delete()}
     * @return int|bool number of updated documents or whether operation was successful.
     * @throws Exception on failure.
     */
    public function remove($condition = [], $options = [], $execOptions = [])
    {
        $options = array_merge(['limit' => 0], $options);
        $writeResult = $this->database->createCommand()->delete($this->name, $condition, $options, $execOptions);
        return $writeResult->getDeletedCount();
    }

    /**
     * Counts records in this collection.
     * @param array $condition query condition
     * @param array $options list of options in format: optionName => optionValue.
     * @param array $execOptions {@see Command::count()}
     * @return int records count.
     * @since 2.1
     */
    public function count($condition = [], $options = [], $execOptions = [])
    {
        return $this->database->createCommand()->count($this->name, $condition, $options, $execOptions);
    }

    /**
     * Returns a list of distinct values for the given column across a collection.
     * @param string $column column to use.
     * @param array $condition query parameters.
     * @param array $options list of options in format: optionName => optionValue.
     * @param array $execOptions {@see Command::distinct()}
     * @return array|bool array of distinct values, or "false" on failure.
     * @throws Exception on failure.
     */
    public function distinct($column, $condition = [], $options = [], $execOptions = [])
    {
        return $this->database->createCommand()->distinct($this->name, $column, $condition, $options, $execOptions);
    }

    /**
     * Performs aggregation using Mongo Aggregation Framework.
     * In case 'cursor' option is specified [[\MongoDB\Driver\Cursor]] instance is returned,
     * otherwise - an array of aggregation results.
     * @param array $pipelines list of pipeline operators.
     * @param array $options optional parameters.
     * @param array $execOptions {@see Command::aggregate()}
     * @return array|\MongoDB\Driver\Cursor the result of the aggregation.
     * @throws Exception on failure.
     */
    public function aggregate($pipelines, $options = [], $execOptions = [])
    {
        return $this->database->createCommand()->aggregate($this->name, $pipelines, $options, $execOptions);
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
     * @param array $execOptions {@see Command::group()}
     * @return array the result of the aggregation.
     * @throws Exception on failure.
     */
    public function group($keys, $initial, $reduce, $options = [], $execOptions = [])
    {
        return $this->database->createCommand()->group($this->name, $keys, $initial, $reduce, $options, $execOptions);
    }

    /**
     * Performs aggregation using MongoDB "map-reduce" mechanism.
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
     * @param \MongoDB\BSON\Javascript|string $map function, which emits map data from collection.
     * Argument will be automatically cast to [[\MongoDB\BSON\Javascript]].
     * @param \MongoDB\BSON\Javascript|string $reduce function that takes two arguments (the map key
     * and the map values) and does the aggregation.
     * Argument will be automatically cast to [[\MongoDB\BSON\Javascript]].
     * @param string|array $out output collection name. It could be a string for simple output
     * ('outputCollection'), or an array for parametrized output (['merge' => 'outputCollection']).
     * You can pass ['inline' => true] to fetch the result at once without temporary collection usage.
     * @param array $condition criteria for including a document in the aggregation.
     * @param array $options additional optional parameters to the mapReduce command. Valid options include:
     *
     * - sort: array, key to sort the input documents. The sort key must be in an existing index for this collection.
     * - limit: int, the maximum number of documents to return in the collection.
     * - finalize: \MongoDB\BSON\Javascript|string, function, which follows the reduce method and modifies the output.
     * - scope: array, specifies global variables that are accessible in the map, reduce and finalize functions.
     * - jsMode: bool, specifies whether to convert intermediate data into BSON format between the execution of the map and reduce functions.
     * - verbose: bool, specifies whether to include the timing information in the result information.
     *
     * @param array $execOptions {@see Command::mapReduce()}
     * @return string|array the map reduce output collection name or output results.
     * @throws Exception on failure.
     */
    public function mapReduce($map, $reduce, $out, $condition = [], $options = [], $execOptions = [])
    {
        return $this->database->createCommand()->mapReduce($this->name, $map, $reduce, $out, $condition, $options, $execOptions);
    }
}
