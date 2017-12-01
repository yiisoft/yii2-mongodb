<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use yii\base\Component;
use yii\db\MigrationInterface;
use yii\di\Instance;
use yii\helpers\Json;

/**
 * Migration is the base class for representing a MongoDB migration.
 *
 * Each child class of Migration represents an individual MongoDB migration which
 * is identified by the child class name.
 *
 * Within each migration, the [[up()]] method should be overridden to contain the logic
 * for "upgrading" the database; while the [[down()]] method for the "downgrading"
 * logic.
 *
 * Migration provides a set of convenient methods for manipulating MongoDB data and schema.
 * For example, the [[createIndex()]] method can be used to create a collection index.
 * Compared with the same methods in [[Collection]], these methods will display extra
 * information showing the method parameters and execution time, which may be useful when
 * applying migrations.
 *
 * @author Klimov Paul <klimov@zfort.com>
 * @since 2.0
 */
abstract class Migration extends Component implements MigrationInterface
{
    /**
     * @var Connection|array|string the MongoDB connection object or the application component ID of the MongoDB connection
     * that this migration should work with.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db = 'mongodb';
    /**
     * @var bool indicates whether the log output should be compacted.
     * If this is set to true, the individual commands ran within the migration will not be output to the console log.
     * Default is `false`, in other words the output is fully verbose by default.
     * @since 2.1.5
     */
    public $compact = false;


    /**
     * Initializes the migration.
     * This method will set [[db]] to be the 'db' application component, if it is null.
     */
    public function init()
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::className());
    }

    /**
     * Creates new collection with the specified options.
     * @param string|array $collection name of the collection
     * @param array $options collection options in format: "name" => "value"
     */
    public function createCollection($collection, $options = [])
    {
        if (is_array($collection)) {
            list($database, $collectionName) = $collection;
        } else {
            $database = null;
            $collectionName = $collection;
        }
        $this->beginProfile($token = "    > create collection " . $this->composeCollectionLogName($collection) . " ...");
        $this->db->getDatabase($database)->createCollection($collectionName, $options);
        $this->endProfile($token);
    }

    /**
     * Drops existing collection.
     * @param string|array $collection name of the collection
     */
    public function dropCollection($collection)
    {
        $this->beginProfile($token = "    > drop collection " . $this->composeCollectionLogName($collection) . " ...");
        $this->db->getCollection($collection)->drop();
        $this->endProfile($token);
    }

    /**
     * Creates indexes in the collection.
     * @param string|array $collection name of the collection
     * @param array $indexes indexes specifications.
     * @since 2.1
     */
    public function createIndexes($collection, $indexes)
    {
        $this->beginProfile($token = "    > create indexes on " . $this->composeCollectionLogName($collection) . " (" . Json::encode($indexes) . ") ...");
        $this->db->getCollection($collection)->createIndexes($indexes);
        $this->endProfile($token);
    }

    /**
     * Drops collection indexes by name.
     * @param string|array $collection name of the collection
     * @param string $indexes wildcard for name of the indexes to be dropped.
     * @since 2.1
     */
    public function dropIndexes($collection, $indexes)
    {
        $this->beginProfile($token = "    > drop indexes '{$indexes}' on " . $this->composeCollectionLogName($collection) . ") ...");
        $this->db->getCollection($collection)->dropIndexes($indexes);
        $this->endProfile($token);
    }

    /**
     * Creates an index on the collection and the specified fields.
     * @param string|array $collection name of the collection
     * @param array|string $columns column name or list of column names.
     * @param array $options list of options in format: optionName => optionValue.
     */
    public function createIndex($collection, $columns, $options = [])
    {
        $this->beginProfile($token = "    > create index on " . $this->composeCollectionLogName($collection) . " (" . Json::encode((array) $columns) . empty($options) ? "" : ", " . Json::encode($options) . ") ...");
        $this->db->getCollection($collection)->createIndex($columns, $options);
        $this->endProfile($token);
    }

    /**
     * Drop indexes for specified column(s).
     * @param string|array $collection name of the collection
     * @param string|array $columns column name or list of column names.
     */
    public function dropIndex($collection, $columns)
    {
        $this->beginProfile($token = "    > drop index on " . $this->composeCollectionLogName($collection) . " (" . Json::encode((array) $columns) . ") ...");
        $this->db->getCollection($collection)->dropIndex($columns);
        $this->endProfile($token);
    }

    /**
     * Drops all indexes for specified collection.
     * @param string|array $collection name of the collection.
     */
    public function dropAllIndexes($collection)
    {
        $this->beginProfile($token = "    > drop all indexes on " . $this->composeCollectionLogName($collection) . ") ...");
        $this->db->getCollection($collection)->dropAllIndexes();
        $this->endProfile($token);
    }

    /**
     * Inserts new data into collection.
     * @param array|string $collection collection name.
     * @param array|object $data data to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoDB\BSON\ObjectID new record id instance.
     */
    public function insert($collection, $data, $options = [])
    {
        $this->beginProfile($token = "    > insert into " . $this->composeCollectionLogName($collection) . ") ...");
        $id = $this->db->getCollection($collection)->insert($data, $options);
        $this->endProfile($token);
        return $id;
    }

    /**
     * Inserts several new rows into collection.
     * @param array|string $collection collection name.
     * @param array $rows array of arrays or objects to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return array inserted data, each row will have "_id" key assigned to it.
     */
    public function batchInsert($collection, $rows, $options = [])
    {
        $this->beginProfile($token = "    > insert into " . $this->composeCollectionLogName($collection) . ") ...");
        $rows = $this->db->getCollection($collection)->batchInsert($rows, $options);
        $this->endProfile($token);
        return $rows;
    }

    /**
     * Updates the rows, which matches given criteria by given data.
     * Note: for "multiple" mode Mongo requires explicit strategy "$set" or "$inc"
     * to be specified for the "newData". If no strategy is passed "$set" will be used.
     * @param array|string $collection collection name.
     * @param array $condition description of the objects to update.
     * @param array $newData the object with which to update the matching records.
     * @param array $options list of options in format: optionName => optionValue.
     * @return int|bool number of updated documents or whether operation was successful.
     */
    public function update($collection, $condition, $newData, $options = [])
    {
        $this->beginProfile($token = "    > update " . $this->composeCollectionLogName($collection) . ") ...");
        $result = $this->db->getCollection($collection)->update($condition, $newData, $options);
        $this->endProfile($token);
        return $result;
    }

    /**
     * Update the existing database data, otherwise insert this data
     * @param array|string $collection collection name.
     * @param array|object $data data to be updated/inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoDB\BSON\ObjectID updated/new record id instance.
     */
    public function save($collection, $data, $options = [])
    {
        $this->beginProfile($token = "    > save " . $this->composeCollectionLogName($collection) . ") ...");
        $id = $this->db->getCollection($collection)->save($data, $options);
        $this->endProfile($token);
        return $id;
    }

    /**
     * Removes data from the collection.
     * @param array|string $collection collection name.
     * @param array $condition description of records to remove.
     * @param array $options list of options in format: optionName => optionValue.
     * @return int|bool number of updated documents or whether operation was successful.
     */
    public function remove($collection, $condition = [], $options = [])
    {
        $this->beginProfile($token = "    > remove " . $this->composeCollectionLogName($collection) . ") ...");
        $result = $this->db->getCollection($collection)->remove($condition, $options);
        $this->endProfile($token);
        return $result;
    }

    /**
     * Composes string representing collection name.
     * @param array|string $collection collection name.
     * @return string collection name.
     */
    protected function composeCollectionLogName($collection)
    {
        if (is_array($collection)) {
            list($database, $collection) = $collection;
            return $database . '.' . $collection;
        }
        return $collection;
    }

    /**
     * @var array opened profile tokens.
     * @since 2.1.1
     */
    private $profileTokens = [];

    /**
     * Logs the incoming message.
     * By default this method sends message to 'stdout'.
     * @param string $string message to be logged.
     * @since 2.1.1
     */
    protected function log($string)
    {
        echo $string;
    }

    /**
     * Marks the beginning of a code block for profiling.
     * @param string $token token for the code block.
     * @since 2.1.1
     */
    protected function beginProfile($token)
    {
        $this->profileTokens[$token] = microtime(true);

        if (!$this->compact) {
            $this->log($token);
        }
    }

    /**
     * Marks the end of a code block for profiling.
     * @param string $token token for the code block.
     * @since 2.1.1
     */
    protected function endProfile($token)
    {
        if (isset($this->profileTokens[$token])) {
            $time = microtime(true) - $this->profileTokens[$token];
            unset($this->profileTokens[$token]);
        } else {
            $time = 0;
        }

        if (!$this->compact) {
            $this->log(" done (time: " . sprintf('%.3f', $time) . "s)\n");
        }
    }
}