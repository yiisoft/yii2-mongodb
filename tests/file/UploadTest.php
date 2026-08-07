<?php

namespace yiiunit\extensions\mongodb\file;

use MongoDB\BSON\ObjectId;
use yiiunit\extensions\mongodb\TestCase;

/**
 * @group file
 */
class UploadTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->dropFileCollection('fs');
        parent::tearDown();
    }

    // Tests :

    public function testAddContent()
    {
        $collection = $this->getConnection()->getFileCollection();

        $upload = $collection->createUpload();
        $document = $upload->addContent('content line 1')
            ->addContent('content line 2')
            ->complete();

        $this->assertTrue($document['_id'] instanceof ObjectId);
        $this->assertEquals(1, $collection->count());
        $this->assertEquals(1, $collection->getChunkCollection()->count());
    }

    /**
     * @depends testAddContent
     */
    public function testAddContentChunk()
    {
        $collection = $this->getConnection()->getFileCollection();

        $upload = $collection->createUpload();
        $upload->chunkSize = 10;
        $document = $upload->addContent('0123456789-tail')->complete();

        $this->assertTrue($document['_id'] instanceof ObjectId);
        $this->assertEquals(1, $collection->count());
        $this->assertEquals(2, $collection->getChunkCollection()->count());
    }

    public function testAddStream()
    {
        $collection = $this->getConnection()->getFileCollection();

        $upload = $collection->createUpload();

        $resource = fopen(__FILE__, 'r');

        $document = $upload->addStream($resource)->complete();

        $this->assertTrue($document['_id'] instanceof ObjectId);
        $this->assertEquals(1, $collection->count());
        $this->assertEquals(1, $collection->getChunkCollection()->count());
    }

    /**
     * @depends testAddContent
     */
    public function testCancel()
    {
        $collection = $this->getConnection()->getFileCollection();

        $upload = $collection->createUpload();
        $document = $upload->addContent('content line 1');
        $document->cancel();

        $this->assertEquals(0, $collection->count());
        $this->assertEquals(0, $collection->getChunkCollection()->count());
    }

    /**
     * @see https://github.com/yiisoft/yii2-mongodb/issues/179
     *
     * @depends testAddContent
     */
    public function testCustomId()
    {
        $collection = $this->getConnection()->getFileCollection();

        $id = new ObjectId();
        $upload = $collection->createUpload([
            'document' => [
                '_id' => $id,
            ]
        ]);
        $document = $upload->addContent('object ID')->complete();
        $this->assertSame($id, $document['_id']);

        $id = '5889da846748383ce0556bd9';
        $upload = $collection->createUpload([
            'document' => [
                '_id' => $id,
            ]
        ]);
        $document = $upload->addContent('object ID')->complete();
        $this->assertSame($id, (string)$document['_id']);

        $id = 'custom-id';
        $upload = $collection->createUpload([
            'document' => [
                '_id' => $id,
            ]
        ]);
        $document = $upload->addContent('object ID')->complete();
        $this->assertSame($id, $document['_id']);
    }

    public function testCustomIdOfTypeUnsupportedByObjectId(): void
    {
        $collection = $this->getConnection()->getFileCollection();

        $id = ['group' => 'reports', 'seq' => 1];
        $upload = $collection->createUpload([
            'document' => [
                '_id' => $id,
            ]
        ]);

        $this->assertSame($id, $upload->document['_id'], 'Composite `_id` must survive init without a `TypeError`.');

        $upload->cancel();
    }
}
