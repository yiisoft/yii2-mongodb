<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use Yii;
use yii\base\ErrorHandler;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\web\MultiFieldSession;

/**
 * Session extends [[\yii\web\Session]] by using MongoDB as session data storage.
 *
 * By default, Session stores session data in a collection named 'session' inside the default database.
 * This collection is better to be pre-created with fields 'id' and 'expire' indexed.
 * The collection name can be changed by setting [[sessionCollection]].
 *
 * The following example shows how you can configure the application to use Session:
 * Add the following to your application config under `components`:
 *
 * ```php
 * 'session' => [
 *     'class' => 'yii\mongodb\Session',
 *     // 'db' => 'mymongodb',
 *     // 'sessionCollection' => 'my_session',
 * ]
 * ```
 *
 * Session extends [[MultiFieldSession]], thus it allows saving extra fields into the [[sessionCollection]].
 * Refer to [[MultiFieldSession]] for more details.
 *
 * Tip: you can use MongoDB [TTL index](https://docs.mongodb.com/manual/tutorial/expire-data/) for the session garbage
 * collection for performance saving, in this case you should set [[Session::gCProbability]] to `0`.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Session extends MultiFieldSession
{
    /**
     * @var Connection|array|string the MongoDB connection object or the application component ID of the MongoDB connection.
     * After the Session object is created, if you want to change this property, you should only assign it
     * with a MongoDB connection object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db = 'mongodb';
    /**
     * @var string|array the name of the MongoDB collection that stores the session data.
     * Please refer to [[Connection::getCollection()]] on how to specify this parameter.
     * This collection is better to be pre-created with fields 'id' and 'expire' indexed.
     */
    public $sessionCollection = 'session';

    /**
     * @var array Session fields to be written into session table columns
     * @since 2.1.8
     */
    protected $fields = [];


    /**
     * Initializes the Session component.
     * This method will initialize the [[db]] property to make sure it refers to a valid MongoDB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init()
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::className());
    }

    /**
     * Session open handler.
     * @internal Do not call this method directly.
     * @param string $savePath session save path
     * @param string $sessionName session name
     * @return bool whether session is opened successfully
     */
    public function openSession($savePath, $sessionName)
    {
        if ($this->getUseStrictMode()) {
            $id = $this->getId();
            $collection = $this->db->getCollection($this->sessionCollection);
            $condition = [
                'id' => $id,
                'expire' => ['$gt' => time()],
            ];
            if (!$collection->documentExists($condition)) {
                //This session id does not exist, mark it for forced regeneration
                $this->_forceRegenerateId = $id;
            }
        }

        return parent::openSession($savePath, $sessionName);
    }

    /**
     * Updates the current session ID with a newly generated one.
     * Please refer to <http://php.net/session_regenerate_id> for more details.
     * @param bool $deleteOldSession Whether to delete the old associated session file or not.
     */
    public function regenerateID($deleteOldSession = false)
    {
        $oldID = session_id();

        // if no session is started, there is nothing to regenerate
        if (empty($oldID)) {
            return;
        }

        parent::regenerateID(false);
        $newID = session_id();

        $collection = $this->db->getCollection($this->sessionCollection);
        $row = $collection->findOne(['id' => $oldID]);
        if ($row !== null) {
            if ($deleteOldSession) {
                $collection->update(['id' => $oldID], ['id' => $newID]);
            } else {
                unset($row['_id']);
                $row['id'] = $newID;
                $collection->insert($row);
            }
        } else {
            // shouldn't reach here normally
            $collection->insert($this->composeFields($newID, ''));
        }
    }

    /**
     * Session read handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return string the session data
     */
    public function readSession($id)
    {
        $collection = $this->db->getCollection($this->sessionCollection);
        $condition = [
            'id' => $id,
            'expire' => ['$gt' => time()],
        ];

        if (isset($this->readCallback)) {
            $doc = $collection->findOne($condition);
            return $doc === null ? '' : $this->extractData($doc);
        }

        $doc = $collection->findOne(
            $condition,
            ['data' => 1, '_id' => 0]
        );
        return isset($doc['data']) ? $doc['data'] : '';
    }

    /**
     * Session write handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return bool whether session write is successful
     */
    public function writeSession($id, $data)
    {
        if ($this->getUseStrictMode() && $id === $this->_forceRegenerateId) {
            //Ignore write when forceRegenerate is active for this id
            return true;
        }

        // exception must be caught in session write handler
        // http://us.php.net/manual/en/function.session-set-save-handler.php
        try {

            // ensure backwards compatability, related to:
            // https://github.com/yiisoft/yii2/pull/17188
            // https://github.com/yiisoft/yii2/pull/17559
            if ($this->writeCallback && !$this->fields) {
                $this->fields = $this->composeFields();
            }

            // ensure data consistency
            if (!isset($this->fields['data'])) {
                $this->fields['data'] = $data;
            } else {
                $_SESSION = $this->fields['data'];
            }

            // ensure 'id' and 'expire' are never affected by [[writeCallback]]
            $this->fields = array_merge($this->fields, [
                'id' => $id,
                'expire' => time() + $this->getTimeout(),
            ]);

            $this->db->getCollection($this->sessionCollection)->update(
                ['id' => $id],
                $this->fields,
                ['upsert' => true]
            );

            $this->fields = [];

        } catch (\Exception $e) {
            Yii::$app->errorHandler->handleException($e);
            return false;
        }

        return true;
    }

    /**
     * Session destroy handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return bool whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        $this->db->getCollection($this->sessionCollection)->remove(
            ['id' => $id],
            ['justOne' => true]
        );

        return true;
    }

    /**
     * Session GC (garbage collection) handler.
     * Do not call this method directly.
     * @param int $maxLifetime the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * @return bool whether session is GCed successfully
     */
    public function gcSession($maxLifetime)
    {
        $this->db->getCollection($this->sessionCollection)
            ->remove(['expire' => ['$lt' => time()]]);

        return true;
    }
}
