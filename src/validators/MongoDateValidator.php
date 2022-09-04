<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb\validators;

use MongoDB\BSON\UTCDateTime;
use yii\validators\DateValidator;

/**
 * MongoDateValidator is an enhanced version of [[DateValidator]], which supports [[\MongoDate]] values.
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
 *             ['date', 'yii\mongodb\validators\MongoDateValidator', 'format' => 'MM/dd/yyyy']
 *         ];
 *     }
 * }
 * ```
 *
 * @see DateValidator
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
class MongoDateValidator extends DateValidator
{
    /**
     * @var string the name of the attribute to receive the parsing result as [[\MongoDate]] instance.
     * When this property is not null and the validation is successful, the named attribute will
     * receive the parsing result as [[\MongoDate]] instance.
     *
     * This can be the same attribute as the one being validated. If this is the case,
     * the original value will be overwritten with the value after successful validation.
     */
    public $mongoDateAttribute;


    /**
     * {@inheritdoc}
     */
    public function validateAttribute($model, $attribute)
    {
        $mongoDateAttribute = $this->mongoDateAttribute;
        if ($this->timestampAttribute === null) {
            $this->timestampAttribute = $mongoDateAttribute;
        }

        $originalErrorCount = count($model->getErrors($attribute));
        parent::validateAttribute($model, $attribute);
        $afterValidateErrorCount = count($model->getErrors($attribute));

        if ($originalErrorCount === $afterValidateErrorCount) {
            if ($this->mongoDateAttribute !== null) {
                $timestamp = $model->{$this->timestampAttribute};
                $mongoDateAttributeValue = $model->{$this->mongoDateAttribute};
                // ensure "dirty attributes" support :
                if (!($mongoDateAttributeValue instanceof UTCDateTime) || $mongoDateAttributeValue->sec !== $timestamp) {
                    $model->{$this->mongoDateAttribute} = new UTCDateTime($timestamp * 1000);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function parseDateValue($value)
    {
        return $value instanceof UTCDateTime
            ? $value->toDateTime()->getTimestamp()
            : parent::parseDateValue($value);
    }
}