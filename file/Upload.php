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
use MongoDB\Driver\Exception\InvalidArgumentException;
use yii\base\InvalidParamException;
use yii\base\BaseObject;
use yii\helpers\StringHelper;

/**
 * Upload represents the GridFS upload operation.
 *
 * An `Upload` object is usually created by calling [[Collection::createUpload()]].
 *
 * Note: instance of this class is 'single use' only. Do not attempt to use same `Upload` instance for
 * multiple file upload.
 *
 * Usage example:
 *
 * ```php
 * $document = Yii::$app->mongodb->getFileCollection()->createUpload()
 *     ->addContent('Part 1')
 *     ->addContent('Part 2')
 *     // ...
 *     ->complete();
 * ```
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class Upload extends BaseObject
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
     * @var array additional file document contents.
     * Common GridFS columns:
     *
     * - metadata: array, additional data associated with the file.
     * - aliases: array, an array of aliases.
     * - contentType: string, content type to be stored with the file.
     */
    public $document = [];
    /**
     * @var int chunk size in bytes.
     */
    public $chunkSize = 261120;
    /**
     * @var int total upload length in bytes.
     */
    public $length = 0;
    /**
     * @var int file chunk counts.
     */
    public $chunkCount = 0;

    /**
     * @var ObjectID file document ID.
     */
    private $_documentId;
    /**
     * @var resource has context for collecting md5 hash
     */
    private $_hashContext;
    /**
     * @var string internal data buffer
     */
    private $_buffer;
    /**
     * @var bool indicates whether upload is complete or not.
     */
    private $_isComplete = false;


    /**
     * Destructor.
     * Makes sure abandoned upload is cancelled.
     */
    public function __destruct()
    {
        if (!$this->_isComplete) {
            $this->cancel();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->_hashContext = hash_init('md5');

        if (isset($this->document['_id'])) {
            if ($this->document['_id'] instanceof ObjectID) {
                $this->_documentId = $this->document['_id'];
            } else {
                try {
                    $this->_documentId = new ObjectID($this->document['_id']);
                } catch (InvalidArgumentException $e) {
                    // invalid id format
                    $this->_documentId = $this->document['_id'];
                }
            }
        } else {
            $this->_documentId = new ObjectID();
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
        $freeBufferLength = $this->chunkSize - StringHelper::byteLength($this->_buffer);
        $contentLength = StringHelper::byteLength($content);
        if ($contentLength > $freeBufferLength) {
            $this->_buffer .= StringHelper::byteSubstr($content, 0, $freeBufferLength);
            $this->flushBuffer(true);
            return $this->addContent(StringHelper::byteSubstr($content, $freeBufferLength));
        } else {
            $this->_buffer .= $content;
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
            $freeBufferLength = $this->chunkSize - StringHelper::byteLength($this->_buffer);

            $streamChunk = fread($stream, $freeBufferLength);
            if ($streamChunk === false) {
                break;
            }
            $this->_buffer .= $streamChunk;
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

        $document = $this->insertFile();

        $this->_isComplete = true;

        return $document;
    }

    /**
     * Cancels the upload.
     */
    public function cancel()
    {
        $this->_buffer = null;

        $this->collection->getChunkCollection()->remove(['files_id' => $this->_documentId], ['limit' => 0]);
        $this->collection->remove(['_id' => $this->_documentId], ['limit' => 1]);

        $this->_isComplete = true;
    }

    /**
     * Flushes [[buffer]] to the chunk if it is full.
     * @param bool $force whether to enforce flushing.
     */
    private function flushBuffer($force = false)
    {
        if ($this->_buffer === null) {
            return;
        }

        if ($force || StringHelper::byteLength($this->_buffer) == $this->chunkSize) {
            $this->insertChunk($this->_buffer);
            $this->_buffer = null;
        }
    }

    /**
     * Inserts file chunk.
     * @param string $data chunk binary content.
     */
    private function insertChunk($data)
    {
        $chunkDocument = [
            'files_id' => $this->_documentId,
            'n' => $this->chunkCount,
            'data' => new Binary($data, Binary::TYPE_GENERIC),
        ];

        hash_update($this->_hashContext, $data);

        $this->collection->getChunkCollection()->insert($chunkDocument);
        $this->length += StringHelper::byteLength($data);
        $this->chunkCount++;
    }

    /**
     * Inserts [[document]] into file collection.
     * @return array inserted file document data.
     */
    private function insertFile()
    {
        $fileDocument = [
            '_id' => $this->_documentId,
            'uploadDate' => new UTCDateTime(round(microtime(true) * 1000)),
        ];
        if ($this->filename === null) {
            $fileDocument['filename'] = $this->_documentId . '.dat';
        } else {
            $fileDocument['filename'] = $this->filename;
        }

        $fileDocument = array_merge(
            $fileDocument,
            $this->document,
            [
                'chunkSize' => $this->chunkSize,
                'length' => $this->length,
                'md5' => hash_final($this->_hashContext),
            ]
        );

        $this->collection->insert($fileDocument);
        return $fileDocument;
    }
}