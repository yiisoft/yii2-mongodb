<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\file;

use yii\base\InvalidConfigException;
use yii\base\Object;
use yii\di\Instance;
use yii\helpers\StringHelper;
use yii\mongodb\Connection;

/**
 * StreamWrapper provides stream wrapper for MongoDB GridFS, allowing file operations via
 * regular PHP stream resources.
 *
 * Before feature can be used this wrapper should be registered via [[register()]] method.
 * It is usually performed via [[yii\mongodb\Connection::registerFileStreamWrapper()]].
 *
 * Note: do not use this class directly - its instance will be created and maintained by PHP internally
 * once corresponding stream resource is created.
 *
 * Resource path should be specified in following format:
 *
 * ```
 * 'protocol://databaseName.fileCollectionPrefix?file_attribute=value'
 * ```
 *
 * Write example:
 *
 * ```php
 * $resource = fopen('gridfs://mydatabase.fs?filename=new_file.txt', 'w');
 * fwrite($resource, 'some content');
 * // ...
 * fclose($resource);
 * ```
 *
 * Read example:
 *
 * ```php
 * $resource = fopen('gridfs://mydatabase.fs?filename=my_file.txt', 'r');
 * $fileContent = stream_get_contents($resource);
 * ```
 *
 * @see http://php.net/manual/en/function.stream-wrapper-register.php
 *
 * @property array $contextOptions Context options. This property is read-only.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class StreamWrapper extends Object
{
    /**
     * @var resource associated stream resource context.
     * This property is set automatically by PHP once wrapper is instantiated.
     */
    public $context;

    /**
     * @var array context options associated with [[context]].
     */
    private $_contextOptions;
    /**
     * @var string protocol associated with stream
     */
    private $protocol;
    /**
     * @var string namespace in format 'databaseName.collectionName' associated with stream.
     */
    private $namespace;
    /**
     * @var array query parameters passed for the stream.
     */
    private $queryParams = [];
    /**
     * @var Upload file upload instance
     */
    private $upload;
    /**
     * @var Download file upload instance
     */
    private $download;
    /**
     * @var integer file pointer offset.
     */
    private $pointerOffset = 0;


    /**
     * Registers this steam wrapper.
     * @param string $protocol name of the protocol to be used.
     * @param boolean $force whether to register wrapper, even if protocol is already taken.
     */
    public static function register($protocol = 'gridfs', $force = false)
    {
        if (in_array($protocol, stream_get_wrappers())) {
            if (!$force) {
                return;
            }
            stream_wrapper_unregister($protocol);
        }

        stream_wrapper_register($protocol, get_called_class(), STREAM_IS_URL);
    }

    /**
     * Returns options associated with [[context]].
     * @return array context options.
     */
    public function getContextOptions()
    {
        if ($this->_contextOptions === null) {
            $this->_contextOptions = stream_context_get_options($this->context);
        }
        return $this->_contextOptions;
    }

    /**
     * Parses stream open path, initializes internal parameters.
     * @param string $path stream open path.
     */
    private function parsePath($path)
    {
        $pathInfo = parse_url($path);

        $this->protocol = $pathInfo['scheme'];
        $this->namespace = $pathInfo['host'];
        parse_str($pathInfo['query'], $this->queryParams);
    }

    /**
     * Prepares [[Download]] instance for the read operations.
     * @return boolean success.
     * @throws InvalidConfigException on invalid context configuration.
     */
    private function prepareDownload()
    {
        $contextOptions = $this->getContextOptions();
        if (isset($contextOptions[$this->protocol]['download'])) {
            $download = $contextOptions[$this->protocol]['download'];
            if (!$download instanceof Download) {
                throw new InvalidConfigException('"download" context option should be an instance of "' . Download::className() . '"');
            }
            $this->download = $download;
            return true;
        }

        $collection = $this->fetchCollection();
        if (empty($this->queryParams)) {
            return false;
        }
        $file = $collection->findOne($this->queryParams);
        if (empty($file)) {
            throw new InvalidConfigException('Requested file does not exits.');
        }

        $this->download = $file['file'];
        return true;
    }

    /**
     * Prepares [[Upload]] instance for the write operations.
     * @return boolean success.
     * @throws InvalidConfigException on invalid context configuration.
     */
    private function prepareUpload()
    {
        $contextOptions = $this->getContextOptions();
        if (isset($contextOptions[$this->protocol]['upload'])) {
            $upload = $contextOptions[$this->protocol]['upload'];
            if (!$upload instanceof Upload) {
                throw new InvalidConfigException('"upload" context option should be an instance of "' . Upload::className() . '"');
            }
            $this->upload = $upload;
            return true;
        }

        $collection = $this->fetchCollection();
        $this->upload = $collection->createUpload(['document' => $this->queryParams]);
        return true;
    }

    /**
     * Fetches associated file collection from stream options.
     * @return Collection file collection instance.
     * @throws InvalidConfigException on invalid stream options.
     */
    private function fetchCollection()
    {
        $contextOptions = $this->getContextOptions();

        if (isset($contextOptions[$this->protocol]['collection'])) {
            $collection = $contextOptions[$this->protocol]['collection'];
            if ($collection instanceof Collection) {
                throw new InvalidConfigException('"collection" context option should be an instance of "' . Collection::className() . '"');
            }

            return $collection;
        }

        if (isset($contextOptions[$this->protocol]['db'])) {
            $connection = $contextOptions[$this->protocol]['db'];
        } else {
            $connection = 'mongodb';
        }
        /* @var $connection Connection */
        $connection = Instance::ensure($connection, Connection::className());

        list($databaseName, $collectionPrefix) = explode('.', $this->namespace, 2);
        return $connection->getDatabase($databaseName)->getFileCollection($collectionPrefix);
    }

    /**
     * Default template for file statistic data set.
     * @see stat()
     * @return array statistic information.
     */
    private function fileStatisticsTemplate()
    {
        return [
            0  => 0,  'dev'     => 0,
            1  => 0,  'ino'     => 0,
            2  => 0,  'mode'    => 0,
            3  => 0,  'nlink'   => 0,
            4  => 0,  'uid'     => 0,
            5  => 0,  'gid'     => 0,
            6  => -1, 'rdev'    => -1,
            7  => 0,  'size'    => 0,
            8  => 0,  'atime'   => 0,
            9  => 0,  'mtime'   => 0,
            10 => 0,  'ctime'   => 0,
            11 => -1, 'blksize' => -1,
            12 => -1, 'blocks'  => -1,
        ];
    }

    // Stream Interface :

    /**
     * Closes a resource.
     * This method is called in response to `fclose()`.
     * @see fclose()
     */
    public function stream_close()
    {
        if ($this->upload !== null) {
            $this->upload->complete();
            $this->upload = null;
        }
        if ($this->download !== null) {
            $this->download = null;
        }
    }

    /**
     * Tests for end-of-file on a file pointer.
     * This method is called in response to `feof()`.
     * @see feof()
     * @return boolean `true` if the read/write position is at the end of the stream and
     * if no more data is available to be read, or `false` otherwise.
     */
    public function stream_eof()
    {
        if ($this->download !== null) {
            return ($this->pointerOffset >= $this->download->getSize());
        }
        return true;
    }

    /**
     * Opens file.
     * This method is called immediately after the wrapper is initialized (f.e. by `fopen()` and `file_get_contents()`).
     * @see fopen()
     * @param string $path specifies the URL that was passed to the original function.
     * @param string $mode mode used to open the file, as detailed for `fopen()`.
     * @param integer $options additional flags set by the streams API.
     * @param string $openedPath real opened path.
     * @return boolean whether operation is successful.
     */
    public function stream_open($path, $mode, $options, &$openedPath)
    {
        if ($options & STREAM_USE_PATH) {
            $openedPath = $path;
        }

        $this->parsePath($path);

        switch ($mode) {
            case 'r':
                return $this->prepareDownload();
            case 'w':
                return $this->prepareUpload();
        }
        return false;
    }

    /**
     * Reads from stream.
     * This method is called in response to `fread()` and `fgets()`.
     * @see fread()
     * @param integer $count count of bytes of data from the current position should be returned.
     * @return string|false if there are less than count bytes available, return as many as are available.
     * If no more data is available, return `false`.
     */
    public function stream_read($count)
    {
        if ($this->download === null) {
            return false;
        }
        $result = $this->download->substr($this->pointerOffset, $count);
        $this->pointerOffset += $count;
        return $result;
    }

    /**
     * Writes to stream.
     * This method is called in response to `fwrite()`.
     * @see fwrite()
     * @param string $data string to be stored into the underlying stream.
     * @return integer the number of bytes that were successfully stored.
     */
    public function stream_write($data)
    {
        if ($this->upload === null) {
            return false;
        }
        $this->upload->addContent($data);
        $result = StringHelper::byteLength($data);
        $this->pointerOffset += $result;
        return $result;
    }

    /**
     * This method is called in response to `fflush()` and when the stream is being closed
     * while any unflushed data has been written to it before.
     * @see fflush()
     * @return boolean whether cached data was successfully stored.
     */
    public function stream_flush()
    {
        return true;
    }

    /**
     * Retrieve information about a file resource.
     * This method is called in response to `stat()`.
     * @see stat()
     * @return array file statistic information.
     */
    public function stream_stat()
    {
        $statistics = $this->fileStatisticsTemplate();

        if ($this->download !== null) {
            $statistics[7] = $statistics['size'] = $this->download->getSize();
        }
        if ($this->upload !== null) {
            $statistics[7] = $statistics['size'] = $this->pointerOffset;
        }

        return $statistics;
    }
}