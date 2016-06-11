<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\file;

use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDatetime;
use yii\base\InvalidParamException;
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
     * @var string filename to be used for file storage.
     */
    public $filename;
    /**
     * @var array user data for the "metadata" field of the files collection document.
     */
    public $metadata = [];
    /**
     * @var array saving options.
     * Supported options:
     *
     * - aliases: array, an array of aliases
     * - contentType: string, content type to be stored with the file.
     */
    public $options = [];
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
     * @var array file data to be saved.
     */
    protected $fileData;

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

        if (!isset($this->fileData['_id'])) {
            $this->fileData['_id'] = new ObjectID();
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
     * Adds a file content to the upload.
     * This method can invoked several times before [[complete()]] is called.
     * @param string $filename source file name.
     * @return $this self reference.
     */
    public function addFile($filename)
    {
        if ($this->filename === null) {
            $this->filename = basename($filename);
        }

        $stream = fopen($filename, 'r+');
        if ($stream === false) {
            throw new InvalidParamException("Unable to read file '{$filename}'");
        }
        return $this->addStream($stream);
    }

    /**
     * Completes upload.
     * @return array saved document.
     */
    public function complete()
    {
        $this->flushBuffer(true);

        $this->insertFile();

        return $this->fileData;
    }

    /**
     * Cancels the upload.
     */
    public function cancel()
    {
        $this->buffer = null;

        $this->collection->getChunkCollection()->remove(['files_id' => $this->fileData['_id']], ['limit' => 0]);
        $this->collection->remove(['_id' => $this->fileData['_id']], ['limit' => 1]);
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
            'files_id' => $this->fileData['_id'],
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
        if ($this->filename === null) {
            $this->fileData['filename'] = $this->fileData['_id'] . '.dat';
        } else {
            $this->fileData['filename'] = $this->filename;
        }
        $this->fileData['uploadDate'] = new UTCDateTime(round(microtime(true) * 1000));

        $this->fileData = array_merge($this->fileData, $this->options);

        if (!empty($this->metadata)) {
            $this->fileData['metadata'] = $this->metadata;
        }
        $this->fileData['chunkSize'] = $this->chunkSize;
        $this->fileData['length'] = $this->length;
        $this->fileData['md5'] = hash_final($this->hashContext);

        $this->collection->insert($this->fileData);
    }
}