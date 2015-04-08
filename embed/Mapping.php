<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\embed;

use ArrayObject;
use yii\base\InvalidParamException;
use yii\base\Object;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Represents mapping between embed object or object list and its container.
 * It stores declaration of embed policy and handles embed value composition and extraction.
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
     * @var mixed actual embed value.
     */
    private $_value = false;


    /**
     * Sets the embed value.
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
     * Returns actual embed value.
     * @param object $owner owner object.
     * @return object|object[]|null embed value.
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

        if ($this->multiple) {
            $value = new ArrayObject();
            foreach ($sourceValue as $key => $frame) {
                if (!is_array($frame)) {
                    throw new InvalidParamException("Source value for the embed should be an array.");
                }
                $value[$key] = Yii::createObject(array_merge($targetConfig, $frame));
            }
            return $value;
        }

        if ($sourceValue === null) {
            return null;
        }
        if (!is_array($sourceValue)) {
            throw new InvalidParamException("Source value for the embed should be an array.");
        }
        return Yii::createObject(array_merge($targetConfig, $sourceValue));
    }

    /**
     * Extract embed object(s) values as array.
     * @param object $owner owner object
     * @return array|null extracted values.
     */
    public function extractValues($owner)
    {
        $embedValue = $this->getValue($owner);
        if ($embedValue === null) {
            $value = null;
        } else {
            if ($this->multiple) {
                $value = [];
                foreach ($embedValue as $key => $object) {
                    $value[$key] = $this->extractObjectValues($object);
                }
            } else {
                $value = $this->extractObjectValues($embedValue);
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
            $values = array_merge($values, $object->getEmbedValues());
        }
        return $values;
    }
}