<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use Yii;


/**
 * ClientSession represents a client session and Commands, queries, and write operations may then be associated the session.
 * @see https://docs.mongodb.com/manual/release-notes/3.6/#client-sessions
 * Note : At least 1.4.0 mongodb php driver version is supported.
 * Note : At least 3.6 MongoDB version is supported.
 * @see https://github.com/mongodb/mongo-php-driver/releases/tag/1.4.0
 * @see https://docs.mongodb.com/ecosystem/drivers/php/#mongodb-compatibility
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
     * @var Transaction current transaction in session. this transaction can only be created once.
    */
    private $_transaction = null;

    /**
     * Start a new session in a connection.
     * @param Connection $db 
     * @param Array $sessionOptions Creates a ClientSession for the given options
     * @see https://www.php.net/manual/en/mongodb-driver-manager.startsession.php#refsect1-mongodb-driver-manager.startsession-parameters
     * @return ClientSession return new session base on a session options for the given connection
    */
    public static function start($db, $sessionOptions = []){
        Connection::prepareExecOptions($sessionOptions);
        Yii::trace('Starting mongodb session ...', __METHOD__);
        $db->trigger(Connection::EVENT_START_SESSION);
        $newSession = new self([
            'db' => $db,
            'mongoSession' => $db->manager->startSession($sessionOptions),
        ]);
        Yii::trace('MongoDB session started.', __METHOD__);
        return $newSession;
    }

    /**
     * Get current transaction of session or create a new transaction once
     * @return Transaction return current transaction
    */
    public function getTransaction(){
        if($this->_transaction === null)
            return $this->_transaction = new Transaction(['clientSession' => $this]);
        return $this->_transaction;
    }

    /**
     * current session has a transaction?
     * @return bool return true if transaction exists otherwise return false
    */
    public function getHasTransaction(){
        return !empty($this->_transaction);
    }

    /**
     * End current session
    */
    public function end(){
        $this->mongoSession->endSession();
        $db->trigger(Connection::EVENT_END_SESSION);
    }
}
