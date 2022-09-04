<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use Yii;

/**
 * In MongoDB, an operation on a single document is atomic. Because you can use embedded documents and
 * arrays to capture relationships between data in a single document structure instead of normalizing
 * across multiple documents and collections, this single-document atomicity obviates the need for multi-document
 * transactions for many practical use cases.
 * For situations that require atomicity of reads and writes to multiple documents (in a single or multiple collections),
 * MongoDB supports multi-document transactions. With distributed transactions, transactions can be used across multiple operations,
 * collections, databases, documents, and shards.
 * @see https://docs.mongodb.com/core/transactions/
 * Note : At least 1.5 mongodb php driver version is supported.
 * Note : At least 4.0 MongoDB version is supported.
 * @see https://docs.mongodb.com/ecosystem/drivers/php/#mongodb-compatibility
 * Note : Nested transaction not supported.
 * @see https://docs.mongodb.com/manual/core/transactions/#transactions-and-sessions
 *
 * @property-read bool $isActive Whether this transaction is active. Only an active transaction can
 * [[commit()]] or [[rollBack()]].
 *
 * @author Abolfazl Ziaratban <abolfazl.ziaratban@gmail.com>
 */
class Transaction extends \yii\base\BaseObject
{
    const STATE_NONE = 'none';
    const STATE_STARTING = 'starting';
    const STATE_ABORTED = 'aborted';
    const STATE_COMMITTED = 'committed';

    /**
     * @var MongoDB\Driver\Session class represents a client session and Commands, queries, and write operations may then be associated the session.
     * @see https://www.php.net/manual/en/class.mongodb-driver-session.php
     */
    public $clientSession;


    /**
     * Set debug message if `enableLogging` property is enable in yii\mongodb\Connection
     * @var string $message please see $this->yiiDebug()
     * @var string $category please see $this->yiiDebug()
     */
    protected function yiiDebug($message, $category = 'mongodb')
    {
        if ($this->clientSession->db->enableLogging) {
            Yii::debug($message,$category);
        }
    }

    /**
     * Begin profile if `enableProfiling` property is enable in yii\mongodb\Connection
     * @var string $token please see $this->yiiBeginProfile()
     * @var string $category please see $this->yiiBeginProfile()
     */
    protected function yiiBeginProfile($token, $category = 'mongodb')
    {
        if ($this->clientSession->db->enableProfiling) {
            Yii::beginProfile($token,$category);
        }
    }

    /**
     * End profile if `enableProfiling` property is enable in yii\mongodb\Connection
     * @var string $token please see $this->yiiEndProfile()
     * @var string $category please see $this->yiiEndProfile()
     */
    protected function yiiEndProfile($token, $category = 'mongodb')
    {
        if ($this->clientSession->db->enableProfiling) {
            Yii::endProfile($token,$category);
        }
    }

    /**
     * Returns the transaction state.
     */
    public function getState()
    {
        return $this->clientSession->mongoSession->getTransactionState();
    }

    /**
     * Returns a value indicating whether this transaction is active.
     * @return bool whether this transaction is active. Only an active transaction
     * can [[commit()]] or [[rollBack()]].
     */
    public function getIsActive()
    {
        return $this->clientSession->db->getIsActive() && $this->clientSession->getInTransaction();
    }

    /**
     * Start a transaction if session is not in transaction process.
     * @see https://www.php.net/manual/en/mongodb-driver-session.starttransaction.php
     * @param array $transactionOptions Options can be passed as argument to this method.
     * Each element in this options array overrides the corresponding option from the "sessionOptions" option,
     * if set when starting the session with ClientSession::start().
     * @see https://www.php.net/manual/en/mongodb-driver-session.starttransaction.php#refsect1-mongodb-driver-session.starttransaction-parameters
     */
    public function start($transactionOptions = [])
    {
        Command::prepareManagerOptions($transactionOptions);
        $this->yiiDebug('Starting mongodb transaction ...', __METHOD__);
        if ($this->clientSession->getInTransaction()) {
            throw new Exception('Nested transaction not supported');
        }
        $this->clientSession->db->trigger(Connection::EVENT_START_TRANSACTION);
        $this->yiiBeginProfile('mongodb > start transaction(session id => ' . $this->clientSession->getId() . ')');
        $this->clientSession->mongoSession->startTransaction($transactionOptions);
        $this->yiiDebug('MongoDB transaction started.', __METHOD__);
    }

    /**
     * Commit a transaction.
     * @see https://www.php.net/manual/en/mongodb-driver-session.committransaction.php
     */
    public function commit()
    {
        $this->yiiDebug('Committing mongodb transaction ...', __METHOD__);
        $this->clientSession->mongoSession->commitTransaction();
        $this->yiiEndProfile('mongodb > start transaction(session id => ' . $this->clientSession->getId() . ')');
        $this->yiiDebug('Commit mongodb transaction.', __METHOD__);
        $this->clientSession->db->trigger(Connection::EVENT_COMMIT_TRANSACTION);
    }

    /**
     * Rolls back a transaction.
     * @see https://www.php.net/manual/en/mongodb-driver-session.aborttransaction.php
     */
    public function rollBack()
    {
        $this->yiiDebug('Rolling back mongodb transaction ...', __METHOD__);
        $this->clientSession->mongoSession->abortTransaction();
        $this->yiiEndProfile('mongodb > start transaction(session id => ' . $this->clientSession->getId() . ')');
        $this->yiiDebug('Roll back mongodb transaction.', __METHOD__);
        $this->clientSession->db->trigger(Connection::EVENT_ROLLBACK_TRANSACTION);
    }
}