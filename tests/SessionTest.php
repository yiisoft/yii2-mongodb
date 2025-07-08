<?php

namespace yiiunit\extensions\mongodb;

use yii\mongodb\Session;
use Yii;
use yii\helpers\ArrayHelper;

class SessionTest extends TestCase
{
    /**
     * @var string test session collection name.
     */
    protected static $sessionCollection = '_test_session';

    protected function tearDown(): void
    {
        $this->dropCollection(static::$sessionCollection);
        parent::tearDown();
    }

    /**
     * Creates test session instance.
     * @return Session session instance.
     */
    protected function createSession($config = [])
    {
        return Yii::createObject(ArrayHelper::merge([
            'class' => Session::className(),
            'db' => $this->getConnection(),
            'sessionCollection' => static::$sessionCollection,
        ], $config));
    }

    // Tests:

    public function testWriteSession()
    {
        $session = $this->createSession();

        $id = uniqid();
        $data = [
            'name' => 'value'
        ];
        $dataSerialized = serialize($data);
        $this->assertTrue($session->writeSession($id, $dataSerialized), 'Unable to write session!');

        $collection = $session->db->getCollection($session->sessionCollection);
        $rows = $this->findAll($collection);
        $this->assertCount(1, $rows, 'No session record!');

        $row = array_shift($rows);
        $this->assertEquals($id, $row['id'], 'Wrong session id!');
        $this->assertEquals($dataSerialized, $row['data'], 'Wrong session data!');
        $this->assertTrue($row['expire'] > time(), 'Wrong session expire!');

        $newData = [
            'name' => 'new value'
        ];
        $newDataSerialized = serialize($newData);
        $this->assertTrue($session->writeSession($id, $newDataSerialized), 'Unable to update session!');

        $rows = $this->findAll($collection);
        $this->assertCount(1, $rows, 'Wrong session records after update!');
        $newRow = array_shift($rows);
        $this->assertEquals($id, $newRow['id'], 'Wrong session id after update!');
        $this->assertEquals($newDataSerialized, $newRow['data'], 'Wrong session data after update!');
        $this->assertTrue($newRow['expire'] >= $row['expire'], 'Wrong session expire after update!');
    }

    /**
     * @depends testWriteSession
     */
    public function testDestroySession()
    {
        $session = $this->createSession();

        $id = uniqid();
        $data = [
            'name' => 'value'
        ];
        $dataSerialized = serialize($data);
        $session->writeSession($id, $dataSerialized);

        $this->assertTrue($session->destroySession($id), 'Unable to destroy session!');

        $collection = $session->db->getCollection($session->sessionCollection);
        $rows = $this->findAll($collection);
        $this->assertEmpty($rows, 'Session record not deleted!');
    }

    /**
     * @depends testWriteSession
     */
    public function testReadSession()
    {
        $session = $this->createSession();

        $id = uniqid();
        $data = [
            'name' => 'value'
        ];
        $dataSerialized = serialize($data);
        $session->writeSession($id, $dataSerialized);

        $sessionData = $session->readSession($id);
        $this->assertEquals($dataSerialized, $sessionData, 'Unable to read session!');

        $collection = $session->db->getCollection($session->sessionCollection);
        list($row) = $this->findAll($collection);
        $newRow = $row;
        $newRow['expire'] = time() - 1;
        unset($newRow['_id']);
        $collection->update(['_id' => $row['_id']], $newRow);

        $sessionData = $session->readSession($id);
        $this->assertEquals('', $sessionData, 'Expired session read!');
    }

    public function testGcSession()
    {
        $session = $this->createSession();
        $collection = $session->db->getCollection($session->sessionCollection);
        $collection->batchInsert([
            [
                'id' => uniqid(),
                'expire' => time() + 10,
                'data' => 'actual',
            ],
            [
                'id' => uniqid(),
                'expire' => time() - 10,
                'data' => 'expired',
            ],
        ]);
        $this->assertTrue($session->gcSession(10), 'Unable to collection garbage session!');

        $rows = $this->findAll($collection);
        $this->assertCount(1, $rows, 'Wrong records count!');
    }

    /**
     * @depends testWriteSession
     */
    public function testWriteCustomField()
    {
        $session = $this->createSession();
        $session->writeCallback = function ($session) {
            return [
                'user_id' => 15
            ];
        };

        $session->writeSession('test', 'session data');

        $rows = $this->findAll($session->db->getCollection($session->sessionCollection));

        $this->assertEquals('session data', $rows[0]['data']);
        $this->assertEquals(15, $rows[0]['user_id']);
    }

    /**
     * @depends testWriteSession
     * @runInSeparateProcess
     */
    public function testStrictMode()
    {
        //non-strict-mode test
        $nonStrictSession = $this->createSession([
            'useStrictMode' => false,
        ]);
        $nonStrictSession->close();
        $nonStrictSession->destroySession('non-existing-non-strict');
        $nonStrictSession->setId('non-existing-non-strict');
        $nonStrictSession->open();
        $this->assertEquals('non-existing-non-strict', $nonStrictSession->getId());
        $nonStrictSession->close();

        //strict-mode test
        $strictSession = $this->createSession([
            'useStrictMode' => true,
        ]);
        $strictSession->close();
        $strictSession->destroySession('non-existing-strict');
        $strictSession->setId('non-existing-strict');
        $strictSession->open();
        $id = $strictSession->getId();
        $this->assertNotEquals('non-existing-strict', $id);
        $strictSession->set('strict_mode_test', 'session data');
        $strictSession->close();
        //Ensure session was not stored under forced id
        $strictSession->setId('non-existing-strict');
        $strictSession->open();
        $this->assertNotEquals('session data', $strictSession->get('strict_mode_test'));
        $strictSession->close();
        //Ensure session can be accessed with the new (and thus existing) id.
        $strictSession->setId($id);
        $strictSession->open();
        $this->assertNotEmpty($id);
        $this->assertEquals($id, $strictSession->getId());
        $this->assertEquals('session data', $strictSession->get('strict_mode_test'));
        $strictSession->close();
    }
}
