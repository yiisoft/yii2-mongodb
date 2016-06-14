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

    public function testSubstr()
    {
        $collection = $this->getConnection()->getFileCollection();

        $upload = $collection->createUpload();
        $upload->chunkSize = 10;
        $document = $upload->addContent('0123456789')
            ->addContent('0123456789')
            ->addContent('0123456789')
            ->complete();

        $download = $collection->createDownload($document);

        $this->assertEquals('2345', $download->substr(12, 4), 'Unable to read part of chunk');
        $this->assertEquals('89012345678901', $download->substr(8, 14), 'Unable to read from different chunks');
        $this->assertEquals('56789', $download->substr(25, 10), 'Unable handle read out of length');
        $this->assertEquals(false, $download->substr(100, 10), 'No fail on read out of file');
        $this->assertEquals('2345678', $download->substr(-8, 7), 'Unable to use negative start');
        $this->assertEquals('234567', $download->substr(22, -2), 'Unable to use negative length');
    }
}