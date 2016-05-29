<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use yii\base\Component;
use yii\base\InvalidConfigException;
use Yii;

use MongoDB\Client;
use MongoDB\Driver\WriteConcern;

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
     * @see http://www.php.net/manual/en/mongoclient.construct.php
     */
    public $options = [];
    /**
     * @var array options for the MongoDB driver.
     *
     * @see http://www.php.net/manual/en/mongoclient.construct.php
     */
    public $driverOptions = [];
    /**
     * Design choice to make this fully public and accessible
     */
    public $dbs = [];
    
    public $activeDb;
    /**
     * @var \MongoClient Mongo client instance.
     */
    public $client;
    
    public function init()
    {
        // Since the driver connects lazily itself now there is no 
        // need to calll open on certain functions
        // Also there is no close anymore so even setting null wont close
        // the actual connection
        if (empty($this->dsn)) {
            throw new InvalidConfigException($this->className() . '::dsn cannot be empty.');
        }
        $token = 'Opening MongoDB connection: ' . $this->dsn;
        try {
            Yii::trace($token, __METHOD__);
            Yii::beginProfile($token, __METHOD__);

            // Been made simple, no more fooling around with DB
            // The new driver prefers simplicity on this front plus 
            // auth has to work at first try now so many will use an auth DB
            // here instead
            $this->client = new Client(
                $this->dsn, 
                $this->options, 
                array_merge(
                    $this->driverOptions, 
                    [
                        'typeMap' => 
                        [
                            'root' => 'array',
                            'document' => 'array',
                            'array' => 'array'
                        ]
                    ]
                )
            );

            // Since databases won't trigger a call to the 
            // server let's just init our DB array right now... init
            if(!is_array($this->dbs) || empty($this->dbs)){
                $this->dbs[] = $this->fetchDefaultDatabaseName();
            }
            foreach($this->dbs as $k => $v){
                $options = [];
                if(is_numeric($k)){
                    $name = $v;
                }else{
                    $name = $k;
                    $options = $v;
                }
                $this->dbs[$k] = $this->selectDatabase($name, $options);
            }

            $this->initConnection();
            Yii::endProfile($token, __METHOD__);
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
        return parent::init();
    }

    /**
     * Returns the Mongo collection with the given name.
     * @param string|null $name collection name, if null default one will be used.
     * @param boolean $refresh whether to reestablish the database connection even if it is found in the cache.
     * @return Database database instance.
     */
    public function getDatabase($name = null, $options = [], $refresh = false)
    {
		if(isset($options['active'])){
			$this->activeDb = $name;
		}
		
		if($name){
			if(isset($this->dbs[$name])){
				return $this->dbs[$name];
			}
			$db = $this->dbs[$name] = $this->selectDatabase($name, $options);
			return $db;
		}
		
		// If we have a default database set let's go looking for it
		if($this->activeDb && isset($this->dbs[$this->activeDb])){
			return $this->dbs[$this->activeDb];
		}elseif($this->activeDb){
			throw new Exception($name . ' is default but does not exist');
		}
		
		// By default let's return the first in the list
		foreach($this->dbs as $db){
			return $db;
		}
    }
    
    public function selectDatabase($name, $options = [])
    {
        return Yii::createObject([
            'class' => 'yii\mongodb\Database',
            'mongoDb' => $this->client->selectDatabase(
                $name, 
                array_merge(
                    [
                        'writeConcern' => new WriteConcern(1)
                    ],
                    $options
                )
            )
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
    public function getCollection($name, $options = [], $refresh = false)
    {
        if (is_array($name)) {
            list ($dbName, $collectionName) = $name;
            return $this->getDatabase($dbName)->getCollection($collectionName, $options, $refresh);
        } else {
            return $this->getDatabase()->getCollection($name, $options, $refresh);
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
     * Returns [[defaultDatabaseName]] value, if it is not set,
     * attempts to determine it from [[dsn]] value.
     * @return string default database name
     * @throws \yii\base\InvalidConfigException if unable to determine default database name.
     */
    protected function fetchDefaultDatabaseName()
    {
        if (preg_match('/^mongodb:\\/\\/.+\\/([^?&]+)/s', $this->dsn, $matches)) {
            return $matches[1];
        } else {
            throw new InvalidConfigException("Unable to determine default database name from dsn.");
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
}
