<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use yii\base\InvalidCallException;
use yii\base\Object;
use Yii;
use yii\helpers\Json;

/**
 * Database represents the Mongo database information.
 *
 * @property file\Collection $fileCollection Mongo GridFS collection. This property is read-only.
 * @property string $name Name of this database. This property is read-only.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Database extends Object
{
    /**
     * @var \MongoDB\Driver\Manager Mongo manager instance.
     */
    public $mongoManager;

    /**
     * @var \MongoDB\Database Mongo database instance.
     */
    public $mongoDb;

    /**
     * @var string Database name
     */
    public $dbName;

    /**
     * @var Collection[] list of collections.
     */
    private $_collections = [];
    /**
     * @var file\Collection[] list of GridFS collections.
     */
    private $_fileCollections = [];


    /**
     * @return string name of this database.
     */
    public function getName()
    {
        return $this->dbName;
    }

    /**
     * Returns the Mongo collection with the given name.
     * @param string $name collection name
     * @param boolean $refresh whether to reload the collection instance even if it is found in the cache.
     * @return Collection Mongo collection instance.
     */
    public function getCollection($name, $refresh = false)
    {
        if ($refresh || !array_key_exists($name, $this->_collections)) {
            $this->_collections[$name] = $this->selectCollection($name);
        }

        return $this->_collections[$name];
    }

    /**
     * Returns Mongo GridFS collection with given prefix.
     * @param string $prefix collection prefix.
     * @param boolean $refresh whether to reload the collection instance even if it is found in the cache.
     * @return file\Collection Mongo GridFS collection.
     */
    public function getFileCollection($prefix = 'fs', $refresh = false)
    {
        if ($refresh || !array_key_exists($prefix, $this->_fileCollections)) {
            $this->_fileCollections[$prefix] = $this->selectFileCollection($prefix);
        }

        return $this->_fileCollections[$prefix];
    }

    /**
     * Selects collection with given name.
     * @param string $name collection name.
     * @return Collection collection instance.
     */
    protected function selectCollection($name)
    {
        // Wrapper of original library collection
        $collection = new \yii\mongodb\library\Collection($this->mongoManager, $this->dbName, $name);

        return Yii::createObject([
            'class' => 'yii\mongodb\Collection',
            'dbName' => $this->dbName,
            'collectionName' => $name,
            'mongoManager' => $this->mongoManager,
            'mongoCollection' => $collection
        ]);
    }

    /**
     * Selects GridFS collection with given prefix.
     * @param string $prefix file collection prefix.
     * @return file\Collection file collection instance.
     */
    protected function selectFileCollection($prefix)
    {
        $collection = new \yii\mongodb\library\Collection($this->mongoManager, $this->dbName, $prefix);

        return Yii::createObject([
            'class' => 'yii\mongodb\file\Collection',
            'dbName' => $this->dbName,
            'collectionPrefix' => $prefix,
            'mongoManager' => $this->mongoManager,
            'mongoCollection' => $collection
        ]);
    }

    /**
     * Creates new collection.
     * Note: Mongo creates new collections automatically on the first demand,
     * this method makes sense only for the migration script or for the case
     * you need to create collection with the specific options.
     * @param string $name name of the collection
     * @param array $options collection options in format: "name" => "value"
     * @return \MongoCollection new Mongo collection instance.
     * @throws Exception on failure.
     */
    public function createCollection($name, $options = [])
    {
        $token = $this->getName() . '.create(' . $name . ', ' . Json::encode($options) . ')';
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $result = $this->mongoDb->createCollection($name, $options);
            Yii::endProfile($token, __METHOD__);

            return $result;
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Executes Mongo command.
     * @param array $command command specification.
     * @param array $options options in format: "name" => "value"
     * @return array database response.
     * @throws Exception on failure.
     */
    public function executeCommand($command, $options = [])
    {
        $token = $this->getName() . '.$cmd(' . Json::encode($command) . ', ' . Json::encode($options) . ')';
        Yii::info($token, __METHOD__);
        try {
            Yii::beginProfile($token, __METHOD__);
            $command = new \MongoDB\Driver\Command($command);
            $result = $this->mongoManager->executeCommand($this->dbName, $command);
            $this->tryResultError($result);
            Yii::endProfile($token, __METHOD__);

            return MongoHelper::cursorFirst($result);
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
                if (array_key_exists('ok', $result)) {
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
}
