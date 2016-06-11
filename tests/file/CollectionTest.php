<?php

namespace yiiunit\extensions\mongodb\file;

use MongoDB\Driver\Cursor;
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
        $this->assertTrue($id instanceof \MongoId);

        $files = $this->findAll($collection);
        $this->assertEquals(1, count($files));

        /* @var $file \MongoGridFSFile */
        $file = $files[0];
        $this->assertEquals($filename, $file->getFilename());
        $this->assertEquals(file_get_contents($filename), $file->getBytes());
    }

    public function testInsertFileContent()
    {
        $collection = $this->getConnection()->getFileCollection();

        $bytes = 'Test file content';
        $id = $collection->insertFileContent($bytes);
        $this->assertTrue($id instanceof \MongoId);

        $files = $this->findAll($collection);
        $this->assertEquals(1, count($files));

        /* @var $file \MongoGridFSFile */
        $file = $files[0];
        $this->assertEquals($bytes, $file->getBytes());
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
        $this->assertTrue($file instanceof \MongoGridFSFile);
        $this->assertEquals($bytes, $file->getBytes());
    }

    /**
     * @depends testGet
     */
    public function testDelete()
    {
        $collection = $this->getConnection()->getFileCollection();

        $bytes = 'Test file content';
        $id = $collection->insertFileContent($bytes);

        $this->assertTrue($collection->delete($id));

        $file = $collection->get($id);
        $this->assertNull($file);
    }
}
