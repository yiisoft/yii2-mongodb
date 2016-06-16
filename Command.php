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
use yii\base\Object;

/**
 * Command represents MongoDB command
 *
 * @property ReadPreference|integer|string|null $readPreference command read preference.
 * @param WriteConcern|integer|string|null $writeConcern write concern to be used by this command.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class Command extends Object
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
     * @var ReadPreference|integer|string|null command read preference.
     */
    private $_readPreference;
    /**
     * @var WriteConcern|integer|string|null write concern to be used by this command.
     */
    private $_writeConcern;
    /**
     * @var ReadConcern|string read concern to be used by this command
     */
    private $_readConcern;


    /**
     * @return ReadPreference
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
     * @param ReadPreference|integer|string|null $readPreference
     * @return $this self reference
     */
    public function setReadPreference($readPreference)
    {
        $this->_readPreference = $readPreference;
        return $this;
    }

    /**
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
     * @param WriteConcern|integer|string|null $writeConcern
     * @return $this self reference
     */
    public function setWriteConcern($writeConcern)
    {
        $this->_writeConcern = $writeConcern;
        return $this;
    }

    /**
     * @return ReadConcern|string
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
     * @param ReadConcern|string $readConcern
     * @return $this self reference
     */
    public function setReadConcern($readConcern)
    {
        $this->_readConcern = $readConcern;
        return $this;
    }

    /**
     * Executes this command.
     * @return \MongoDB\Driver\Cursor result cursor
     * @throws Exception on failure
     */
    public function execute()
    {
        $databaseName = $this->databaseName === null ? $this->db->defaultDatabaseName : $this->databaseName;

        $token = $this->log([$databaseName, 'command'], $this->document, __METHOD__);

        try {
            $this->beginProfile($token, __METHOD__);

            $this->db->open();
            $server = $this->db->manager->selectServer($this->getReadPreference());
            $mongoCommand = new \MongoDB\Driver\Command($this->document);
            $cursor = $server->executeCommand($databaseName, $mongoCommand);
            $cursor->setTypeMap($this->db->typeMap);

            $this->endProfile($token, __METHOD__);
        } catch (RuntimeException $e) {
            $this->endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        return $cursor;
    }

    /**
     * @param string $collectionName collection name
     * @param array $options batch options
     * @return array
     * @throws Exception on failure.
     * @throws InvalidConfigException on invalid [[document]] format.
     * @throws Exception on failure
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
            $server = $this->db->manager->selectServer($this->getReadPreference());
            $writeResult = $server->executeBulkWrite($databaseName . '.' . $collectionName, $batch, $this->getWriteConcern());

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
            $server = $this->db->manager->selectServer($this->getReadPreference());
            $cursor = $server->executeQuery($databaseName . '.' . $collectionName, $query);
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
     * @return boolean whether operation was successful.
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
     * @param array $options collection options.
     * @return boolean whether operation was successful.
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
     * @return boolean whether operation was successful.
     */
    public function dropCollection($collectionName)
    {
        $this->document = $this->db->getQueryBuilder()->dropCollection($collectionName);

        $result = current($this->execute()->toArray());
        return $result['ok'] > 0;
    }

    /**
     * Creates indexes in the collection.
     * @param string $collectionName.
     * @param array $indexes indexes specifications.
     * @return boolean whether operation was successful.
     */
    public function createIndexes($collectionName, $indexes)
    {
        $this->document = $this->db->getQueryBuilder()->createIndexes($this->databaseName, $collectionName, $indexes);

        $result = current($this->execute()->toArray());
        return $result['ok'] > 0;
    }

    /**
     * @param string $collectionName
     * @param string $indexes
     * @return \stdClass result data.
     */
    public function dropIndexes($collectionName, $indexes)
    {
        $this->document = $this->db->getQueryBuilder()->dropIndexes($collectionName, $indexes);

        return current($this->execute()->toArray());
    }

    /**
     * @param string $collectionName
     * @param array $options
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
     * @param array $options options/
     * @return integer records count
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
                'upsert' => true,
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
     * @param array $options
     * @return ObjectID|boolean inserted record ID, `false` - on failure.
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
     * @param array $options
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
            if ($options['limit'] === null) {
                unset($options['limit']);
            } else {
                $options['limit'] = (int)$options['limit'];
            }
        }
        if (array_key_exists('skip', $options)) {
            if ($options['skip'] === null) {
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

    public function mapReduce($collectionName, $map, $reduce, $out, $condition = [], $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->mapReduce($collectionName, $map, $reduce, $out, $condition, $options);
        $cursor = $this->execute();

        $result = current($cursor->toArray());

        return array_key_exists('results', $result) ? $result['results'] : $result['result'];
    }

    /**
     * Performs aggregation using MongoDB Aggregation Framework.
     * @param string $collectionName collection name
     * @param array $pipelines list of pipeline operators.
     * @param array $options optional parameters.
     * @return array aggregation result.
     */
    public function aggregate($collectionName, $pipelines, $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->aggregate($collectionName, $pipelines, $options);
        $cursor = $this->execute();

        $result = current($cursor->toArray());

        return $result['result'];
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

    // Logging :

    /**
     * Logs the command data if logging is enabled at [[db]]
     * @param array|string $namespace command namespace
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