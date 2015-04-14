<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\embedded;

use ArrayObject;
use yii\base\InvalidParamException;
use yii\base\Object;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Represents mapping between embedded object or object list and its container.
 * It stores declaration of embedded policy and handles embedded value composition and extraction.
 *
 * @see ContainerTrait
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
class Mapping extends Object
{
    /**
     * @var string name of the container source field or property.
     */
    public $source;
    /**
     * @var string|array target class name or array object configuration.
     */
    public $target;
    /**
     * @var boolean whether list of objects should match the source value.
     */
    public $multiple;
    /**
     * @var boolean whether to create empty object or list of objects, if the source field is null.
     * If disabled [[getValue()]] will produce `null` value from null source.
     */
    public $createFromNull = true;

    /**
     * @var mixed actual embedded value.
     */
    private $_value = false;


    /**
     * Sets the embedded value.
     * @param array|object|null $value actual value.
     * @throws InvalidParamException on invalid argument
     */
    public function setValue($value)
    {
        if (!is_null($value)) {
            if ($this->multiple) {
                if (is_array($value)) {
                    $arrayObject = new ArrayObject();
                    foreach ($value as $k => $v) {
                        $arrayObject[$k] = $v;
                    }
                    $value = $arrayObject;
                } elseif (!($value instanceof \ArrayAccess)) {
                    throw new InvalidParamException("Value should either an array or a null, '" . gettype($value) . "' given.");
                }
            } else {
                if (!is_object($value)) {
                    throw new InvalidParamException("Value should either an object or a null, '" . gettype($value) . "' given.");
                }
            }
        }

        $this->_value = $value;
    }

    /**
     * Returns actual embedded value.
     * @param object $owner owner object.
     * @return object|object[]|null embedded value.
     */
    public function getValue($owner)
    {
        if ($this->_value === false) {
            $this->_value = $this->createValue($owner);
        }
        return $this->_value;
    }

    /**
     * @param object $owner owner object
     * @throws InvalidParamException on invalid source.
     * @return array|null|object value.
     */
    private function createValue($owner)
    {
        if (is_array($this->target)) {
            $targetConfig = $this->target;
        } else {
            $targetConfig = ['class' => $this->target];
        }

        $sourceValue = $owner->{$this->source};
        if ($this->createFromNull && $sourceValue === null) {
            $sourceValue = [];
        }

        if ($this->multiple) {
            $value = new ArrayObject();
            foreach ($sourceValue as $key => $frame) {
                if (!is_array($frame)) {
                    throw new InvalidParamException("Source value for the embedded should be an array.");
                }
                $value[$key] = Yii::createObject(array_merge($targetConfig, $frame));
            }
            return $value;
        }

        if ($sourceValue === null) {
            return null;
        }
        if (!is_array($sourceValue)) {
            throw new InvalidParamException("Source value for the embedded should be an array.");
        }
        return Yii::createObject(array_merge($targetConfig, $sourceValue));
    }

    /**
     * Extract embedded object(s) values as array.
     * @param object $owner owner object
     * @return array|null extracted values.
     */
    public function extractValues($owner)
    {
        $embeddedValue = $this->getValue($owner);
        if ($embeddedValue === null) {
            $value = null;
        } else {
            if ($this->multiple) {
                $value = [];
                foreach ($embeddedValue as $key => $object) {
                    $value[$key] = $this->extractObjectValues($object);
                }
            } else {
                $value = $this->extractObjectValues($embeddedValue);
            }
        }
        return $value;
    }

    /**
     * @param object $object
     * @return array
     */
    private function extractObjectValues($object)
    {
        $values = ArrayHelper::toArray($object);
        if ($object instanceof ContainerInterface) {
            $values = array_merge($values, $object->getEmbeddedValues());
        }
        return $values;
    }
}