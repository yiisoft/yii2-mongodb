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
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\WriteResult;
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
     * Executes this command.
     * @return \MongoDB\Driver\Cursor result cursor
     * @throws Exception on failure
     */
    public function execute()
    {
        $databaseName = $this->databaseName === null ? $this->db->defaultDatabaseName : $this->databaseName;

        try {
            $server = $this->db->manager->selectServer($this->getReadPreference());
            $mongoCommand = new \MongoDB\Driver\Command($this->document);
            $cursor = $server->executeCommand($databaseName, $mongoCommand);
        } catch (RuntimeException $e) {
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
     */
    public function executeBatch($collectionName, $options = [])
    {
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

        $databaseName = $this->databaseName === null ? $this->db->defaultDatabaseName : $this->databaseName;

        try {
            $server = $this->db->manager->selectServer($this->getReadPreference());
            $writeResult = $server->executeBulkWrite($databaseName . '.' . $collectionName, $batch, $this->getWriteConcern());
        } catch (RuntimeException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        return [
            'insertedIds' => $insertedIds,
            'result' => $writeResult,
        ];
    }

    /**
     * Drops database associated with this command.
     * @return boolean whether operation was successful.
     */
    public function dropDatabase()
    {
        $this->document = $this->db->getQueryBuilder()->dropDatabase();
        $result = current($this->execute()->toArray());
        return $result->ok > 0;
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
        return $result->ok > 0;
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
        return $result->ok > 0;
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
        return $result->ok > 0;
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

        $cursor->setTypeMap($this->db->cursorTypeMap);

        return $cursor->toArray();
    }

    /**
     * @param string $collectionName
     * @param array $condition
     * @param array $options
     * @return integer records count
     */
    public function count($collectionName, $condition = [], $options = [])
    {
        $this->document = $this->db->getQueryBuilder()->count($collectionName, $condition, $options);

        $result = current($this->execute()->toArray());
        return $result->n;
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
                'multi' => false,
                'upsert' => false,
            ],
            $options
        );

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
}