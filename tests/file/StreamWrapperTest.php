<?php

namespace yiiunit\extensions\mongodb\file;

use yiiunit\extensions\mongodb\TestCase;

class StreamWrapperTest extends TestCase
{
    protected function tearDown()
    {
        $connection = $this->getConnection();
        if (in_array($connection->fileStreamProtocol, stream_get_wrappers())) {
            stream_wrapper_unregister($connection->fileStreamProtocol);
        }

        $this->dropFileCollection('fs');

        parent::tearDown();
    }

    // Tests :

    public function testCreateFromDownload()
    {
        $collection = $this->getConnection()->getFileCollection();

        $upload = $collection->createUpload();
        $document = $upload->addContent('test content')->complete();

        $document = $collection->findOne(['_id' => $document['_id']]);
        $resource = $document['file']->toResource();
        $this->assertTrue(is_resource($resource));

        $this->assertEquals('test content', stream_get_contents($resource));
    }

    public function testWriteResource()
    {
        $connection = $this->getConnection();
        $this->mockApplication([
            'components' => [
                'mongodb' => $connection
            ],
        ]);

        $connection->registerFileStreamWrapper(true);
        $databaseName = $connection->getDefaultDatabaseName();

        $url = "gridfs://{$databaseName}.fs?filename=test.txt";
        $resource = fopen($url, 'w');
        fwrite($resource, 'begin ');
        fwrite($resource, 'end');
        fclose($resource);

        $collection = $connection->getFileCollection();
        $document = $collection->findOne(['filename' => 'test.txt']);
        $this->assertNotEmpty($document);

        $this->assertEquals('begin end', $document['file']->toString());
    }

    public function testReadResource()
    {
        $connection = $this->getConnection();
        $this->mockApplication([
            'components' => [
                'mongodb' => $connection
            ],
        ]);

        $collection = $connection->getFileCollection();
        $upload = $collection->createUpload();
        $document = $upload->addContent('test content')->complete();

        $connection->registerFileStreamWrapper(true);
        $databaseName = $connection->getDefaultDatabaseName();

        $url = "gridfs://{$databaseName}.fs?_id=" . $document['_id'];
        $resource = fopen($url, 'r');

        $this->assertEquals('test content', stream_get_contents($resource));
    }
    
    public function testSeek()
    {
        $connection = $this->getConnection();
        $this->mockApplication([
            'components' => [
                'mongodb' => $connection
            ],
        ]);

        $connection->registerFileStreamWrapper(true);
        $databaseName = $connection->getDefaultDatabaseName();

        $url = "gridfs://{$databaseName}.fs?filename=test.txt";
        $resource = fopen($url, 'w');
        fwrite($resource, 'begin end');
        fclose($resource);
        
        $url = "gridfs://{$databaseName}.fs?filename=test.txt";
        $resource = fopen($url, 'r');
        $data = fgets($resource);
        
        fseek($resource, 0);
        $position = ftell($resource);
        $this->assertEquals(0, $position);
        
        fseek($resource, 2, SEEK_CUR);
        $position = ftell($resource);
        $this->assertEquals(2, $position);
        
        fseek($resource, 0, SEEK_END);
        $position = ftell($resource);
        $this->assertEquals(9, $position);
    }
}