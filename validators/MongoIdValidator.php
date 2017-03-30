<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\validators;

use MongoDB\BSON\ObjectID;
use yii\base\InvalidConfigException;
use yii\validators\Validator;
use Yii;

/**
 * MongoIdValidator verifies if the attribute is a valid Mongo ID.
 * Attribute will be considered as valid, if it is an instance of [[\MongoId]] or its string value.
 *
 * Usage example:
 *
 * ```php
 * class Customer extends yii\mongodb\ActiveRecord
 * {
 *     ...
 *     public function rules()
 *     {
 *         return [
 *             ['_id', 'yii\mongodb\validators\MongoIdValidator']
 *         ];
 *     }
 * }
 * ```
 *
 * In order to validate an array of Mongo IDs, enable [[expectArray]] option.
 * This validator may also serve as a filter, allowing conversion of Mongo ID value either to the plain string
 * or to [[\MongoId]] instance. You can enable this feature via [[forceFormat]].
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
class MongoIdValidator extends Validator
{
    /**
     * @var bool whether to expect array type attribute.
     */
    public $expectArray = false;

    /**
     * @var string user-defined error message used when the value is not an array.
     */
    public $notArray;

    /**
     * @var string|null specifies the format, which validated attribute value should be converted to
     * in case validation was successful.
     * valid values are:
     * - 'string' - enforce value converted to plain string.
     * - 'object' - enforce value converted to [[\MongoId]] instance.
     *   If not set - no conversion will be performed, leaving attribute value intact.
     */
    public $forceFormat;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if ($this->notArray === null) {
            $this->notArray = Yii::t('yii', '{attribute} must be an array.');
        }

        if ($this->message === null) {
            $this->message = Yii::t('yii', $this->expectArray ? '{attribute} are invalid.' : '{attribute} is invalid.');
        }
    }

    /**
     * Validates a single attribute.
     * Converts the attribute value to string/object according to [[forceFormat]] if specified.
     * With [[expectArray]] option enabled, checks that the value is traversable and applies format to each element.
     *
     * @param \yii\base\Model $model the data model to be validated.
     * @param string $attribute the name of the attribute to be validated.
     * @throws InvalidConfigException if [[forceFormat]] is invalid.
     */
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;

        if (!$this->expectArray) {
            $list = [$value];
        } elseif (is_array($value) || $value instanceof \Traversable) {
            $list = $value;
        } else {
            $this->addError($model, $attribute, $this->notArray, []);
            return;
        }

        foreach ($list as &$value) {
            $mongoId = $this->parseMongoId($value);
            if (is_object($mongoId)) {
                if ($this->forceFormat !== null) {
                    switch ($this->forceFormat) {
                        case 'string' :
                            $value = $mongoId->__toString();
                            break;
                        case 'object' :
                            $value = $mongoId;
                            break;
                        default:
                            throw new InvalidConfigException("Unrecognized format '{$this->forceFormat}'");
                    }
                }
            } else {
                $this->addError($model, $attribute, $this->message, []);
                return;
            }
        }

        $model->$attribute = $this->expectArray ? $list : $value;
    }

    /**
     * Validates a value out of the context of a data model (arrays are not supported).
     *
     * @param mixed $value the data value to be validated.
     * @return array|null the error message and the parameters to be inserted into the error message,
     * or null if the data is valid.
     */
    protected function validateValue($value)
    {
        return is_object($this->parseMongoId($value)) ? null : [$this->message, []];
    }

    /**
     * Converts a value to Mongo ID.
     *
     * @param mixed $value the value to be converted.
     * @return \ObjectID|null Mongo ID object or null if conversion failed.
     */
    protected function parseMongoId($value)
    {
        if ($value instanceof ObjectID) {
            return $value;
        }
        try {
            return new ObjectID($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
