<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use MongoDB\Driver\ReadPreference;
use yii\base\Object;

/**
 * Command represents MongoDB command
 *
 * @property ReadPreference|integer|string|null $readPreference
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
     * @var ReadPreference|integer|string|null command read preference
     */
    private $_readPreference;


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
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        return $cursor;
    }

    /**
     * @param string $collectionName
     * @param array $options
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
}