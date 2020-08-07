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
use yii\caching\CacheInterface;

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
 * @property Database $database Database instance. This property is read-only.
 * @property string $defaultDatabaseName Default database name.
 * @property file\Collection $fileCollection Mongo GridFS collection instance. This property is read-only.
 * @property bool $isActive Whether the Mongo connection is established. This property is read-only.
 * @property LogBuilder $logBuilder The log builder for this connection. Note that the type of this property
 * differs in getter and setter. See [[getLogBuilder()]] and [[setLogBuilder()]] for details.
 * @property QueryBuilder $queryBuilder The query builder for the this MongoDB connection. Note that the type
 * of this property differs in getter and setter. See [[getQueryBuilder()]] and [[setQueryBuilder()]] for
 * details.
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
     *     'ssl' => true // initiate the connection with TLS/SSL
     * ]
     * ```
     *
     * @see https://docs.mongodb.com/manual/reference/connection-string/#connections-connection-options
     */
    public $options = [];
    /**
     * @var array options for the MongoDB driver.
     * Any driver-specific options not included in MongoDB connection string specification.
     *
     * @see http://php.net/manual/en/mongodb-driver-manager.construct.php
     */
    public $driverOptions = [];
    /**
     * @var Manager MongoDB driver manager.
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
     * @var bool whether to log command and query executions.
     * When enabled this option may reduce performance. MongoDB commands may contain large data,
     * consuming both CPU and memory.
     * It makes sense to disable this option in the production environment.
     * @since 2.1
     */
    public $enableLogging = true;
    /**
     * @var bool whether to enable profiling the commands and queries being executed.
     * This option will have no effect in case [[enableLogging]] is disabled.
     * @since 2.1
     */
    public $enableProfiling = true;
    /**
     * @var string name of the protocol, which should be used for the GridFS stream wrapper.
     * Only alphanumeric values are allowed: do not use any URL special characters, such as '/', '&', ':' etc.
     * @see \yii\mongodb\file\StreamWrapper
     * @since 2.1
     */
    public $fileStreamProtocol = 'gridfs';
    /**
     * @var string name of the class, which should serve as a stream wrapper for [[fileStreamProtocol]] protocol.
     * @since 2.1
     */
    public $fileStreamWrapperClass = 'yii\mongodb\file\StreamWrapper';
    /**
     * @var bool whether to enable query caching.
     * Note that in order to enable query caching, a valid cache component as specified
     * by [[queryCache]] must be enabled and [[enableQueryCache]] must be set true.
     * Also, only the results of the queries enclosed within [[cache()]] will be cached.
     * @see queryCache
     * @see cache()
     * @see noCache()
     */
    public $enableQueryCache = true;
    /**
     * @var int the default number of seconds that query results can remain valid in cache.
     * Defaults to 3600, meaning 3600 seconds, or one hour. Use 0 to indicate that the cached data will never expire.
     * The value of this property will be used when [[cache()]] is called without a cache duration.
     * @see enableQueryCache
     * @see cache()
     */
    public $queryCacheDuration = 3600;
    /**
     * @var CacheInterface|string the cache object or the ID of the cache application component
     * that is used for query caching.
     * @see enableQueryCache
     */
    public $queryCache = 'cache';

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
     * @since 2.1
     */
    private $_queryBuilder = 'yii\mongodb\QueryBuilder';
    /**
     * @var LogBuilder|array|string log entries builder used for this connecton.
     * @since 2.1
     */
    private $_logBuilder = 'yii\mongodb\LogBuilder';
    /**
     * @var bool whether GridFS stream wrapper has been already registered.
     * @since 2.1
     */
    private $_fileStreamWrapperRegistered = false;
    /**
     * @var array query cache parameters for the [[cache()]] calls
     */
    private $_queryCacheInfo = [];


    /**
     * Sets default database name.
     * @param string $name default database name.
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
     * Returns log builder for this connection.
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
     * Sets log builder used for this connection.
     * @param array|string|LogBuilder $logBuilder the log builder for this connection.
     * @since 2.1
     */
    public function setLogBuilder($logBuilder)
    {
        $this->_logBuilder = $logBuilder;
    }

    /**
     * Returns the MongoDB database with the given name.
     * @param string|null $name database name, if null default one will be used.
     * @param bool $refresh whether to reestablish the database connection even, if it is found in the cache.
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
        return Yii::createObject([
            'class' => 'yii\mongodb\Database',
            'name' => $name,
            'connection' => $this,
        ]);
    }

    /**
     * Returns the MongoDB collection with the given name.
     * @param string|array $name collection name. If string considered as the name of the collection
     * inside the default database. If array - first element considered as the name of the database,
     * second - as name of collection inside that database
     * @param bool $refresh whether to reload the collection instance even if it is found in the cache.
     * @return Collection Mongo collection instance.
     */
    public function getCollection($name, $refresh = false)
    {
        if (is_array($name)) {
            list ($dbName, $collectionName) = $name;
            return $this->getDatabase($dbName)->getCollection($collectionName, $refresh);
        }
        return $this->getDatabase()->getCollection($name, $refresh);
    }

    /**
     * Returns the MongoDB GridFS collection.
     * @param string|array $prefix collection prefix. If string considered as the prefix of the GridFS
     * collection inside the default database. If array - first element considered as the name of the database,
     * second - as prefix of the GridFS collection inside that database, if no second element present
     * default "fs" prefix will be used.
     * @param bool $refresh whether to reload the collection instance even if it is found in the cache.
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
        }
        return $this->getDatabase()->getFileCollection($prefix, $refresh);
    }

    /**
     * Returns a value indicating whether the Mongo connection is established.
     * @return bool whether the Mongo connection is established
     */
    public function getIsActive()
    {
        return is_object($this->manager) && $this->manager->getServers() !== [];
    }

    /**
     * Establishes a Mongo connection.
     * It does nothing if a MongoDB connection has already been established.
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
                $this->enableProfiling and Yii::beginProfile($token, __METHOD__);
                $options = $this->options;

                $this->manager = new Manager($this->dsn, $options, $this->driverOptions);
                $this->manager->selectServer($this->manager->getReadPreference());

                $this->initConnection();
                $this->enableProfiling and Yii::endProfile($token, __METHOD__);
            } catch (\Exception $e) {
                $this->enableProfiling and Yii::endProfile($token, __METHOD__);
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
            $this->_queryCacheInfo = [];
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
     * @param array $document command document contents.
     * @param string|null $databaseName database name, if not set [[defaultDatabaseName]] will be used.
     * @return Command command instance.
     * @since 2.1
     */
    public function createCommand($document = [], $databaseName = null)
    {
        return new Command([
            'db' => $this,
            'databaseName' => $databaseName,
            'document' => $document,
        ]);
    }

    /**
     * Registers GridFS stream wrapper for the [[fileStreamProtocol]] protocol.
     * @param bool $force whether to enforce registration even wrapper has been already registered.
     * @return string registered stream protocol name.
     */
    public function registerFileStreamWrapper($force = false)
    {
        if ($force || !$this->_fileStreamWrapperRegistered) {
            /* @var $class \yii\mongodb\file\StreamWrapper */
            $class = $this->fileStreamWrapperClass;
            $class::register($this->fileStreamProtocol, $force);

            $this->_fileStreamWrapperRegistered = true;
        }

        return $this->fileStreamProtocol;
    }

    /**
     * Uses query cache for the queries performed with the callable.
     *
     * When query caching is enabled ([[enableQueryCache]] is true and [[queryCache]] refers to a valid cache),
     * queries performed within the callable will be cached and their results will be fetched from cache if available.
     * For example,
     *
     * ```php
     * // The customers will be fetched from cache if available.
     * // If not, the query will be made against DB and cached for use next time.
     * $customers = $db->cache(function (Connection $db) {
     *     return (new Query())->select(['name', 'status'])->from('customer')->limit(10)->all();
     * });
     * ```
     *
     * Note that query cache can only be used for queries that return results as array (executed by  \yii\mongodb\Query).
     *
     * @param callable $callable a PHP callable that contains DB queries which will make use of query cache.
     * The signature of the callable is `function (Connection $db)`.
     * @param int $duration the number of seconds that query results can remain valid in the cache. If this is
     * not set, the value of [[queryCacheDuration]] will be used instead.
     * Use 0 to indicate that the cached data will never expire.
     * @param \yii\caching\Dependency $dependency the cache dependency associated with the cached query results.
     * @return mixed the return result of the callable
     * @throws \Exception|\Throwable if there is any exception during query
     * @see enableQueryCache
     * @see queryCache
     * @see noCache()
     */
    public function cache(callable $callable, $duration = null, $dependency = null)
    {
        $this->_queryCacheInfo[] = [$duration === null ? $this->queryCacheDuration : $duration, $dependency];
        try {
            $result = call_user_func($callable, $this);
            array_pop($this->_queryCacheInfo);
            return $result;
        } catch (\Exception $e) {
            array_pop($this->_queryCacheInfo);
            throw $e;
        } catch (\Throwable $e) {
            array_pop($this->_queryCacheInfo);
            throw $e;
        }
    }
    
    /**
     * Disables query cache temporarily.
     *
     * Queries performed within the callable will not use query cache at all. For example,
     *
     * ```php
     * $db->cache(function (Connection $db) {
     *
     *     // ... queries that use query cache ...
     *
     *     return $db->noCache(function (Connection $db) {
     *         // this query will not use query cache
     *         return (new Query())->select(['name', 'status'])->from('customer')->limit(10)->all();
     *     });
     * });
     * ```
     *
     * @param callable $callable a PHP callable that contains DB queries which should not use query cache.
     * The signature of the callable is `function (Connection $db)`.
     * @return mixed the return result of the callable
     * @throws \Exception|\Throwable if there is any exception during query
     * @see enableQueryCache
     * @see queryCache
     * @see cache()
     */
     public function noCache(callable $callable)
     {
        $this->_queryCacheInfo[] = false;
        try {
            $result = call_user_func($callable, $this);
            array_pop($this->_queryCacheInfo);
            return $result;
        } catch (\Exception $e) {
            array_pop($this->_queryCacheInfo);
            throw $e;
        } catch (\Throwable $e) {
            array_pop($this->_queryCacheInfo);
            throw $e;
        }
    }

    /**
     * Returns the current query cache information.
     * This method is used internally by [[Query]].
     * @param int $duration the preferred caching duration. If null, it will be ignored.
     * @param \yii\caching\Dependency $dependency the preferred caching dependency. If null, it will be ignored.
     * @return array the current query cache information, or null if query cache is not enabled.
     * @internal
     */
    public function getQueryCacheInfo($duration, $dependency)
    {
        if (!$this->enableQueryCache) {
            return null;
        }

        $info = end($this->_queryCacheInfo);
        if (is_array($info)) {
            if ($duration === null) {
                $duration = $info[0];
            }
            if ($dependency === null) {
                $dependency = $info[1];
            }
        }
        
        if ($duration === 0 || $duration > 0) {
            if (is_string($this->queryCache) && Yii::$app) {
                $cache = Yii::$app->get($this->queryCache, false);
            } else {
                $cache = $this->queryCache;
            }
            if ($cache instanceof CacheInterface) {
                return [$cache, $duration, $dependency];
            }
        }
    
        return null;
    }

}
