<?php

namespace yiiunit\extensions\mongodb\file;

use yiiunit\extensions\mongodb\TestCase;

/**
 * @group file
 */
class DownloadTest extends TestCase
{
    protected function tearDown()
    {
        $this->dropFileCollection('fs');
        parent::tearDown();
    }

    // Tests :

    public function testToStream()
    {
        $collection = $this->getConnection()->getFileCollection();

        $upload = $collection->createUpload();
        $document = $upload->addContent('test content')->complete();

        $download = $collection->createDownload($document);
        $stream = fopen('php://temp', 'w+');
        $download->toStream($stream);

        rewind($stream);
        $this->assertEquals('test content', stream_get_contents($stream));
    }

    public function testToFile()
    {
        $collection = $this->getConnection()->getFileCollection();

        $upload = $collection->createUpload();
        $document = $upload->addContent('test content')->complete();

        $download = $collection->createDownload($document);
        $fileName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . 'download.txt';
        $download->toFile($fileName);

        $this->assertFileExists($fileName);
        $this->assertEquals('test content', file_get_contents($fileName));
    }
}