<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use yii\base\BaseObject;
use Yii;

/**
 * Database represents the MongoDB database information.
 *
 * @property file\Collection $fileCollection Mongo GridFS collection. This property is read-only.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Database extends BaseObject
{
    /**
     * @var Connection MongoDB connection.
     */
    public $connection;
    /**
     * @var string name of this database.
     */
    public $name;

    /**
     * @var Collection[] list of collections.
     */
    private $_collections = [];
    /**
     * @var file\Collection[] list of GridFS collections.
     */
    private $_fileCollections = [];


    /**
     * Returns the Mongo collection with the given name.
     * @param string $name collection name
     * @param bool $refresh whether to reload the collection instance even if it is found in the cache.
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
     * @param bool $refresh whether to reload the collection instance even if it is found in the cache.
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
        return Yii::createObject([
            'class' => 'yii\mongodb\Collection',
            'database' => $this,
            'name' => $name,
        ]);
    }

    /**
     * Selects GridFS collection with given prefix.
     * @param string $prefix file collection prefix.
     * @return file\Collection file collection instance.
     */
    protected function selectFileCollection($prefix)
    {
        return Yii::createObject([
            'class' => 'yii\mongodb\file\Collection',
            'database' => $this,
            'prefix' => $prefix,
        ]);
    }

    /**
     * Creates MongoDB command associated with this database.
     * @param array $document command document contents.
     * @return Command command instance.
     * @since 2.1
     */
    public function createCommand($document = [])
    {
        return $this->connection->createCommand($document, $this->name);
    }

    /**
     * Creates new collection.
     * Note: Mongo creates new collections automatically on the first demand,
     * this method makes sense only for the migration script or for the case
     * you need to create collection with the specific options.
     * @param string $name name of the collection
     * @param array $options collection options in format: "name" => "value"
     * @return bool whether operation was successful.
     * @throws Exception on failure.
     */
    public function createCollection($name, $options = [])
    {
        return $this->createCommand()->createCollection($name, $options);
    }

    /**
     * Drops specified collection.
     * @param string $name name of the collection
     * @return bool whether operation was successful.
     * @since 2.1
     */
    public function dropCollection($name)
    {
        return $this->createCommand()->dropCollection($name);
    }

    /**
     * Returns the list of available collections in this database.
     * @param array $condition filter condition.
     * @param array $options options list.
     * @return array collections information.
     * @since 2.1.1
     */
    public function listCollections($condition = [], $options = [])
    {
        return $this->createCommand()->listCollections($condition, $options);
    }

    /**
     * Clears internal collection lists.
     * This method can be used to break cycle references between [[Database]] and [[Collection]] instances.
     */
    public function clearCollections()
    {
        $this->_collections = [];
        $this->_fileCollections = [];
    }
}
