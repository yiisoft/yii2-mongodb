<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use Yii;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\ReadPreference;

/**
 * ClientSession represents a client session and Commands, queries, and write operations may then be associated the session.
 * @see https://docs.mongodb.com/manual/release-notes/3.6/#client-sessions
 * Note : The minimum supported version of mongodb php driver is 1.4.0
 * Note : The minimum supported version of MongoDB server is 3.6
 * @see https://github.com/mongodb/mongo-php-driver/releases/tag/1.4.0
 * @see https://docs.mongodb.com/ecosystem/drivers/php/#mongodb-compatibility
 *
 * @property-read string $id
 * @property-read bool $inTransaction
 * @property-read Transaction $transaction Returns current transaction.
 *
 * @author Abolfazl Ziaratban <abolfazl.ziaratban@gmail.com>
 */
class ClientSession extends \yii\base\BaseObject
{
    /**
     * @var Connection the database connection that this transaction is associated with.
     */
    public $db;
    /**
     * @var MongoDB\Driver\Session class represents a client session and Commands,
     * queries, and write operations may then be associated the session.
     * @see https://www.php.net/manual/en/class.mongodb-driver-session.php
     */
    public $mongoSession;

    /**
     * @var Transaction the current transaction in session. this transaction can only be created once.
     */
    private $_transaction = null;


    /**
     * Prepares options for some purposes
     * @param array by reference
     * convert string option to object
     * [
     * 'defaultTransactionOptions' => [
     * 'readConcern' => 'snapshot',
     * 'writeConcern' => 'majority',
     * 'writeConcern' => ['majority',true],
     * 'readPreference' => 'primary',
     * ],
     * ]
     * convert to :
     * [
     * 'defaultTransactionOptions' => [
     * 'readConcern' => new \MongoDB\Driver\ReadConcern('snapshot'),
     * 'writeConcern' => new \MongoDB\Driver\WriteConcern('majority'),
     * 'writeConcern' => new \MongoDB\Driver\WriteConcern('majority',true),
     * 'readPreference' => new \MongoDB\Driver\ReadPreference('primary'),
     * ],
     * ]
     */
    public static function prepareOptions(&$options)
    {
        if (array_key_exists('defaultTransactionOptions', $options)) {

            //convert readConcern
            if (
                array_key_exists('readConcern', $options['defaultTransactionOptions']) &&
                is_string($options['defaultTransactionOptions']['readConcern'])
            ) {
                $options['defaultTransactionOptions']['readConcern'] = new ReadConcern($options['defaultTransactionOptions']['readConcern']);
            }

            //convert writeConcern
            if (array_key_exists('writeConcern',$options['defaultTransactionOptions'])) {
                if (
                    is_string($options['defaultTransactionOptions']['writeConcern']) ||
                    is_int($options['defaultTransactionOptions']['writeConcern'])
                ) {
                    $options['defaultTransactionOptions']['writeConcern'] = new WriteConcern($options['defaultTransactionOptions']['writeConcern']);
                } elseif (is_array($options['defaultTransactionOptions']['writeConcern'])) {
                    $options['defaultTransactionOptions']['writeConcern'] =
                        (new \ReflectionClass('\MongoDB\Driver\WriteConcern'))
                            ->newInstanceArgs(
                                $options['defaultTransactionOptions']['writeConcern']
                            )
                    ;
                }
            }

            //Convert readPreference
            if (array_key_exists('readPreference',$options['defaultTransactionOptions'])) {
                if (is_string($options['defaultTransactionOptions']['readPreference'])) {
                    $options['defaultTransactionOptions']['readPreference'] = new ReadPreference($options['defaultTransactionOptions']['readPreference']);
                } else if(is_array($options['defaultTransactionOptions']['readPreference'])) {
                    $options['defaultTransactionOptions']['readPreference'] =
                        (new \ReflectionClass('\MongoDB\Driver\ReadPreference'))
                            ->newInstanceArgs(
                                $options['defaultTransactionOptions']['readPreference']
                            )
                    ;
                }
            }
        }
    }

    /**
     * Returns the logical session ID as string for this session, which may be used to identify this session's operations on the server.
     * @see https://www.php.net/manual/en/mongodb-driver-session.getlogicalsessionid.php
     * @return string
     */
    public function getId()
    {
        return $this->mongoSession->getLogicalSessionId()->id->jsonSerialize()['$binary'];
    }

    /**
     * Starts a new mongodb session in a connection.
     * @param Connection $db
     * @param Array $sessionOptions Creates a ClientSession for the given options {@see https://www.php.net/manual/en/mongodb-driver-manager.startsession.php#refsect1-mongodb-driver-manager.startsession-parameters}
     * @return ClientSession returns new session base on a session options for the given connection
     */
    public static function start($db, $sessionOptions = [])
    {
        self::prepareOptions($sessionOptions);
        if ($db->enableProfiling) {
            Yii::debug('Starting mongodb session ...', __METHOD__);
        }
        $db->trigger(Connection::EVENT_START_SESSION);
        $newSession = new self([
            'db' => $db,
            'mongoSession' => $db->manager->startSession($sessionOptions),
        ]);
        if ($db->enableProfiling) {   
            Yii::debug('MongoDB session started.', __METHOD__);
        }
        return $newSession;
    }

    /**
     * Gets a current transaction of session or creates a new transaction once
     * @return Transaction returns current transaction
     */
    public function getTransaction()
    {
        if ($this->_transaction === null) {
            return $this->_transaction = new Transaction(['clientSession' => $this]);
        }
        return $this->_transaction;
    }

    /**
     * Returns true if the transaction is in progress
     * @return bool
     */
    public function getInTransaction()
    {
        return $this->mongoSession->isInTransaction();
    }

    /**
     * Ends the current session.
     */
    public function end()
    {
        $this->mongoSession->endSession();
        $this->db->trigger(Connection::EVENT_END_SESSION);
    }
}