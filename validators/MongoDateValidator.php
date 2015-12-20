<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\validators;

use yii\validators\DateValidator;

/**
 * MongoDateValidator is an enhanced version of [[DateValidator]], which supports [[\MongoDate]] values.
 *
 * Usage example:
 *
 * ~~~
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
 * ~~~
 *
 * @see DateValidator
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
class MongoDateValidator extends DateValidator
{
    /**
     * @var string the name of the attribute to receive the parsing result as [[\MongoDB\BSON\UTCDatetime]] instance.
     * When this property is not null and the validation is successful, the named attribute will
     * receive the parsing result as [[\MongoDB\BSON\UTCDatetime]] instance.
     *
     * This can be the same attribute as the one being validated. If this is the case,
     * the original value will be overwritten with the value after successful validation.
     */
    public $mongoDateAttribute;

    /**
     * @inheritdoc
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
                if (!($mongoDateAttributeValue instanceof \MongoDB\BSON\UTCDatetime) || $mongoDateAttributeValue->toDateTime()->format('U') !== $timestamp) {
                    // Must be 64bit integer
                    $milliseconds = (string)$timestamp . '000';
                    $model->{$this->mongoDateAttribute} = new \MongoDB\BSON\UTCDatetime($milliseconds);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function parseDateValue($value)
    {
        if ($value instanceof \MongoDB\BSON\UTCDatetime) {
            /** @var \DateTime $datetime */
            $datetime = $value->toDateTime();
            return $datetime->format('U');
        }
        return parent::parseDateValue($value);
    }
}