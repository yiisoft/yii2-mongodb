<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\file;

use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use yii\base\Object;

/**
 * Upload represents the GridFS upload operation.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class Upload extends Object
{
    /**
     * @var Collection file collection to be used.
     */
    public $collection;
    /**
     * @var array document data to be saved.
     */
    public $document;
    /**
     * @var integer chunk size in bytes.
     */
    public $chunkSize = 261120;
    /**
     * @var integer total upload length in bytes.
     */
    public $length = 0;
    /**
     * @var integer file chunk counts.
     */
    public $chunkCount = 0;

    /**
     * @var resource has context for collecting md5 hash
     */
    private $hashContext;
    /**
     * @var string internal data buffer
     */
    private $buffer;


    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->hashContext = hash_init('md5');

        if (!isset($this->document['_id'])) {
            $this->document['_id'] = new ObjectID();
        }

        $this->collection->ensureIndexes();
    }

    /**
     * Adds string content to the upload.
     * This method can invoked several times before [[complete()]] is called.
     * @param string $content binary content.
     * @return $this self reference.
     */
    public function addContent($content)
    {
        $freeBufferLength = $this->chunkSize - strlen($this->buffer);
        $contentLength = strlen($content);
        if ($contentLength > $freeBufferLength) {
            $this->buffer .= substr($content, 0, $freeBufferLength);
            $this->flushBuffer(true);
            return $this->addContent(substr($content, $freeBufferLength));
        } else {
            $this->buffer .= $content;
            $this->flushBuffer();
        }

        return $this;
    }

    /**
     * Adds stream content to the upload.
     * This method can invoked several times before [[complete()]] is called.
     * @param resource $stream data source stream.
     * @return $this self reference.
     */
    public function addStream($stream)
    {
        while (!feof($stream)) {
            $freeBufferLength = $this->chunkSize - strlen($this->buffer);

            $streamChunk = fread($stream, $freeBufferLength);
            if ($streamChunk === false) {
                break;
            }
            $this->buffer .= $streamChunk;
            $this->flushBuffer();
        }

        return $this;
    }

    /**
     * Completes upload.
     * @return array saved document.
     */
    public function complete()
    {
        $this->flushBuffer(true);

        if (isset($this->document['filename'])) {
            $this->document['filename'] = 'file.dat';
        }
        $this->document['length'] = $this->length;
        $this->document['md5'] = hash_final($this->hashContext);

        $this->insertFile();

        return $this->document;
    }

    /**
     * Cancels the upload.
     */
    public function cancel()
    {
        $this->buffer = null;

        $this->collection->getChunkCollection()->remove(['files_id' => $this->document['_id']], ['limit' => 0]);
        $this->collection->remove(['_id' => $this->document['_id']], ['limit' => 1]);
    }

    /**
     * Flushes [[buffer]] to the chunk if it is full.
     * @param boolean $force whether to enforce flushing.
     */
    private function flushBuffer($force = false)
    {
        if ($this->buffer === null) {
            return;
        }

        if ($force || strlen($this->buffer) == $this->chunkSize) {
            $this->insertChunk($this->buffer);
            $this->buffer = null;
        }
    }

    /**
     * Inserts file chunk.
     * @param string $data chunk binary content.
     */
    private function insertChunk($data)
    {
        $chunkDocument = [
            'files_id' => $this->document['_id'],
            'n' => $this->chunkCount,
            'data' => new Binary($data, Binary::TYPE_GENERIC),
        ];

        hash_update($this->hashContext, $data);

        $this->collection->getChunkCollection()->insert($chunkDocument);
        $this->length += strlen($data);
        $this->chunkCount++;
    }

    /**
     * Inserts [[document]] into file collection.
     */
    private function insertFile()
    {
        $this->collection->insert($this->document);
    }
}