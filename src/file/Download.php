<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb\file;

use MongoDB\BSON\ObjectID;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\BaseObject;
use yii\helpers\FileHelper;
use yii\helpers\StringHelper;

/**
 * Download represents the GridFS download operation.
 *
 * A `Download` object is usually created by calling [[Collection::get()]] or [[Collection::createDownload()]].
 *
 * Usage example:
 *
 * ```php
 * Yii::$app->mongodb->getFileCollection()->createDownload($document['_id'])->toFile('/path/to/file.dat');
 * ```
 *
 * You can use `Download::substr()` to read a specific part of the file:
 *
 * ```php
 * $filePart = Yii::$app->mongodb->getFileCollection()->createDownload($document['_id'])->substr(256, 1024);
 * ```
 *
 * @property-read string $bytes File content.
 * @property-read \MongoDB\Driver\Cursor $chunkCursor Chuck list cursor.
 * @property-read \Iterator $chunkIterator Chuck cursor iterator.
 * @property array $document Document to be downloaded. Note that the type of this property differs in getter
 * and setter. See [[getDocument()]] and [[setDocument()]] for details.
 * @property-read string|null $filename File name.
 * @property-read resource $resource File stream resource.
 * @property-read int $size File size.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class Download extends BaseObject
{
    /**
     * @var Collection file collection to be used.
     */
    public $collection;

    /**
     * @var array|ObjectID document to be downloaded.
     */
    private $_document;
    /**
     * @var \MongoDB\Driver\Cursor cursor for the file chunks.
     */
    private $_chunkCursor;
    /**
     * @var \Iterator iterator for [[chunkCursor]].
     */
    private $_chunkIterator;
    /**
     * @var resource|null
     */
    private $_resource;


    /**
     * @return array document to be downloaded.
     * @throws InvalidConfigException on invalid document configuration.
     */
    public function getDocument()
    {
        if (!is_array($this->_document)) {
            if (is_scalar($this->_document) || $this->_document instanceof ObjectID) {
                $document = $this->collection->findOne(['_id' => $this->_document]);
                if (empty($document)) {
                    throw new InvalidConfigException('Document id=' . $this->_document . ' does not exist at collection "' . $this->collection->getFullName() . '"');
                }
                $this->_document = $document;
            } else {
                $this->_document = (array)$this->_document;
            }
        }
        return $this->_document;
    }

    /**
     * Sets data of the document to be downloaded.
     * Document can be specified by its ID, in this case its data will be fetched automatically
     * via extra query.
     * @param array|ObjectID $document document raw data or document ID.
     */
    public function setDocument($document)
    {
        $this->_document = $document;
    }

    /**
     * Returns the size of the associated file.
     * @return int file size.
     */
    public function getSize()
    {
        $document = $this->getDocument();
        return isset($document['length']) ? $document['length'] : 0;
    }

    /**
     * Returns associated file's filename.
     * @return string|null file name.
     */
    public function getFilename()
    {
        $document = $this->getDocument();
        return isset($document['filename']) ? $document['filename'] : null;
    }

    /**
     * Returns file chunks read cursor.
     * @param bool $refresh whether to recreate cursor, if it is already exist.
     * @return \MongoDB\Driver\Cursor chuck list cursor.
     * @throws InvalidConfigException
     */
    public function getChunkCursor($refresh = false)
    {
        if ($refresh || $this->_chunkCursor === null) {
            $file = $this->getDocument();
            $this->_chunkCursor = $this->collection->getChunkCollection()->find(
                ['files_id' => $file['_id']],
                [],
                ['sort' => ['n' => 1]]
            );
        }
        return $this->_chunkCursor;
    }

    /**
     * Returns iterator for the file chunks cursor.
     * @param bool $refresh whether to recreate iterator, if it is already exist.
     * @return \Iterator chuck cursor iterator.
     */
    public function getChunkIterator($refresh = false)
    {
        if ($refresh || $this->_chunkIterator === null) {
            $this->_chunkIterator = new \IteratorIterator($this->getChunkCursor($refresh));
            $this->_chunkIterator->rewind();
        }
        return $this->_chunkIterator;
    }

    /**
     * Saves file into the given stream.
     * @param resource $stream stream, which file should be saved to.
     * @return int number of written bytes.
     */
    public function toStream($stream)
    {
        $bytesWritten = 0;
        foreach ($this->getChunkCursor() as $chunk) {
            $bytesWritten += fwrite($stream, $chunk['data']->getData());
        }
        return $bytesWritten;
    }

    /**
     * Saves download to the physical file.
     * @param string $filename name of the physical file.
     * @return int number of written bytes.
     */
    public function toFile($filename)
    {
        $filename = Yii::getAlias($filename);
        FileHelper::createDirectory(dirname($filename));
        return $this->toStream(fopen($filename, 'w+'));
    }

    /**
     * Returns a string of the bytes in the associated file.
     * @return string file content.
     */
    public function toString()
    {
        $result = '';
        foreach ($this->getChunkCursor() as $chunk) {
            $result .= $chunk['data']->getData();
        }
        return $result;
    }

    /**
     * Returns an opened stream resource, which can be used to read file.
     * Note: each invocation of this method will create new file resource.
     * @return resource stream resource.
     */
    public function toResource()
    {
        $protocol = $this->collection->database->connection->registerFileStreamWrapper();

        $context = stream_context_create([
            $protocol => [
                'download' => $this,
            ]
        ]);

        $document = $this->getDocument();
        $url = "{$protocol}://{$this->collection->database->name}.{$this->collection->prefix}?_id={$document['_id']}";
        return fopen($url, 'r', false, $context);
    }

    /**
     * Return part of a file.
     * @param int $start reading start position.
     * If non-negative, the returned string will start at the start'th position in file, counting from zero.
     * If negative, the returned string will start at the start'th character from the end of file.
     * @param int $length number of bytes to read.
     * If given and is positive, the string returned will contain at most length characters beginning from start (depending on the length of file).
     * If given and is negative, then that many characters will be omitted from the end of file (after the start position has been calculated when a start is negative).
     * @return string|false the extracted part of file or `false` on failure
     */
    public function substr($start, $length)
    {
        $document = $this->getDocument();

        if ($start < 0) {
            $start = max($document['length'] + $start, 0);
        }

        if ($start > $document['length']) {
            return false;
        }

        if ($length < 0) {
            $length = $document['length'] - $start + $length;
            if ($length < 0) {
                return false;
            }
        }

        $chunkSize = $document['chunkSize'];

        $startChunkNumber = floor($start / $chunkSize);

        $chunkIterator = $this->getChunkIterator();

        if (!$chunkIterator->valid()) {
            // invalid iterator state - recreate iterator
            // unable to use `rewind` due to error "Cursors cannot rewind after starting iteration"
            $chunkIterator = $this->getChunkIterator(true);
        }

        if ($chunkIterator->key() > $startChunkNumber) {
            // unable to go back by iterator
            // unable to use `rewind` due to error "Cursors cannot rewind after starting iteration"
            $chunkIterator = $this->getChunkIterator(true);
        }

        $result = '';

        $chunkDataOffset = $start - $startChunkNumber * $chunkSize;
        while ($chunkIterator->valid()) {
            if ($chunkIterator->key() >= $startChunkNumber) {
                $chunk = $chunkIterator->current();
                $data = $chunk['data']->getData();

                $readLength = min($chunkSize - $chunkDataOffset, $length);

                $result .= StringHelper::byteSubstr($data, $chunkDataOffset, $readLength);

                $length -= $readLength;
                if ($length <= 0) {
                    break;
                }

                $chunkDataOffset = 0;
            }

            $chunkIterator->next();
        }

        return $result;
    }

    // Compatibility with `MongoGridFSFile` :

    /**
     * Alias of [[toString()]] method.
     * @return string file content.
     */
    public function getBytes()
    {
        return $this->toString();
    }

    /**
     * Alias of [[toFile()]] method.
     * @param string $filename name of the physical file.
     * @return int number of written bytes.
     */
    public function write($filename)
    {
        return $this->toFile($filename);
    }

    /**
     * Returns persistent stream resource, which can be used to read file.
     * @return resource file stream resource.
     */
    public function getResource()
    {
        if ($this->_resource === null) {
            $this->_resource = $this->toResource();
        }
        return $this->_resource;
    }
}