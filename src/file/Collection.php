<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\file;

use MongoDB\BSON\ObjectID;
use yii\mongodb\Exception;
use Yii;
use yii\web\UploadedFile;

/**
 * Collection represents the Mongo GridFS collection information.
 *
 * A file collection object is usually created by calling [[Database::getFileCollection()]] or [[Connection::getFileCollection()]].
 *
 * File collection inherits all interface from regular [[\yii\mongo\Collection]], adding methods to store files.
 *
 * @property \yii\mongodb\Collection $chunkCollection Mongo collection instance. This property is read-only.
 * @property \yii\mongodb\Collection $fileCollection Mongo collection instance. This property is read-only.
 * @property string $prefix Prefix of this file collection.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Collection extends \yii\mongodb\Collection
{
    /**
     * @var \yii\mongodb\Database MongoDB database instance.
     */
    public $database;

    /**
     * @var string prefix of this file collection.
     */
    private $_prefix;
    /**
     * @var \yii\mongodb\Collection file chunks MongoDB collection.
     */
    private $_chunkCollection;
    /**
     * @var \yii\mongodb\Collection files MongoDB collection.
     */
    private $_fileCollection;
    /**
     * @var bool whether file related fields indexes are ensured for this collection.
     */
    private $indexesEnsured = false;


    /**
     * @return string prefix of this file collection.
     */
    public function getPrefix()
    {
        return $this->_prefix;
    }

    /**
     * @param string $prefix prefix of this file collection.
     */
    public function setPrefix($prefix)
    {
        $this->_prefix = $prefix;
        $this->name = $prefix . '.files';
    }

    /**
     * Creates upload command.
     * @param array $options upload options.
     * @return Upload file upload instance.
     * @since 2.1
     */
    public function createUpload($options = [])
    {
        $config = $options;
        $config['collection'] = $this;
        return new Upload($config);
    }

    /**
     * Creates download command.
     * @param array|ObjectID $document file document ot be downloaded.
     * @return Download file download instance.
     * @since 2.1
     */
    public function createDownload($document)
    {
        return new Download([
            'collection' => $this,
            'document' => $document,
        ]);
    }

    /**
     * Returns the MongoDB collection for the file chunks.
     * @param bool $refresh whether to reload the collection instance even if it is found in the cache.
     * @return \yii\mongodb\Collection mongo collection instance.
     */
    public function getChunkCollection($refresh = false)
    {
        if ($refresh || !is_object($this->_chunkCollection)) {
            $this->_chunkCollection = Yii::createObject([
                'class' => 'yii\mongodb\Collection',
                'database' => $this->database,
                'name' => $this->getPrefix() . '.chunks'
            ]);
        }

        return $this->_chunkCollection;
    }

    /**
     * Returns the MongoDB collection for the files.
     * @param bool $refresh whether to reload the collection instance even if it is found in the cache.
     * @return \yii\mongodb\Collection mongo collection instance.
     * @since 2.1
     */
    public function getFileCollection($refresh = false)
    {
        if ($refresh || !is_object($this->_fileCollection)) {
            $this->_fileCollection = Yii::createObject([
                'class' => 'yii\mongodb\Collection',
                'database' => $this->database,
                'name' => $this->name
            ]);
        }

        return $this->_fileCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function drop()
    {
        return parent::drop() && $this->database->dropCollection($this->getChunkCollection()->name);
    }

    /**
     * {@inheritdoc}
     * @return Cursor cursor for the search results
     */
    public function find($condition = [], $fields = [], $options = [])
    {
        return new Cursor($this, parent::find($condition, $fields, $options));
    }

    /**
     * {@inheritdoc}
     */
    public function remove($condition = [], $options = [])
    {
        $fileCollection = $this->getFileCollection();
        $chunkCollection = $this->getChunkCollection();

        if (empty($condition) && empty($options['limit'])) {
            // truncate :
            $deleteCount = $fileCollection->remove([], $options);
            $chunkCollection->remove([], $options);
            return $deleteCount;
        }

        $batchSize = 200;
        $options['batchSize'] = $batchSize;
        $cursor = $fileCollection->find($condition, ['_id'], $options);
        unset($options['limit']);
        $deleteCount = 0;
        $deleteCallback = function ($ids) use ($fileCollection, $chunkCollection, $options) {
            $chunkCollection->remove(['files_id' => ['$in' => $ids]], $options);
            return $fileCollection->remove(['_id' => ['$in' => $ids]], $options);
        };

        $ids = [];
        $idsCount = 0;
        foreach ($cursor as $row) {
            $ids[] = $row['_id'];
            $idsCount++;
            if ($idsCount >= $batchSize) {
                $deleteCount += $deleteCallback($ids);
                $ids = [];
                $idsCount = 0;
            }
        }

        if (!empty($ids)) {
            $deleteCount += $deleteCallback($ids);
        }

        return $deleteCount;
    }

    /**
     * Creates new file in GridFS collection from given local filesystem file.
     * Additional attributes can be added file document using $metadata.
     * @param string $filename name of the file to store.
     * @param array $metadata other metadata fields to include in the file document.
     * @param array $options list of options in format: optionName => optionValue
     * @return mixed the "_id" of the saved file document. This will be a generated [[\MongoId]]
     * unless an "_id" was explicitly specified in the metadata.
     * @throws Exception on failure.
     */
    public function insertFile($filename, $metadata = [], $options = [])
    {
        $options['document'] = $metadata;
        $document = $this->createUpload($options)->addFile($filename)->complete();
        return $document['_id'];
    }

    /**
     * Creates new file in GridFS collection with specified content.
     * Additional attributes can be added file document using $metadata.
     * @param string $bytes string of bytes to store.
     * @param array $metadata other metadata fields to include in the file document.
     * @param array $options list of options in format: optionName => optionValue
     * @return mixed the "_id" of the saved file document. This will be a generated [[\MongoId]]
     * unless an "_id" was explicitly specified in the metadata.
     * @throws Exception on failure.
     */
    public function insertFileContent($bytes, $metadata = [], $options = [])
    {
        $options['document'] = $metadata;
        $document = $this->createUpload($options)->addContent($bytes)->complete();
        return $document['_id'];
    }

    /**
     * Creates new file in GridFS collection from uploaded file.
     * Additional attributes can be added file document using $metadata.
     * @param string $name name of the uploaded file to store. This should correspond to
     * the file field's name attribute in the HTML form.
     * @param array $metadata other metadata fields to include in the file document.
     * @param array $options list of options in format: optionName => optionValue
     * @return mixed the "_id" of the saved file document. This will be a generated [[\MongoId]]
     * unless an "_id" was explicitly specified in the metadata.
     * @throws Exception on failure.
     */
    public function insertUploads($name, $metadata = [], $options = [])
    {
        $uploadedFile = UploadedFile::getInstanceByName($name);
        if ($uploadedFile === null) {
            throw new Exception("Uploaded file '{$name}' does not exist.");
        }

        $options['filename'] = $uploadedFile->name;
        $options['document'] = $metadata;
        $document = $this->createUpload($options)->addFile($uploadedFile->tempName)->complete();
        return $document['_id'];
    }

    /**
     * Retrieves the file with given _id.
     * @param mixed $id _id of the file to find.
     * @return Download|null found file, or null if file does not exist
     * @throws Exception on failure.
     */
    public function get($id)
    {
        $document = $this->getFileCollection()->findOne(['_id' => $id]);
        return empty($document) ? null : $this->createDownload($document);
    }

    /**
     * Deletes the file with given _id.
     * @param mixed $id _id of the file to find.
     * @return bool whether the operation was successful.
     * @throws Exception on failure.
     */
    public function delete($id)
    {
        $this->remove(['_id' => $id], ['limit' => 1]);
        return true;
    }

    /**
     * Makes sure that indexes, which are crucial for the file processing,
     * exist at this collection and [[chunkCollection]].
     * The check result is cached per collection instance.
     * @param bool $force whether to ignore internal collection instance cache.
     * @return $this self reference.
     */
    public function ensureIndexes($force = false)
    {
        if (!$force && $this->indexesEnsured) {
            return $this;
        }

        $this->ensureFileIndexes();
        $this->ensureChunkIndexes();

        $this->indexesEnsured = true;
        return $this;
    }

    /**
     * Ensures indexes at file collection.
     */
    private function ensureFileIndexes()
    {
        $indexKey = ['filename' => 1, 'uploadDate' => 1];
        foreach ($this->listIndexes() as $index) {
            if ($index['key'] === $indexKey) {
                return;
            }
        }

        $this->createIndex($indexKey);
    }

    /**
     * Ensures indexes at chunk collection.
     */
    private function ensureChunkIndexes()
    {
        $chunkCollection = $this->getChunkCollection();
        $indexKey = ['files_id' => 1, 'n' => 1];
        foreach ($chunkCollection->listIndexes() as $index) {
            if (!empty($index['unique']) && $index['key'] === $indexKey) {
                return;
            }
        }
        $chunkCollection->createIndex($indexKey, ['unique' => true]);
    }
}
