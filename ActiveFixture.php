<?php

namespace yii\mongodb;


class ActiveFixture extends \yii\test\BaseActiveFixture
{

	public $db = 'mongodb';
	/**
	 * @var string the name of the database table that this fixture is about. If this property is not set,
	 * the table name will be determined via [[modelClass]].
	 * @see modelClass
	 */
	public $collectionName;
	public $loadSchema = false;
	/**
	 * @var string the file path or path alias of the data file that contains the fixture data
	 * and will be loaded by [[loadData()]]. If this is not set, it will default to `FixturePath/data/TableName.php`,
	 * where `FixturePath` stands for the directory containing this fixture class, and `TableName` stands for the
	 * name of the table associated with this fixture.
	 */
	public $dataFile;
	/**
	 * @var boolean whether to reset the table associated with this fixture.
	 * By setting this property to be true, when [[loadData()]] is called, all existing data in the table
	 * will be removed and the sequence number (if any) will be reset.
	 *
	 * Note that you normally do not need to reset the table if you implement [[loadSchema()]] because
	 * there will be no existing data.
	 */
	public $resetTable = false;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		if (!isset($this->modelClass) && !isset($this->collectionName)) {
			throw new InvalidConfigException('Either "modelClass" or "collectionName" must be set.');
		}
	}


	/**
	 * Loads the fixture data.
	 * The default implementation will first reset the DB table and then populate it with the data
	 * returned by [[getData()]].
	 */
	protected function loadData()
	{
		if ($this->resetTable) {
			$this->resetTable();
		}
		$this->getCollection()->insert($this->getData());
		foreach ($this->getData() as $alias => $row) {
			$this->data[$alias] = $row;
		}
	}

	protected function getCollection() {
		return $this->db->getCollection($this->getCollectionName());
	}

	protected function getCollectionName()
	{
		if ($this->collectionName) {
			return $this->collectionName;
		} else {
			$modelClass = $this->modelClass;
			return $modelClass::collectionName();
		}
	}

	/**
	 * Returns the fixture data.
	 *
	 * This method is called by [[loadData()]] to get the needed fixture data.
	 *
	 * The default implementation will try to return the fixture data by including the external file specified by [[dataFile]].
	 * The file should return an array of data rows (column name => column value), each corresponding to a row in the table.
	 *
	 * If the data file does not exist, an empty array will be returned.
	 *
	 * @return array the data rows to be inserted into the database table.
	 */
	protected function getData()
	{
		if ($this->dataFile === false) {
			return [];
		}
		if ($this->dataFile !== null) {
			$dataFile = Yii::getAlias($this->dataFile);
		} else {
			$class = new \ReflectionClass($this);
			$dataFile = dirname($class->getFileName()) . '/data/' . $this->getCollectionName() . '.php';
		}
		return is_file($dataFile) ? require($dataFile) : [];
	}

	/**
	 * Removes all existing data from the specified table and resets sequence number if any.
	 * This method is called before populating fixture data into the table associated with this fixture.
	 */
	protected function resetTable()
	{
		$this->getCollection()->remove();
	}
}