<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use yii\base\InvalidParamException;
use yii\base\Object;
use Yii;
use yii\helpers\ArrayHelper;

use MongoDB\BSON\Binary;
use MongoDB\BSON\Javascript;
use MongoDB\BSON\MaxKey;
use MongoDB\BSON\MinKey;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\Timestamp;

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
     * @var \MongoCollection Mongo collection instance.
     */
    public $mongoCollection;
    public $mongoDatabase;


    /**
     * @return string name of this collection.
     */
    public function getName()
    {
        return $this->mongoCollection->getCollectionName();
    }

    /**
     * @return string full name of this collection, including database name.
     */
    public function getFullName()
    {
        return $this->mongoCollection->__toString();
    }

    /**
     * @return array last error information.
     */
    public function getLastError()
    {
        return $this->mongoDatabase->executeCommand(
            [
                'getLastError' => 1
            ]
        );
    }

    /**
     * Composes log/profile token.
     * @param string $command command name
     * @param array $arguments command arguments.
     * @return string token.
     */
    protected function composeLogToken($command, $arguments = [])
    {
        $parts = [];
        foreach ($arguments as $argument) {
            $parts[] = is_scalar($argument) ? $argument : $this->encodeLogData($argument);
        }

        return $this->getFullName() . '.' . $command . '(' . implode(', ', $parts) . ')';
    }

    /**
     * Encodes complex log data into JSON format string.
     * @param mixed $data raw data.
     * @return string encoded data string.
     */
    public function encodeLogData($data)
    {
        return json_encode($this->processLogData($data));
    }

    /**
     * Pre-processes the log data before sending it to `json_encode()`.
     * @param mixed $data raw data.
     * @return mixed the processed data.
     */
    protected function processLogData($data)
    {
        if (is_object($data)) {
            if ($data instanceof ObjectID ||
                $data instanceof Regex ||
                $data instanceof UTCDateTime ||
                $data instanceof Timestamp
            ) {
                $data = get_class($data) . '(' . $data->__toString() . ')';
            } elseif ($data instanceof Javascript) {
                $data = 'MongoCode( ' . $data->__toString() . ' )';
            } elseif ($data instanceof Binary) {
                $data = 'MongoBinData(...)';
            /* Does not exist anymore but there is a JIRA for it being put in
            } elseif ($data instanceof \MongoDBRef) {
                $data = 'MongoDBRef(...)';
            */
            } elseif ($data instanceof MinKey || $data instanceof MaxKey) {
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
        $token = $this->composeLogToken('drop');
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $result = $this->mongoCollection->drop();
            $this->tryResultError($result);
            Yii::endProfile($token, __METHOD__);

            return true;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
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
        $columns = (array)$columns;
        $keys = $this->normalizeIndexKeys($columns);
        $token = $this->composeLogToken('createIndex', [$keys, $options]);
        Yii::info($token, __METHOD__);
        
        // Cannot assign w to this operation anymore
        // Everything works by exceptions now
        try {
            Yii::beginProfile($token, __METHOD__);
            $this->mongoCollection->createIndex($keys, $options);
            Yii::endProfile($token, __METHOD__);
            return true;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
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
        $columns = (array)$columns;
        $keys = $this->normalizeIndexKeys($columns);
        $token = $this->composeLogToken('dropIndex', [$keys]);
        Yii::info($token, __METHOD__);
        try {
            $index = [];
            foreach($keys as $k => $v){
                $index[] = $k . '_' . $v;
            }
            
            $result = $this->mongoCollection->deleteIndex(implode('_', $index));
            $this->tryResultError($result);

            return true;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Compose index keys from given columns/keys list.
     * @param array $columns raw columns/keys list.
     * @return array normalizes index keys array.
     */
    protected function normalizeIndexKeys($columns)
    {
        $keys = [];
        foreach ($columns as $key => $value) {
            if (is_numeric($key)) {
                $keys[$value] = 1;
            } else {
                $keys[$key] = $value;
            }
        }

        return $keys;
    }

    /**
     * Drops all indexes for this collection.
     * @throws Exception on failure.
     * @return integer count of dropped indexes.
     */
    public function dropAllIndexes()
    {
        $token = $this->composeLogToken('dropIndexes');
        Yii::info($token, __METHOD__);
        try {
            $result = $this->mongoCollection->dropIndexes();
            $this->tryResultError($result);

            return $result['nIndexesWas'];
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
    
    public function count($condition = [], $options = [])
    {
        $token = $this->composeLogToken('count');
        Yii::info($token, __METHOD__);
        try {
            $result = $this->mongoCollection->count($condition, $options);
            return $result;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Returns a cursor for the search results.
     * In order to perform "find" queries use [[Query]] class.
     * @param array $condition query condition
     * @param array $fields fields to be selected
     * @return \MongoCursor cursor for the search results
     * @see Query
     */
    public function find($condition = [], $options = [])
    {
        return $this->mongoCollection->find($this->buildCondition($condition), $options);
    }

    /**
     * Returns a single document.
     * @param array $condition query condition
     * @param array $fields fields to be selected
     * @return array|null the single document. Null is returned if the query results in nothing.
     * @see http://www.php.net/manual/en/mongocollection.findone.php
     */
    public function findOne($condition = [], $options = [])
    {
        return $this->mongoCollection->findOne($this->buildCondition($condition), $options);
    }

    /**
     * Updates a document and returns it.
     * @param array $condition query condition
     * @param array $update update criteria
     * @param array $fields fields to be returned
     * @param array $options list of options in format: optionName => optionValue.
     * @return array|null the original document, or the modified document when $options['new'] is set.
     * @throws Exception on failure.
     * @see http://www.php.net/manual/en/mongocollection.findandmodify.php
     */
    public function findAndModify($condition, $update, $fields = [], $options = [])
    {
        $condition = $this->buildCondition($condition);
        if($fields){
            $options['fields'] = $fields;
        }
        $token = $this->composeLogToken('findAndModify', [$condition, $update, $fields, $options]);
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            if(isset($options['remove']) && $options['remove']){
                $result = $this->mongoCollection->findOneAndDelete($condition, $options);
            }elseif(isset($options['update']) && $options['update']){
                if(isset($options['upsert']) && $options['upsert']){
                    $result = $this->mongoCollection->findOneAndReplace($condition, $update, $options);
                }else{
                    $result = $this->mongoCollection->findOneAndUpdate($condition, $update, $options);
                }
            }else{
                throw new Exception('Must enter a operation type for findAndModify');
            }
            Yii::endProfile($token, __METHOD__);

            return $result;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Inserts new data into collection.
     * @param array|object $data data to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoId new record id instance.
     * @throws Exception on failure.
     */
    public function insert($data, $options = [])
    {
        $token = $this->composeLogToken('insert', [$data]);
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $result = $this->mongoCollection->insertOne($data, $options);
            Yii::endProfile($token, __METHOD__);
            
            if($result->isAcknowledged()){
                return $result->getInsertedId();
            }else{
                return true;
            }
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
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
        $token = $this->composeLogToken('batchInsert', [$rows]);
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $result = $this->mongoCollection->insertMany($rows, $options);
            Yii::endProfile($token, __METHOD__);
            
            if($result->isAcknowledged()){
                return $result->getInsertedIds();
            }else{
                return true;
            }
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Updates the rows, which matches given criteria by given data.
     * Note: for "multiple" mode Mongo requires explicit strategy "$set" or "$inc"
     * to be specified for the "newData". If no strategy is passed "$set" will be used.
     * @param array $condition description of the objects to update.
     * @param array $newData the object with which to update the matching records.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer|boolean number of updated documents or whether operation was successful.
     * @throws Exception on failure.
     */
    public function update($condition, $newData, $options = [])
    {
        $condition = $this->buildCondition($condition);
        $options = array_merge(['multiple' => true], $options);
        
        // Should always use a $set when no operators present since update 
        // without it will replace the whole document
        $keys = array_keys($newData);
        if (!empty($keys) && strncmp('$', $keys[0], 1) !== 0) {
            $newData = ['$set' => $newData];
        }
        $token = $this->composeLogToken('update', [$condition, $newData, $options]);
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            
            $multiple = $options['multiple'];
            unset($options['multiple']);
            
            if($multiple){
                $result = $this->mongoCollection->updateMany($condition, $newData, $options);
            }else{
                $result = $this->mongoCollection->updateOne($condition, $newData, $options);    
            }
            
            Yii::endProfile($token, __METHOD__);
            
            if(!$result->isAcknowledged()){
                return true;
            }else{
                if(
                    isset($options['upsert']) && 
                    $options['upsert'] && 
                    $result->getUpsertedId()
                ){
                    return $result->getUpsertedCount();
                }else{
                    return $result->getModifiedCount();
                }
            }
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
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
        $token = $this->composeLogToken('save', [$data]);
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $options = array_merge($options, ['upsert' => true]);
            
            $condition = [];
            if(isset($data['_id'])){
                $condition['_id'] = $data['_id'];
                unset($data['_id']);
            }
            
            $result = $this->mongoCollection->replaceOne(
                $condition, 
                $data, 
                $options
            );
            Yii::endProfile($token, __METHOD__);
            if($result->isAcknowledged()){
                if($result->getUpsertedCount() > 0){
                    return $result->getUpsertedId();
                }else{
                    return $condition['_id'];
                }
            }else{
                return true;
            }
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Removes data from the collection.
     * @param array $condition description of records to remove.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer|boolean number of updated documents or whether operation was successful.
     * @throws Exception on failure.
     * @see http://www.php.net/manual/en/mongocollection.remove.php
     */
    public function remove($condition = [], $options = [])
    {
        $condition = $this->buildCondition($condition);
        $options = array_merge(['justOne' => false], $options);
        $token = $this->composeLogToken('remove', [$condition, $options]);
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            if($options['justOne']){
                $result = $this->mongoCollection->deleteOne($condition, $options);
            }else{
                $result = $this->mongoCollection->deleteMany($condition, $options);
            }
            Yii::endProfile($token, __METHOD__);
            if($result->isAcknowledged()){
                return $result->getDeletedCount();
            }
            return true;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Returns a list of distinct values for the given column across a collection.
     * @param string $column column to use.
     * @param array $condition query parameters.
     * @return array|boolean array of distinct values, or "false" on failure.
     * @throws Exception on failure.
     */
    public function distinct($column, $condition = [], $options = [])
    {
        $condition = $this->buildCondition($condition);
        $token = $this->composeLogToken('distinct', [$column, $condition]);
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $result = $this->mongoCollection->distinct($column, $condition, $options);
            Yii::endProfile($token, __METHOD__);

            return $result;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Performs aggregation using Mongo Aggregation Framework.
     * @param array $pipeline list of pipeline operators, or just the first operator
     * @param array $options aggregate options
     * @return array the result of the aggregation.
     * @throws Exception on failure.
     * @see http://docs.mongodb.org/manual/applications/aggregation/
     */
    public function aggregate($pipeline, $options = [])
    {
        $token = $this->composeLogToken('aggregate', [$pipeline, $options]);
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $result = $this->mongoCollection->aggregate($pipeline, $options);
            Yii::endProfile($token, __METHOD__);

            return $result;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Performs aggregation using Mongo "group" command.
     * @param mixed $keys fields to group by. If an array or non-code object is passed,
     * it will be the key used to group results. If instance of [[\MongoCode]] passed,
     * it will be treated as a function that returns the key to group by.
     * @param array $initial Initial value of the aggregation counter object.
     * @param \MongoCode|string $reduce function that takes two arguments (the current
     * document and the aggregation to this point) and does the aggregation.
     * Argument will be automatically cast to [[\MongoCode]].
     * @param array $options optional parameters to the group command. Valid options include:
     *  - condition - criteria for including a document in the aggregation.
     *  - finalize - function called once per unique key that takes the final output of the reduce function.
     * @return array the result of the aggregation.
     * @throws Exception on failure.
     * @see http://docs.mongodb.org/manual/reference/command/group/
     */
    public function group($keys, $initial, $reduce, $options = [])
    {
        if (!($reduce instanceof Javascript)) {
            $reduce = new Javascript((string) $reduce);
        }
        $cond = [];
        if (array_key_exists('condition', $options)) {
            $cond = $this->buildCondition($options['condition']);
        }
        $finalize = null;
        if (array_key_exists('finalize', $options)) {
            if (!($options['finalize'] instanceof Javascript)) {
                $finalize = new Javascript((string) $options['finalize']);
            }
        }
        $keyf = null;
        if (array_key_exists('keyf', $options)) {
            if (!($options['keyf'] instanceof Javascript)) {
                $keyf = new Javascript((string) $options['keyf']);
            }
        }
        
        $token = $this->composeLogToken('group', [$keys, $initial, $reduce, $options]);
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            // Avoid possible E_DEPRECATED for $options:
            $result = $this->mongoDatabase->executeCommand([
               'group' => 
                [
                    'ns' => $this->getName(),
                    'key' => $keys,
                    'initial' => $initial,
                    '$reduce' => $reduce instanceof Javascript 
                        ? $reduce 
                        : new Javascript($reduce),
                    '$keyf' => $keyf,
                    'cond' => $cond,
                    'finalize' => $finalize
                ] 
            ]);
            $this->tryResultError($result);

            Yii::endProfile($token, __METHOD__);
            if (array_key_exists('retval', $result)) {
                return $result['retval'];
            } else {
                return [];
            }
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
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
        if (!($map instanceof Javascript)) {
            $map = new Javascript((string) $map);
        }
        if (!($reduce instanceof Javascript)) {
            $reduce = new Javascript((string) $reduce);
        }
        $command = [
            'mapReduce' => $this->getName(),
            'map' => $map,
            'reduce' => $reduce,
            'out' => $out
        ];
        if (!empty($condition)) {
            $command['query'] = $this->buildCondition($condition);
        }
        if (array_key_exists('finalize', $options)) {
            if (!($options['finalize'] instanceof Javascript)) {
                $options['finalize'] = new Javascript((string) $options['finalize']);
            }
        }
        if (!empty($options)) {
            $command = array_merge($command, $options);
        }
        $token = $this->composeLogToken('mapReduce', [$map, $reduce, $out]);
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $result = $this->mongoDatabase->executeCommand($command);
            $this->tryResultError($result);
            Yii::endProfile($token, __METHOD__);

            return array_key_exists('results', $result) ? $result['results'] : $result['result'];
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Checks if command execution result ended with an error.
     * @param mixed $result raw command execution result.
     * @throws Exception if an error occurred.
     */
    protected function tryResultError($result)
    {
        if (is_array($result)) {
            if (!empty($result['errmsg'])) {
                $errorMessage = $result['errmsg'];
            } elseif (!empty($result['err'])) {
                $errorMessage = $result['err'];
            }
            if (isset($errorMessage)) {
                if (array_key_exists('code', $result)) {
                    $errorCode = (int) $result['code'];
                } elseif (array_key_exists('ok', $result)) {
                    $errorCode = (int) $result['ok'];
                } else {
                    $errorCode = 0;
                }
                throw new Exception($errorMessage, $errorCode);
            }
        } elseif (!$result) {
            throw new Exception('Unknown error, use "w=1" option to enable error tracking');
        }
    }

    /**
     * Throws an exception if there was an error on the last operation.
     * @throws Exception if an error occurred.
     */
    protected function tryLastError()
    {
        $this->tryResultError($this->getLastError());
    }

    /**
     * Converts "\yii\db\*" quick condition keyword into actual Mongo condition keyword.
     * @param string $key raw condition key.
     * @return string actual key.
     */
    protected function normalizeConditionKeyword($key)
    {
        static $map = [
            'AND' => '$and',
            'OR' => '$or',
            'IN' => '$in',
            'NOT IN' => '$nin',
        ];
        $matchKey = strtoupper($key);
        if (array_key_exists($matchKey, $map)) {
            return $map[$matchKey];
        } else {
            return $key;
        }
    }

    /**
     * Converts given value into [[MongoId]] instance.
     * If array given, each element of it will be processed.
     * @param mixed $rawId raw id(s).
     * @return array|\MongoId normalized id(s).
     */
    protected function ensureMongoId($rawId)
    {
        if (is_array($rawId)) {
            $result = [];
            foreach ($rawId as $key => $value) {
                $result[$key] = $this->ensureMongoId($value);
            }

            return $result;
        } elseif (is_object($rawId)) {
            if ($rawId instanceof ObjectID) {
                return $rawId;
            } else {
                $rawId = (string) $rawId;
            }
        }
        try {
            $mongoId = new ObjectID($rawId);
        } catch (\Exception $e) {
            // invalid id format
            $mongoId = $rawId;
        }

        return $mongoId;
    }

    /**
     * Parses the condition specification and generates the corresponding Mongo condition.
     * @param array $condition the condition specification. Please refer to [[Query::where()]]
     * on how to specify a condition.
     * @return array the generated Mongo condition
     * @throws InvalidParamException if the condition is in bad format
     */
    public function buildCondition($condition)
    {
        static $builders = [
            'NOT' => 'buildNotCondition',
            'AND' => 'buildAndCondition',
            'OR' => 'buildOrCondition',
            'BETWEEN' => 'buildBetweenCondition',
            'NOT BETWEEN' => 'buildBetweenCondition',
            'IN' => 'buildInCondition',
            'NOT IN' => 'buildInCondition',
            'REGEX' => 'buildRegexCondition',
            'LIKE' => 'buildLikeCondition',
        ];

        if (!is_array($condition)) {
            throw new InvalidParamException('Condition should be an array.');
        } elseif (empty($condition)) {
            return [];
        }
        if (isset($condition[0])) { // operator format: operator, operand 1, operand 2, ...
            $operator = strtoupper($condition[0]);
            if (isset($builders[$operator])) {
                $method = $builders[$operator];
            } else {
                $operator = $condition[0];
                $method = 'buildSimpleCondition';
            }
            array_shift($condition);
            return $this->$method($operator, $condition);
        } else {
            // hash format: 'column1' => 'value1', 'column2' => 'value2', ...
            return $this->buildHashCondition($condition);
        }
    }

    /**
     * Creates a condition based on column-value pairs.
     * @param array $condition the condition specification.
     * @return array the generated Mongo condition.
     */
    public function buildHashCondition($condition)
    {
        $result = [];
        foreach ($condition as $name => $value) {
            if (strncmp('$', $name, 1) === 0) {
                // Native Mongo condition:
                $result[$name] = $value;
            } else {
                if (is_array($value)) {
                    if (ArrayHelper::isIndexed($value)) {
                        // Quick IN condition:
                        $result = array_merge($result, $this->buildInCondition('IN', [$name, $value]));
                    } else {
                        // Mongo complex condition:
                        $result[$name] = $value;
                    }
                } else {
                    // Direct match:
                    if ($name == '_id') {
                        $value = $this->ensureMongoId($value);
                    }
                    $result[$name] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Composes `NOT` condition.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the Mongo conditions to connect.
     * @return array the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildNotCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }

        list($name, $value) = $operands;

        $result = [];
        if (is_array($value)) {
            $result[$name] = ['$not' => $this->buildCondition($value)];
        } else {
            if ($name == '_id') {
                $value = $this->ensureMongoId($value);
            }
            $result[$name] = ['$ne' => $value];
        }

        return $result;
    }

    /**
     * Connects two or more conditions with the `AND` operator.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the Mongo conditions to connect.
     * @return array the generated Mongo condition.
     */
    public function buildAndCondition($operator, $operands)
    {
        $operator = $this->normalizeConditionKeyword($operator);
        $parts = [];
        foreach ($operands as $operand) {
            $parts[] = $this->buildCondition($operand);
        }

        return [$operator => $parts];
    }

    /**
     * Connects two or more conditions with the `OR` operator.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the Mongo conditions to connect.
     * @return array the generated Mongo condition.
     */
    public function buildOrCondition($operator, $operands)
    {
        $operator = $this->normalizeConditionKeyword($operator);
        $parts = [];
        foreach ($operands as $operand) {
            $parts[] = $this->buildCondition($operand);
        }

        return [$operator => $parts];
    }

    /**
     * Creates an Mongo condition, which emulates the `BETWEEN` operator.
     * @param string $operator the operator to use
     * @param array $operands the first operand is the column name. The second and third operands
     * describe the interval that column value should be in.
     * @return array the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildBetweenCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1], $operands[2])) {
            throw new InvalidParamException("Operator '$operator' requires three operands.");
        }
        list($column, $value1, $value2) = $operands;
        if (strncmp('NOT', $operator, 3) === 0) {
            return [
                $column => [
                    '$lt' => $value1,
                    '$gt' => $value2,
                ]
            ];
        } else {
            return [
                $column => [
                    '$gte' => $value1,
                    '$lte' => $value2,
                ]
            ];
        }
    }

    /**
     * Creates an Mongo condition with the `IN` operator.
     * @param string $operator the operator to use (e.g. `IN` or `NOT IN`)
     * @param array $operands the first operand is the column name. If it is an array
     * a composite IN condition will be generated.
     * The second operand is an array of values that column value should be among.
     * @return array the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildInCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }

        list($column, $values) = $operands;

        $values = (array) $values;
        $operator = $this->normalizeConditionKeyword($operator);

        if (!is_array($column)) {
            $columns = [$column];
            $values = [$column => $values];
        } elseif (count($column) > 1) {
            return $this->buildCompositeInCondition($operator, $column, $values);
        } else {
            $columns = $column;
            $values = [$column[0] => $values];
        }

        $result = [];
        foreach ($columns as $column) {
            if ($column == '_id') {
                $inValues = $this->ensureMongoId($values[$column]);
            } else {
                $inValues = $values[$column];
            }

            $inValues = array_values($inValues);
            if (count($inValues) === 1 && $operator === '$in') {
                $result[$column] = $inValues[0];
            } else {
                $result[$column][$operator] = $inValues;
            }
        }

        return $result;
    }

    /**
     * @param string $operator MongoDB the operator to use (`$in` OR `$nin`)
     * @param array $columns list of compare columns
     * @param array $values compare values in format: columnName => [values]
     * @return array the generated Mongo condition.
     */
    private function buildCompositeInCondition($operator, $columns, $values)
    {
        $result = [];

        $inValues = [];
        foreach ($values as $columnValues) {
            foreach ($columnValues as $column => $value) {
                if ($column == '_id') {
                    $value = $this->ensureMongoId($value);
                }
                $inValues[$column][] = $value;
            }
        }

        foreach ($columns as $column) {
            $columnInValues = array_values($inValues[$column]);
            if (count($columnInValues) === 1 && $operator === '$in') {
                $result[$column] = $columnInValues[0];
            } else {
                $result[$column][$operator] = $columnInValues;
            }
        }

        return $result;
    }

    /**
     * Creates a Mongo regular expression condition.
     * @param string $operator the operator to use
     * @param array $operands the first operand is the column name.
     * The second operand is a single value that column value should be compared with.
     * @return array the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildRegexCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }
        list($column, $value) = $operands;
        if (!($value instanceof Regex)) {
            $value = ltrim($value, '/');
            $flags = '';
            
            $parts = explode('/', $value);
            if(count($parts) > 1){
                $value = $parts[0];
                $flags = $parts[1];
            }
            $value = new Regex(rtrim($value, '/'), $flags);
        }

        return [$column => $value];
    }

    /**
     * Creates a Mongo condition, which emulates the `LIKE` operator.
     * @param string $operator the operator to use
     * @param array $operands the first operand is the column name.
     * The second operand is a single value that column value should be compared with.
     * @return array the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildLikeCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }
        list($column, $value) = $operands;
        if (!($value instanceof Regex)) {
            $value = new Regex(preg_quote($value), 'i');
        }

        return [$column => $value];
    }

    /**
     * Creates an Mongo condition like `{$operator:{field:value}}`.
     * @param string $operator the operator to use. Besides regular MongoDB operators, aliases like `>`, `<=`,
     * and so on, can be used here.
     * @param array $operands the first operand is the column name.
     * The second operand is a single value that column value should be compared with.
     * @return string the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildSimpleCondition($operator, $operands)
    {
        if (count($operands) !== 2) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }

        list($column, $value) = $operands;

        if (strncmp('$', $operator, 1) !== 0) {
            static $operatorMap = [
                '>' => '$gt',
                '<' => '$lt',
                '>=' => '$gte',
                '<=' => '$lte',
                '!=' => '$ne',
                '<>' => '$ne',
                '=' => '$eq',
                '==' => '$eq',
            ];
            if (isset($operatorMap[$operator])) {
                $operator = $operatorMap[$operator];
            } else {
                throw new InvalidParamException("Unsupported operator '{$operator}'");
            }
        }

        return [$column => [$operator => $value]];
    }
}
