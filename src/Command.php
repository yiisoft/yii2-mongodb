<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use MongoDB\BSON\ObjectID;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\WriteResult;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\BaseObject;

/**
 * Command represents MongoDB statement such as command or query.
 *
 * A command object is usually created by calling [[Connection::createCommand()]] or [[Database::createCommand()]].
 * The statement it represents can be set via the [[document]] property.
 *
 * To execute a non-query command, such as 'listIndexes', 'count', 'distinct' and so on, call [[execute()]].
 * For example:
 *
 * ```php
 * $result = Yii::$app->mongodb->createCommand(['listIndexes' => 'some_collection'])->execute();
 * ```
 *
 * To execute a 'find' command, which return cursor, call [[query()]].
 * For example:
 *
 * ```php
 * $cursor = Yii::$app->mongodb->createCommand(['projection' => ['name' => true]])->query('some_collection');
 * ```
 *
 * To execute batch (bulk) operations, call [[executeBatch()]].
 * For example:
 *
 * ```php
 * Yii::$app->mongodb->createCommand()
 *     ->addInsert(['name' => 'new'])
 *     ->addUpdate(['name' => 'existing'], ['name' => 'updated'])
 *     ->addDelete(['name' => 'old'])
 *     ->executeBatch('some_collection');
 * ```
 *
 * @property ReadConcern|string $readConcern Read concern to be used in this command.
 * @property ReadPreference $readPreference Read preference. Note that the type of this property differs in
 * getter and setter. See [[getReadPreference()]] and [[setReadPreference()]] for details.
 * @property WriteConcern|null $writeConcern Write concern to be used in this command. Note that the type of
 * this property differs in getter and setter. See [[getWriteConcern()]] and [[setWriteConcern()]] for details.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class Command extends BaseObject
{
    /**
     * @var Connection the MongoDB connection that this command is associated with.
     */
    public $db;
    /**
     * @var string name of the database that this command is associated with.
     */
    public $databaseName;
    /**
     * @var array command document contents.
     */
    public $document = [];

    /**
     * @var ReadPreference|int|string|null command read preference.
     */
    private $_readPreference;
    /**
     * @var WriteConcern|int|string|null write concern to be used by this command.
     */
    private $_writeConcern;
    /**
     * @var ReadConcern|string read concern to be used by this command
     */
    private $_readConcern;


    /**
     * Returns read preference for this command.
     * @return ReadPreference read preference.
     */
    public function getReadPreference()
    {
        if (!is_object($this->_readPreference)) {
            if ($this->_readPreference === null) {
                $this->_readPreference = $this->db->manager->getReadPreference();
            } elseif (is_scalar($this->_readPreference)) {
                $this->_readPreference = new ReadPreference($this->_readPreference);
            }
        }
        return $this->_readPreference;
    }

    /**
     * Sets read preference for this command.
     * @param ReadPreference|int|string|null $readPreference read reference, it can be specified as
     * instance of [[ReadPreference]] or scalar mode value, for example: `ReadPreference::RP_PRIMARY`.
     * @return $this self reference.
     */
    public function setReadPreference($readPreference)
    {
        $this->_readPreference = $readPreference;
        return $this;
    }

    /**
     * Returns write concern for this command.
     * @return WriteConcern|null write concern to be used in this command.
     */
    public function getWriteConcern()
    {
        if ($this->_writeConcern !== null) {
            if (is_scalar($this->_writeConcern)) {
                $this->_writeConcern = new WriteConcern($this->_writeConcern);
            }
        }
        return $this->_writeConcern;
    }

    /**
     * Sets write concern for this command.
     * @param WriteConcern|int|string|null $writeConcern write concern, it can be an instance of [[WriteConcern]]
     * or its scalar mode value, for example: `majority`.
     * @return $this self reference
     */
    public function setWriteConcern($writeConcern)
    {
        $this->_writeConcern = $writeConcern;
        return $this;
    }

    /**
     * Retuns read concern for this command.
     * @return ReadConcern|string read concern to be used in this command.
     */
    public function getReadConcern()
    {
        if ($this->_readConcern !== null) {
            if (is_scalar($this->_readConcern)) {
                $this->_readConcern = new ReadConcern($this->_readConcern);
            }
        }
        return $this->_readConcern;
    }

    /**
     * Sets read concern for this command.
     * @param ReadConcern|string $readConcern read concern, it can be an instance of [[ReadConcern]] or
     * scalar level value, for example: 'local'.
     * @return $this self reference
     */
    public function setReadConcern($readConcern)
    {
        $this->_readConcern = $readConcern;
        return $this;
    }

    /**
     * Executes this command.
     * @return \MongoDB\Driver\Cursor result cursor.
     * @throws Exception on failure.
     */
    public function execute()
    {
        $databaseName = $this->databaseName === null ? $this->db->defaultDatabaseName : $this->databaseName;

        $token = $this->log([$databaseName, 'command'], $this->document, __METHOD__);

        try {
            $this->beginProfile($token, __METHOD__);

            $this->db->open();
            $mongoCommand = new \MongoDB\Driver\Command($this->document);
            $cursor = $this->db->manager->executeCommand($databaseName, $mongoCommand, $this->getReadPreference());
            $cursor->setTypeMap($this->db->typeMap);

            $this->endProfile($token, __METHOD__);
        } catch (RuntimeException $e) {
            $this->endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        return $cursor;
    }

    /**
     * Execute commands batch (bulk).
     * @param string $collectionName collection name.
     * @param array $options batch options.
     * @return array array of 2 elements:
     *
     * - 'insertedIds' - contains inserted IDs.
     * - 'result' - [[\MongoDB\Driver\WriteResult]] instance.
     *
     * @throws Exception on failure.
     * @throws InvalidConfigException on invalid [[document]] format.
     */
    public function executeBatch($collectionName, $options = [])
    {
        $databaseName = $this->databaseName === null ? $this->db->defaultDatabaseName : $this->databaseName;

        $token = $this->log([$databaseName, $collectionName, 'bulkWrite'], $this->document, __METHOD__);

        try {
            $this->beginProfile($token, __METHOD__);

            $batch = new BulkWrite($options);

            $insertedIds = [];
            foreach ($this->document as $key => $operation) {
                switch ($operation['type']) {
                    case 'insert':
                        $insertedIds[$key] = $batch->insert($operation['document']);
                        break;
                    case 'update':
                        $batch->update($operation['condition'], $operation['document'], $operation['options']);
                        break;
                    case 'delete':
                        $batch->delete($operation['condition'], isset($operation['options']) ? $operation['options'] : []);
                        break;
                    default:
                        throw new InvalidConfigException("Unsupported batch operation type '{$operation['type']}'");
                }
            }

            $this->db->open();
            $writeResult = $this->db->manager->executeBulkWrite($databaseName . '.' . $collectionName, $batch, $this->getWriteConcern());

            $this->endProfile($token, __METHOD__);
        } catch (RuntimeException $e) {
            $this->endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        return [
            'insertedIds' => $insertedIds,
            'result' => $writeResult,
        ];
    }

    /**
     * Executes this command as a mongo query
     * @param string $collectionName collection name
     * @param array $options query options.
     * @return \MongoDB\Driver\Cursor result cursor.
     * @throws Exception on failure
     */
    public function query($collectionName, $options = [])
    {
        $databaseName = $this->databaseName === null ? $this->db->defaultDatabaseName : $this->databaseName;

        $token = $this->log(
            'find',
            array_merge(
                [
                    'ns' => $databaseName . '.' . $collectionName,
                    'filter' => $this->document,
                ],
                $options
            ),
            __METHOD__
        );

        $readConcern = $this->getReadConcern();
        if ($readConcern !== null) {
            $options['readConcern'] = $readConcern;
        }

        try {
            $this->beginProfile($token, __METHOD__);

            $query = new \MongoDB\Driver\Query($this->document, $options);
            $this->db->open();
            $cursor = $this->db->manager->executeQuery($databaseName . '.' . $collectionName, $query, $this->getReadPreference());
            $cursor->setTypeMap($this->db->typeMap);

            $this->endProfile($token, __METHOD__);
        } catch (RuntimeException $e) {
            $this->endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        return $cursor;
    }

    /**
     * Drops database associated with this command.
     * @return bool whether operation was successful.
     */
    public function dropDatabase()
    {
        $this->document = $this->db->getQueryBuilder()->dropDatabase();

        $result = current($this->execute()->toArray());
        return $result['ok'] > 0;
    }

    /**
     * Creates new collection in database associated with this command.s
     * @param string $collectionName collection name
     * @param array $options collection options in format: "name" => "value"
     * @return bool whether operation was successful.
     */
    public function createCollection($collectionName, array $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->createCollection($collectionName, $options);

        $result = current($this->execute()->toArray());
        return $result['ok'] > 0;
    }

    /**
     * Drops specified collection.
     * @param string $collectionName name of the collection to be dropped.
     * @return bool whether operation was successful.
     */
    public function dropCollection($collectionName)
    {
        $this->document = $this->db->getQueryBuilder()->dropCollection($collectionName);

        $result = current($this->execute()->toArray());
        return $result['ok'] > 0;
    }

    /**
     * Creates indexes in the collection.
     * @param string $collectionName collection name.
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
     * @return bool whether operation was successful.
     */
    public function createIndexes($collectionName, $indexes)
    {
        $this->document = $this->db->getQueryBuilder()->createIndexes($this->databaseName, $collectionName, $indexes);

        $result = current($this->execute()->toArray());
        return $result['ok'] > 0;
    }

    /**
     * Drops collection indexes by name.
     * @param string $collectionName collection name.
     * @param string $indexes wildcard for name of the indexes to be dropped.
     * @return array result data.
     */
    public function dropIndexes($collectionName, $indexes)
    {
        $this->document = $this->db->getQueryBuilder()->dropIndexes($collectionName, $indexes);

        return current($this->execute()->toArray());
    }

    /**
     * Returns information about current collection indexes.
     * @param string $collectionName collection name
     * @param array $options list of options in format: optionName => optionValue.
     * @return array list of indexes info.
     * @throws Exception on failure.
     */
    public function listIndexes($collectionName, $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->listIndexes($collectionName, $options);

        try {
            $cursor = $this->execute();
        } catch (Exception $e) {
            // The server may return an error if the collection does not exist.
            $notFoundCodes = [
                26, // namespace not found
                60 // database not found
            ];
            if (in_array($e->getCode(), $notFoundCodes, true)) {
                return [];
            }

            throw $e;
        }

        return $cursor->toArray();
    }

    /**
     * Counts records in specified collection.
     * @param string $collectionName collection name
     * @param array $condition filter condition
     * @param array $options list of options in format: optionName => optionValue.
     * @return int records count
     */
    public function count($collectionName, $condition = [], $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->count($collectionName, $condition, $options);

        $result = current($this->execute()->toArray());
        return $result['n'];
    }

    /**
     * Adds the insert operation to the batch command.
     * @param array $document document to be inserted
     * @return $this self reference.
     * @see executeBatch()
     */
    public function addInsert($document)
    {
        $this->document[] = [
            'type' => 'insert',
            'document' => $document,
        ];
        return $this;
    }

    /**
     * Adds the update operation to the batch command.
     * @param array $condition filter condition
     * @param array $document data to be updated
     * @param array $options update options.
     * @return $this self reference.
     * @see executeBatch()
     */
    public function addUpdate($condition, $document, $options = [])
    {
        $options = array_merge(
            [
                'multi' => true,
                'upsert' => false,
            ],
            $options
        );

        if ($options['multi']) {
            $keys = array_keys($document);
            if (!empty($keys) && strncmp('$', $keys[0], 1) !== 0) {
                $document = ['$set' => $document];
            }
        }

        $this->document[] = [
            'type' => 'update',
            'condition' => $this->db->getQueryBuilder()->buildCondition($condition),
            'document' => $document,
            'options' => $options,
        ];
        return $this;
    }

    /**
     * Adds the delete operation to the batch command.
     * @param array $condition filter condition.
     * @param array $options delete options.
     * @return $this self reference.
     * @see executeBatch()
     */
    public function addDelete($condition, $options = [])
    {
        $this->document[] = [
            'type' => 'delete',
            'condition' => $this->db->getQueryBuilder()->buildCondition($condition),
            'options' => $options,
        ];
        return $this;
    }

    /**
     * Inserts new document into collection.
     * @param string $collectionName collection name
     * @param array $document document content
     * @param array $options list of options in format: optionName => optionValue.
     * @return ObjectID|bool inserted record ID, `false` - on failure.
     */
    public function insert($collectionName, $document, $options = [])
    {
        $this->document = [];
        $this->addInsert($document);
        $result = $this->executeBatch($collectionName, $options);

        if ($result['result']->getInsertedCount() < 1) {
            return false;
        }

        return reset($result['insertedIds']);
    }

    /**
     * Inserts batch of new documents into collection.
     * @param string $collectionName collection name
     * @param array[] $documents documents list
     * @param array $options list of options in format: optionName => optionValue.
     * @return array|false list of inserted IDs, `false` on failure.
     */
    public function batchInsert($collectionName, $documents, $options = [])
    {
        $this->document = [];
        foreach ($documents as $key => $document) {
            $this->document[$key] = [
                'type' => 'insert',
                'document' => $document
            ];
        }

        $result = $this->executeBatch($collectionName, $options);

        if ($result['result']->getInsertedCount() < 1) {
            return false;
        }

        return $result['insertedIds'];
    }

    /**
     * Update existing documents in the collection.
     * @param string $collectionName collection name
     * @param array $condition filter condition
     * @param array $document data to be updated.
     * @param array $options update options.
     * @return WriteResult write result.
     */
    public function update($collectionName, $condition, $document, $options = [])
    {
        $batchOptions = [];
        foreach (['bypassDocumentValidation'] as $name) {
            if (isset($options[$name])) {
                $batchOptions[$name] = $options[$name];
                unset($options[$name]);
            }
        }

        $this->document = [];
        $this->addUpdate($condition, $document, $options);
        $result = $this->executeBatch($collectionName, $batchOptions);

        return $result['result'];
    }

    /**
     * Removes documents from the collection.
     * @param string $collectionName collection name.
     * @param array $condition filter condition.
     * @param array $options delete options.
     * @return WriteResult write result.
     */
    public function delete($collectionName, $condition, $options = [])
    {
        $batchOptions = [];
        foreach (['bypassDocumentValidation'] as $name) {
            if (isset($options[$name])) {
                $batchOptions[$name] = $options[$name];
                unset($options[$name]);
            }
        }

        $this->document = [];
        $this->addDelete($condition, $options);
        $result = $this->executeBatch($collectionName, $batchOptions);

        return $result['result'];
    }

    /**
     * Performs find query.
     * @param string $collectionName collection name
     * @param array $condition filter condition
     * @param array $options query options.
     * @return \MongoDB\Driver\Cursor result cursor.
     */
    public function find($collectionName, $condition, $options = [])
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $this->document = $queryBuilder->buildCondition($condition);

        if (isset($options['projection'])) {
            $options['projection'] = $queryBuilder->buildSelectFields($options['projection']);
        }

        if (isset($options['sort'])) {
            $options['sort'] = $queryBuilder->buildSortFields($options['sort']);
        }

        if (array_key_exists('limit', $options)) {
            if ($options['limit'] === null || !ctype_digit((string) $options['limit'])) {
                unset($options['limit']);
            } else {
                $options['limit'] = (int)$options['limit'];
            }
        }
        if (array_key_exists('skip', $options)) {
            if ($options['skip'] === null || !ctype_digit((string) $options['skip'])) {
                unset($options['skip']);
            } else {
                $options['skip'] = (int)$options['skip'];
            }
        }

        return $this->query($collectionName, $options);
    }

    /**
     * Updates a document and returns it.
     * @param $collectionName
     * @param array $condition query condition
     * @param array $update update criteria
     * @param array $options list of options in format: optionName => optionValue.
     * @return array|null the original document, or the modified document when $options['new'] is set.
     */
    public function findAndModify($collectionName, $condition = [], $update = [], $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->findAndModify($collectionName, $condition, $update, $options);
        $cursor = $this->execute();

        $result = current($cursor->toArray());

        if (!isset($result['value'])) {
            return null;
        }

        return $result['value'];
    }

    /**
     * Returns a list of distinct values for the given column across a collection.
     * @param string $collectionName collection name.
     * @param string $fieldName field name to use.
     * @param array $condition query parameters.
     * @param array $options list of options in format: optionName => optionValue.
     * @return array array of distinct values, or "false" on failure.
     */
    public function distinct($collectionName, $fieldName, $condition = [], $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->distinct($collectionName, $fieldName, $condition, $options);
        $cursor = $this->execute();

        $result = current($cursor->toArray());

        if (!isset($result['values']) || !is_array($result['values'])) {
            return false;
        }

        return $result['values'];
    }

    /**
     * Performs aggregation using MongoDB "group" command.
     * @param string $collectionName collection name.
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
     */
    public function group($collectionName, $keys, $initial, $reduce, $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->group($collectionName, $keys, $initial, $reduce, $options);
        $cursor = $this->execute();

        $result = current($cursor->toArray());

        return $result['retval'];
    }

    /**
     * Performs MongoDB "map-reduce" command.
     * @param string $collectionName collection name.
     * @param \MongoDB\BSON\Javascript|string $map function, which emits map data from collection.
     * Argument will be automatically cast to [[\MongoDB\BSON\Javascript]].
     * @param \MongoDB\BSON\Javascript|string $reduce function that takes two arguments (the map key
     * and the map values) and does the aggregation.
     * Argument will be automatically cast to [[\MongoDB\BSON\Javascript]].
     * @param string|array $out output collection name. It could be a string for simple output
     * ('outputCollection'), or an array for parametrized output (['merge' => 'outputCollection']).
     * You can pass ['inline' => true] to fetch the result at once without temporary collection usage.
     * @param array $condition filter condition for including a document in the aggregation.
     * @param array $options additional optional parameters to the mapReduce command. Valid options include:
     *
     *  - sort: array, key to sort the input documents. The sort key must be in an existing index for this collection.
     *  - limit: int, the maximum number of documents to return in the collection.
     *  - finalize: \MongoDB\BSON\Javascript|string, function, which follows the reduce method and modifies the output.
     *  - scope: array, specifies global variables that are accessible in the map, reduce and finalize functions.
     *  - jsMode: bool, specifies whether to convert intermediate data into BSON format between the execution of the map and reduce functions.
     *  - verbose: bool, specifies whether to include the timing information in the result information.
     *
     * @return string|array the map reduce output collection name or output results.
     */
    public function mapReduce($collectionName, $map, $reduce, $out, $condition = [], $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->mapReduce($collectionName, $map, $reduce, $out, $condition, $options);
        $cursor = $this->execute();

        $result = current($cursor->toArray());

        return array_key_exists('results', $result) ? $result['results'] : $result['result'];
    }

    /**
     * Performs aggregation using MongoDB Aggregation Framework.
     * In case 'cursor' option is specified [[\MongoDB\Driver\Cursor]] instance is returned,
     * otherwise - an array of aggregation results.
     * @param string $collectionName collection name
     * @param array $pipelines list of pipeline operators.
     * @param array $options optional parameters.
     * @return array|\MongoDB\Driver\Cursor aggregation result.
     */
    public function aggregate($collectionName, $pipelines, $options = [])
    {
        if (empty($options['cursor'])) {
            $returnCursor = false;
            $options['cursor'] = new \stdClass();
        } else {
            $returnCursor = true;
        }

        $this->document = $this->db->getQueryBuilder()->aggregate($collectionName, $pipelines, $options);
        $cursor = $this->execute();

        if ($returnCursor) {
            return $cursor;
        }

        return $cursor->toArray();
    }

    /**
     * Return an explanation of the query, often useful for optimization and debugging.
     * @param string $collectionName collection name
     * @param array $query query document.
     * @return array explanation of the query.
     */
    public function explain($collectionName, $query)
    {
        $this->document = $this->db->getQueryBuilder()->explain($collectionName, $query);
        $cursor = $this->execute();

        return current($cursor->toArray());
    }

    /**
     * Returns the list of available databases.
     * @param array $condition filter condition.
     * @param array $options options list.
     * @return array database information
     */
    public function listDatabases($condition = [], $options = [])
    {
        if ($this->databaseName === null) {
            $this->databaseName = 'admin';
        }
        $this->document = $this->db->getQueryBuilder()->listDatabases($condition, $options);

        $cursor = $this->execute();
        $result = current($cursor->toArray());

        if (empty($result['databases'])) {
            return [];
        }
        return $result['databases'];
    }

    /**
     * Returns the list of available collections.
     * @param array $condition filter condition.
     * @param array $options options list.
     * @return array collections information.
     */
    public function listCollections($condition = [], $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->listCollections($condition, $options);
        $cursor = $this->execute();

        return $cursor->toArray();
    }

    // Logging :

    /**
     * Logs the command data if logging is enabled at [[db]].
     * @param array|string $namespace command namespace.
     * @param array $data command data.
     * @param string $category log category
     * @return string|false log token, `false` if log is not enabled.
     */
    protected function log($namespace, $data, $category)
    {
        if ($this->db->enableLogging) {
            $token = $this->db->getLogBuilder()->generateToken($namespace, $data);
            Yii::info($token, $category);
            return $token;
        }
        return false;
    }

    /**
     * Marks the beginning of a code block for profiling.
     * @param string $token token for the code block
     * @param string $category the category of this log message
     * @see endProfile()
     */
    protected function beginProfile($token, $category)
    {
        if ($token !== false && $this->db->enableProfiling) {
            Yii::beginProfile($token, $category);
        }
    }

    /**
     * Marks the end of a code block for profiling.
     * @param string $token token for the code block
     * @param string $category the category of this log message
     * @see beginProfile()
     */
    protected function endProfile($token, $category)
    {
        if ($token !== false && $this->db->enableProfiling) {
            Yii::endProfile($token, $category);
        }
    }
}
