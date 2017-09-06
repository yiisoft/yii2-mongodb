<?php

namespace yiiunit\extensions\mongodb\file;

use MongoDB\BSON\ObjectID;
use yii\mongodb\file\Cursor;
use yii\mongodb\file\Download;
use yiiunit\extensions\mongodb\TestCase;

/**
 * @group file
 */
class CollectionTest extends TestCase
{
    protected function tearDown()
    {
        $this->dropFileCollection('fs');
        parent::tearDown();
    }

    // Tests :

    public function testGetChunkCollection()
    {
        $collection = $this->getConnection()->getFileCollection();
        $chunkCollection = $collection->getChunkCollection();
        $this->assertTrue($chunkCollection instanceof \yii\mongodb\Collection);
        $this->assertTrue($chunkCollection->database instanceof \yii\mongodb\Database);
    }

    public function testGetFileCollection()
    {
        $collection = $this->getConnection()->getFileCollection();
        $fileCollection = $collection->getFileCollection();
        $this->assertTrue($fileCollection instanceof \yii\mongodb\Collection);
        $this->assertTrue($fileCollection->database instanceof \yii\mongodb\Database);
    }

    public function testEnsureIndexes()
    {
        $collection = $this->getConnection()->getFileCollection();

        $collection->ensureIndexes();
        $this->assertCount(2, $collection->listIndexes());
        $this->assertCount(2, $collection->getChunkCollection()->listIndexes());

        $collection->dropAllIndexes();
        $collection->ensureIndexes();
        $this->assertCount(1, $collection->listIndexes());

        $collection->ensureIndexes(true);
        $this->assertCount(2, $collection->listIndexes());
    }

    public function testFind()
    {
        $collection = $this->getConnection()->getFileCollection();
        $cursor = $collection->find();
        $this->assertTrue($cursor instanceof Cursor);
    }

    public function testInsertFile()
    {
        $collection = $this->getConnection()->getFileCollection();

        $filename = __FILE__;
        $id = $collection->insertFile($filename);
        $this->assertTrue($id instanceof ObjectID);

        $files = $this->findAll($collection);
        $this->assertEquals(1, count($files));

        $file = $files[0];
        $this->assertEquals(basename($filename), $file['filename']);
        $this->assertEquals(filesize($filename), $file['length']);
    }

    public function testInsertFileContent()
    {
        $collection = $this->getConnection()->getFileCollection();

        $bytes = 'Test file content';
        $id = $collection->insertFileContent($bytes);
        $this->assertTrue($id instanceof ObjectID);

        $files = $this->findAll($collection);
        $this->assertEquals(1, count($files));

        /* @var $file Download */
        $file = $files[0];
        $this->assertTrue($file['file'] instanceof Download);
        $this->assertEquals($bytes, $file['file']->getBytes());
    }

    /**
     * @depends testInsertFileContent
     */
    public function testGet()
    {
        $collection = $this->getConnection()->getFileCollection();

        $bytes = 'Test file content';
        $id = $collection->insertFileContent($bytes);

        $file = $collection->get($id);
        $this->assertTrue($file instanceof Download);
        $this->assertEquals($bytes, $file->getBytes());
    }

    /**
     * @depends testGet
     */
    public function testDeleteFile()
    {
        $collection = $this->getConnection()->getFileCollection();

        $bytes = 'Test file content';
        $id = $collection->insertFileContent($bytes);

        $this->assertTrue($collection->delete($id));

        $file = $collection->get($id);
        $this->assertNull($file);
    }

    /**
     * @depends testInsertFileContent
     */
    public function testRemove()
    {
        $collection = $this->getConnection()->getFileCollection();

        for ($i = 1; $i <=10; $i++) {
            $bytes = 'Test file content ' . $i;
            $collection->insertFileContent($bytes, [
                'index' => $i
            ], ['chunkSize' => 15]);
        }

        $this->assertEquals(1, $collection->remove(['index' => ['$in' =>[1, 2, 3]]], ['limit' => 1]));
        $this->assertEquals(9, $collection->count());
        $this->assertEquals(18, $collection->getChunkCollection()->count());

        $this->assertEquals(3, $collection->remove(['index' => ['$in' =>[5, 7, 9]]]));
        $this->assertEquals(6, $collection->count());
        $this->assertEquals(12, $collection->getChunkCollection()->count());

        $this->assertEquals(6, $collection->remove());
        $this->assertEquals(0, $collection->count());
        $this->assertEquals(0, $collection->getChunkCollection()->count());
    }
}
