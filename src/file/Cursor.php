<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb\file;

/**
 * Cursor is a wrapper around [[\MongoDB\Driver\Cursor]], which allows returning of the
 * record with [[Download]] instance attached.
 *
 * @method \MongoDB\Driver\Cursor getInnerIterator()
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class Cursor extends \IteratorIterator implements \Countable
{
    /**
     * @var Collection related GridFS collection instance.
     */
    public $collection;


    /**
     * Constructor.
     * @param Collection $collection
     * @param \MongoDB\Driver\Cursor $cursor
     */
    public function __construct($collection, $cursor)
    {
        $this->collection = $collection;
        parent::__construct($cursor);
    }

    /**
     * Return the current element
     * This method is required by the interface [[\Iterator]].
     * @return mixed current row
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        $value = parent::current();
        if (!isset($value['file'])) {
            $value['file'] = $this->collection->createDownload(array_intersect_key($value, ['_id' => true, 'filename' => true, 'length' => true, 'chunkSize' => true]));
        }
        return $value;
    }

    /**
     * Count elements of this cursor.
     * This method is required by the interface [[\Countable]].
     * @return int elements count.
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->cursor);
    }

    // Mock up original cursor interface :

    /**
     * Returns an array containing all results for this cursor
     * @return array containing all results for this cursor.
     */
    public function toArray()
    {
        $result = [];
        foreach ($this as $key => $value) {
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Returns the ID for this cursor.
     * @return \MongoDB\Driver\CursorId cursor ID.
     */
    public function getId()
    {
        return $this->getInnerIterator()->getId();
    }

    /**
     * Sets a type map to use for BSON unserialization.
     * @param array $typemap type map.
     */
    public function setTypeMap($typemap)
    {
        $this->getInnerIterator()->setTypeMap($typemap);
    }

    /**
     * PHP magic method, which is invoked on attempt of invocation not existing method.
     * It redirects method call to inner iterator.
     * @param string $name method name.
     * @param array $arguments method arguments
     * @return mixed method result.
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->getInnerIterator(), $name], $arguments);
    }

    /**
     * PHP magic method, which is invoked on attempt of setting not existing property.
     * It passes value to the inner iterator.
     * @param string $name field name.
     * @param mixed $value field value.
     */
    public function __set($name, $value)
    {
        $this->getInnerIterator()->{$name} = $value;
    }

    /**
     * PHP magic method, which is invoked on attempt of getting not existing property.
     * It returns value from the inner iterator.
     * @param string $name field name.
     * @return mixed field value.
     */
    public function __get($name)
    {
        return $this->getInnerIterator()->{$name};
    }

    /**
     * PHP magic method, which is invoked on attempt of checking if a property is set.
     * @param string $name field name.
     * @return bool whether field exists or not.
     */
    public function __isset($name)
    {
        $cursor = $this->getInnerIterator();
        return isset($cursor->$name);
    }

    /**
     * PHP magic method, which is invoked on attempt of unsetting of property.
     * @param string $name field name.
     */
    public function __unset($name)
    {
        $cursor = $this->getInnerIterator();
        unset($cursor->$name);
    }
}
