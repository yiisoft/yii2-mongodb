<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use MongoDB\Driver\Manager;
use yii\base\Component;
use yii\base\InvalidConfigException;
use Yii;

/**
 * Connection represents a connection to a MongoDb server.
 *
 * Connection works together with [[Database]] and [[Collection]] to provide data access
 * to the Mongo database. They are wrappers of the [[MongoDB PHP extension]](http://us1.php.net/manual/en/book.mongo.php).
 *
 * To establish a DB connection, set [[dsn]] and then call [[open()]] to be true.
 *
 * The following example shows how to create a Connection instance and establish
 * the DB connection:
 *
 * ```php
 * $connection = new \yii\mongodb\Connection([
 *     'dsn' => $dsn,
 * ]);
 * $connection->open();
 * ```
 *
 * After the Mongo connection is established, one can access Mongo databases and collections:
 *
 * ```php
 * $database = $connection->getDatabase('my_mongo_db');
 * $collection = $database->getCollection('customer');
 * $collection->insert(['name' => 'John Smith', 'status' => 1]);
 * ```
 *
 * You can work with several different databases at the same server using this class.
 * However, while it is unlikely your application will actually need it, the Connection class
 * provides ability to use [[defaultDatabaseName]] as well as a shortcut method [[getCollection()]]
 * to retrieve a particular collection instance:
 *
 * ```php
 * // get collection 'customer' from default database:
 * $collection = $connection->getCollection('customer');
 * // get collection 'customer' from database 'mydatabase':
 * $collection = $connection->getCollection(['mydatabase', 'customer']);
 * ```
 *
 * Connection is often used as an application component and configured in the application
 * configuration like the following:
 *
 * ```php
 * [
 *      'components' => [
 *          'mongodb' => [
 *              'class' => '\yii\mongodb\Connection',
 *              'dsn' => 'mongodb://developer:password@localhost:27017/mydatabase',
 *          ],
 *      ],
 * ]
 * ```
 *
 * @property string $defaultDatabaseName name of the MongoDB database to use by default.
 * @property QueryBuilder $queryBuilder the query builder for the current MongoDB connection.
 * @property LogBuilder $logBuilder the log builder for the current MongoDB connection.
 * @property Database $database Database instance. This property is read-only.
 * @property file\Collection $fileCollection Mongo GridFS collection instance. This property is read-only.
 * @property boolean $isActive Whether the Mongo connection is established. This property is read-only.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Connection extends Component
{
    /**
     * @event Event an event that is triggered after a DB connection is established
     */
    const EVENT_AFTER_OPEN = 'afterOpen';

    /**
     * @var string host:port
     *
     * Correct syntax is:
     * mongodb://[username:password@]host1[:port1][,host2[:port2:],...][/dbname]
     * For example:
     * mongodb://localhost:27017
     * mongodb://developer:password@localhost:27017
     * mongodb://developer:password@localhost:27017/mydatabase
     */
    public $dsn;
    /**
     * @var array connection options.
     * For example:
     *
     * ```php
     * [
     *     'socketTimeoutMS' => 1000, // how long a send or receive on a socket can take before timing out
     *     'journal' => true // block write operations until the journal be flushed the to disk
     * ]
     * ```
     *
     * @see http://php.net/manual/en/mongodb-driver-manager.construct.php
     */
    public $options = [];
    /**
     * @var array options for the MongoDB driver.
     *
     * @see http://php.net/manual/en/mongodb-driver-manager.construct.php
     */
    public $driverOptions = [];
    /**
     * @var Manager MongoDB driver manager
     * @since 2.1
     */
    public $manager;
    /**
     * @var array type map to use for BSON unserialization.
     * Note: default type map will be automatically merged into this field, possibly overriding user-defined values.
     * @see http://php.net/manual/en/mongodb-driver-cursor.settypemap.php
     * @since 2.1
     */
    public $typeMap = [];
    /**
     * @var boolean whether to log command and query executions.
     * While enabled this option may reduce performance, since MongoDB commands may contain large data,
     * consuming both CPU and memory.
     * It make sense to disable this option at production environment.
     * @since 2.1
     */
    public $enableLogging = true;
    /**
     * @var boolean whether to enable profiling the commands and queries being executed.
     * This option will have no effect in case [[enableLogging]] is disabled.
     * @since 2.1
     */
    public $enableProfiling = true;

    /**
     * @var string name of the MongoDB database to use by default.
     * If this field left blank, connection instance will attempt to determine it from
     * [[dsn]] automatically, if needed.
     */
    private $_defaultDatabaseName;
    /**
     * @var Database[] list of Mongo databases
     */
    private $_databases = [];
    /**
     * @var QueryBuilder|array|string the query builder for this connection
     */
    private $_queryBuilder = 'yii\mongodb\QueryBuilder';
    /**
     * @var LogBuilder|array|string log entries builder used for this connecton.
     */
    private $_logBuilder = 'yii\mongodb\LogBuilder';


    /**
     * @param string $name default database name
     */
    public function setDefaultDatabaseName($name)
    {
        $this->_defaultDatabaseName = $name;
    }

    /**
     * Returns default database name, if it is not set,
     * attempts to determine it from [[dsn]] value.
     * @return string default database name
     * @throws \yii\base\InvalidConfigException if unable to determine default database name.
     */
    public function getDefaultDatabaseName()
    {
        if ($this->_defaultDatabaseName === null) {
            if (preg_match('/^mongodb:\\/\\/.+\\/([^?&]+)/s', $this->dsn, $matches)) {
                $this->_defaultDatabaseName = $matches[1];
            } else {
                throw new InvalidConfigException("Unable to determine default database name from dsn.");
            }
        }

        return $this->_defaultDatabaseName;
    }

    /**
     * Returns the query builder for the this MongoDB connection.
     * @return QueryBuilder the query builder for the this MongoDB connection.
     * @since 2.1
     */
    public function getQueryBuilder()
    {
        if (!is_object($this->_queryBuilder)) {
            $this->_queryBuilder = Yii::createObject($this->_queryBuilder, [$this]);
        }
        return $this->_queryBuilder;
    }

    /**
     * Sets the query builder for the this MongoDB connection.
     * @param QueryBuilder|array|string|null $queryBuilder the query builder for this MongoDB connection.
     * @since 2.1
     */
    public function setQueryBuilder($queryBuilder)
    {
        $this->_queryBuilder = $queryBuilder;
    }

    /**
     * @return LogBuilder the log builder for this connection.
     * @since 2.1
     */
    public function getLogBuilder()
    {
        if (!is_object($this->_logBuilder)) {
            $this->_logBuilder = Yii::createObject($this->_logBuilder);
        }
        return $this->_logBuilder;
    }

    /**
     * @param array|string|LogBuilder $logBuilder the log builder for this connection.
     * @since 2.1
     */
    public function setLogBuilder($logBuilder)
    {
        $this->_logBuilder = $logBuilder;
    }

    /**
     * Returns the Mongo collection with the given name.
     * @param string|null $name collection name, if null default one will be used.
     * @param boolean $refresh whether to reestablish the database connection even if it is found in the cache.
     * @return Database database instance.
     */
    public function getDatabase($name = null, $refresh = false)
    {
        if ($name === null) {
            $name = $this->getDefaultDatabaseName();
        }
        if ($refresh || !array_key_exists($name, $this->_databases)) {
            $this->_databases[$name] = $this->selectDatabase($name);
        }

        return $this->_databases[$name];
    }

    /**
     * Selects the database with given name.
     * @param string $name database name.
     * @return Database database instance.
     */
    protected function selectDatabase($name)
    {
        $this->open();

        return Yii::createObject([
            'class' => 'yii\mongodb\Database',
            'name' => $name,
            'connection' => $this,
        ]);
    }

    /**
     * Returns the Mongo collection with the given name.
     * @param string|array $name collection name. If string considered as the name of the collection
     * inside the default database. If array - first element considered as the name of the database,
     * second - as name of collection inside that database
     * @param boolean $refresh whether to reload the collection instance even if it is found in the cache.
     * @return Collection Mongo collection instance.
     */
    public function getCollection($name, $refresh = false)
    {
        if (is_array($name)) {
            list ($dbName, $collectionName) = $name;

            return $this->getDatabase($dbName)->getCollection($collectionName, $refresh);
        } else {
            return $this->getDatabase()->getCollection($name, $refresh);
        }
    }

    /**
     * Returns the Mongo GridFS collection.
     * @param string|array $prefix collection prefix. If string considered as the prefix of the GridFS
     * collection inside the default database. If array - first element considered as the name of the database,
     * second - as prefix of the GridFS collection inside that database, if no second element present
     * default "fs" prefix will be used.
     * @param boolean $refresh whether to reload the collection instance even if it is found in the cache.
     * @return file\Collection Mongo GridFS collection instance.
     */
    public function getFileCollection($prefix = 'fs', $refresh = false)
    {
        if (is_array($prefix)) {
            list ($dbName, $collectionPrefix) = $prefix;
            if (!isset($collectionPrefix)) {
                $collectionPrefix = 'fs';
            }

            return $this->getDatabase($dbName)->getFileCollection($collectionPrefix, $refresh);
        } else {
            return $this->getDatabase()->getFileCollection($prefix, $refresh);
        }
    }

    /**
     * Returns a value indicating whether the Mongo connection is established.
     * @return boolean whether the Mongo connection is established
     */
    public function getIsActive()
    {
        return is_object($this->manager) && $this->manager->getServers() !== [];
    }

    /**
     * Establishes a Mongo connection.
     * It does nothing if a Mongo connection has already been established.
     * @throws Exception if connection fails
     */
    public function open()
    {
        if ($this->manager === null) {
            if (empty($this->dsn)) {
                throw new InvalidConfigException($this->className() . '::dsn cannot be empty.');
            }
            $token = 'Opening MongoDB connection: ' . $this->dsn;
            try {
                Yii::trace($token, __METHOD__);
                Yii::beginProfile($token, __METHOD__);
                $options = $this->options;

                $this->manager = new Manager($this->dsn, $options, $this->driverOptions);
                $this->manager->selectServer($this->manager->getReadPreference());

                $this->initConnection();
                Yii::endProfile($token, __METHOD__);
            } catch (\Exception $e) {
                Yii::endProfile($token, __METHOD__);
                throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
            }

            $this->typeMap = array_merge(
                $this->typeMap,
                [
                    'root' => 'array',
                    'document' => 'array'
                ]
            );
        }
    }

    /**
     * Closes the currently active DB connection.
     * It does nothing if the connection is already closed.
     */
    public function close()
    {
        if ($this->manager !== null) {
            Yii::trace('Closing MongoDB connection: ' . $this->dsn, __METHOD__);
            $this->manager = null;
            foreach ($this->_databases as $database) {
                $database->clearCollections();
            }
            $this->_databases = [];
        }
    }

    /**
     * Initializes the DB connection.
     * This method is invoked right after the DB connection is established.
     * The default implementation triggers an [[EVENT_AFTER_OPEN]] event.
     */
    protected function initConnection()
    {
        $this->trigger(self::EVENT_AFTER_OPEN);
    }

    /**
     * Creates MongoDB command.
     * @param string|null $databaseName database name, if not set [[defaultDatabaseName]] will be used.
     * @param array $document command document contents.
     * @return Command command instance.
     * @since 2.1
     */
    public function createCommand($databaseName = null, $document = [])
    {
        return new Command([
            'db' => $this,
            'databaseName' => $databaseName,
            'document' => $document,
        ]);
    }
}
