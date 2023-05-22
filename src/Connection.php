<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
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
 * @property-read Database $database Database instance.
 * @property string $defaultDatabaseName Default database name.
 * @property-read file\Collection $fileCollection Mongo GridFS collection instance.
 * @property-read bool $isActive Whether the Mongo connection is established.
 * @property LogBuilder $logBuilder The log builder for this connection. Note that the type of this property
 * differs in getter and setter. See [[getLogBuilder()]] and [[setLogBuilder()]] for details.
 * @property QueryBuilder $queryBuilder The query builder for the this MongoDB connection. Note that the type
 * of this property differs in getter and setter. See [[getQueryBuilder()]] and [[setQueryBuilder()]] for
 * details.
 * @property-write ClientSession|null $session New instance of ClientSession to replace return $this.
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
     * @event yii\base\Event an event that is triggered right before a mongo client session is started
     */
    const EVENT_START_SESSION = 'startSession';
    /**
     * @event yii\base\Event an event that is triggered right after a mongo client session is ended
     */
    const EVENT_END_SESSION = 'endSession';
    /**
     * @event yii\base\Event an event that is triggered right before a transaction is started
     */
    const EVENT_START_TRANSACTION = 'startTransaction';
    /**
     * @event yii\base\Event an event that is triggered right after a transaction is committed
     */
    const EVENT_COMMIT_TRANSACTION = 'commitTransaction';
    /**
     * @event yii\base\Event an event that is triggered right after a transaction is rolled back
     */
    const EVENT_ROLLBACK_TRANSACTION = 'rollbackTransaction';

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
     * @var array default options for `executeCommand` , executeBulkWrite and executeQuery method of MongoDB\Driver\Manager in `Command` class.
     */
    public $globalExecOptions = [
        /**
         * Shared between some(or all) methods(executeCommand|executeBulkWrite|executeQuery).
         *
         * This options are :
         * - session
         */
        'share' => [],
        'command' => [],
        'bulkWrite' => [],
        'query' => [],
    ];
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
                Yii::debug($token, __METHOD__);
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
            Yii::debug('Closing MongoDB connection: ' . $this->dsn, __METHOD__);
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
            'globalExecOptions' => $this->globalExecOptions
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
     * Recursive replacement on $this->globalExecOptions with new options. {@see $this->globalExecOptions}
     * @param array $newExecOptions {@see $this->globalExecOptions}
     * @return $this
     */
    public function execOptions($newExecOptions)
    {
        if (empty($newExecOptions)) {
            $this->globalExecOptions = [];
        }
        else {
            $this->globalExecOptions = array_replace_recursive($this->globalExecOptions, $newExecOptions);
        }
        return $this;
    }

    /**
     * Ends the previous session and starts the new session.
     * @param array $sessionOptions see doc of ClientSession::start()
     * return ClientSession
     */
    public function startSession($sessionOptions = [])
    {

        if ($this->getInSession()) {
            $this->getSession()->end();
        }

        $newSession = $this->newSession($sessionOptions);
        $this->setSession($newSession);
        return $newSession;
    }

    /**
     * Starts a new session if the session has not started, otherwise returns previous session.
     * @param array $sessionOptions see doc of ClientSession::start()
     * return ClientSession
     */
    public function startSessionOnce($sessionOptions = [])
    {
        if ($this->getInSession()) {   
            return $this->getSession();
        }
        return $this->startSession($sessionOptions);
    }

    /**
     * Only starts the new session for current connection but this session does not set for current connection.
     * @param array $sessionOptions see doc of ClientSession::start()
     * return ClientSession
     */
    public function newSession($sessionOptions = [])
    {
        return ClientSession::start($this, $sessionOptions);
    }

    /**
     * Checks whether the current connection is in session.
     * return bool
     */
    public function getInSession()
    {
        return array_key_exists('session',$this->globalExecOptions['share']);
    }

    /**
     * Checks that the current connection is in session and transaction
     * return bool
     */
    public function getInTransaction()
    {
        return $this->getInSession() && $this->getSession()->getInTransaction();
    }

    /**
     * Throws custom error if transaction is not ready in connection
     * @param string $operation a custom message to be shown
     */
    public function transactionReady($operation)
    {
        if (!$this->getInSession()) {
            throw new Exception('You can\'t ' . $operation . ' because current connection is\'t in a session.');
        }
        if (!$this->getSession()->getInTransaction()) {
            throw new Exception('You can\'t ' . $operation . ' because transaction not started in current session.');
        }
    }

    /**
     * Returns current session
     * return ClientSession|null
     */
    public function getSession()
    {
        return $this->getInSession() ? $this->globalExecOptions['share']['session'] : null;
    }

    /**
     * Starts a transaction with three steps :
     * - starts new session if has not started
     * - starts the transaction in new session
     * - sets new session to current connection
     * @param array $transactionOptions see doc of Transaction::start()
     * @param array $sessionOptions see doc of ClientSession::start()
     * return ClientSession
     */
    public function startTransaction($transactionOptions = [], $sessionOptions = [])
    {
        $session = $this->startSession($sessionOptions,true);
        $session->getTransaction()->start($transactionOptions);
        return $session;
    }

    /**
     * Starts a transaction in current session if the previous transaction was not started in current session.
     * @param array $transactionOptions see doc of Transaction::start()
     * @param array $sessionOptions see doc of ClientSession::start()
     * return ClientSession
     */
    public function startTransactionOnce($transactionOptions = [], $sessionOptions = [])
    {
        if ($this->getInTransaction()) {
            return $this->getSession();
        }
        return $this->startTransaction($transactionOptions,$sessionOptions);
    }

    /**
     * Commits transaction in current session
     */
    public function commitTransaction()
    {
        $this->transactionReady('commit transaction');
        $this->getSession()->transaction->commit();
    }

    /**
     * Rollbacks transaction in current session
     */
    public function rollBackTransaction()
    {
        $this->transactionReady('roll back transaction');
        $this->getSession()->transaction->rollBack();
    }

    /**
     * Changes the current session of connection to execute commands (or drop session)
     * @param ClientSession|null $clientSession new instance of ClientSession to replace
     * return $this
     */
    public function setSession($clientSession)
    {
        #drop session
        if (empty($clientSession)) {
            unset($this->globalExecOptions['share']['session']);
        }
        else {
            $this->globalExecOptions['share']['session'] = $clientSession;
        }
        return $this;
    }

    /**
     * Starts and commits a transaction in easy mode.
     * @param callable $actions your block of code must be runned after transaction started and before commit
     * if the $actions returns false then transaction rolls back.
     * @param array $transactionOptions see doc of Transaction::start()
     * @param array $sessionOptions see doc of ClientSession::start()
     */
    public function transaction(callable $actions, $transactionOptions = [], $sessionOptions = [])
    {
        $session = $this->startTransaction($transactionOptions, $sessionOptions);
        $success = false;
        try {
            $result = call_user_func($actions, $session);
            if ($session->getTransaction()->getIsActive()) {
                if ($result === false) {
                    $session->getTransaction()->rollBack();
                }
                else {
                    $session->getTransaction()->commit();
                }
            }
            $success = true;
        } finally {
            if (!$success && $session->getTransaction()->getIsActive()) {
                $session->getTransaction()->rollBack();
            }
        }
    }

    /**
     * Starts and commits transaction in easy mode if the previous transaction was not executed,
     * otherwise only runs your actions in previous transaction.
     * @param callable $actions your block of code must be runned after transaction started and before commit
     * @param array $transactionOptions see doc of Transaction::start()
     * @param array $sessionOptions see doc of ClientSession::start()
     */
    public function transactionOnce(callable $actions, $transactionOptions = [], $sessionOptions = [])
    {
        if ($this->getInTransaction()) {
            $actions();
        }
        else {
            $this->transaction($actions,$transactionOptions,$sessionOptions);
        }
    }

    /**
     * Runs your mongodb command out of session and transaction.
     * @param callable $actions your block of code must be runned out of session and transaction
     * @return mixed returns a result of $actions()
     */
    public function noTransaction(callable $actions)
    {
        $lastSession = $this->getSession();
        $this->setSession(null);
        try {
            $result = $actions();
        } finally {
            $this->setSession($lastSession);
        }
        return $result;
    }
}