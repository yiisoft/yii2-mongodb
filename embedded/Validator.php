<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\embedded;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;

/**
 * Validator validates embedded entities as nested models.
 * This validator may be applied only for the model, which implements [[ContainerInterface]] interface.
 *
 * ~~~php
 * class User extends Model implements ContainerInterface
 * {
 *     use ContainerTrait;
 *
 *     public $contactData;
 *
 *     public function embedContact()
 *     {
 *         return $this->mapEmbedded('contactData', Contact::className());
 *     }
 *
 *     public function rules()
 *     {
 *         return [
 *             ['contact', 'yii\mongodb\embedded\Validator'],
 *         ]
 *     }
 * }
 *
 * class Contact extends Model
 * {
 *     public $email;
 *
 *     public function rules()
 *     {
 *         return [
 *             ['email', 'required'],
 *             ['email', 'email'],
 *         ]
 *     }
 * }
 * ~~~
 *
 * @see ContainerInterface
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
class Validator extends \yii\validators\Validator
{
    /**
     * @var boolean whether to add an error message to embedded source attribute instead of embedded name itself.
     */
    public $addErrorToSource = true;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} is invalid.');
        }
    }

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        if (!($model instanceof ContainerInterface)) {
            throw new InvalidConfigException('Owner model must implement "yii\mongodb\embedded\ContainerInterface" interface.');
        }

        $mapping = $model->getEmbeddedMapping($attribute);
        $embedded = $model->getEmbedded($attribute);

        if ($mapping->multiple) {
            if (!is_array($embedded) && !($embedded instanceof \IteratorAggregate)) {
                $error = $this->message;
            } else {
                foreach ($embedded as $embeddedModel) {
                    if (!($embeddedModel instanceof Model)) {
                        throw new InvalidConfigException('Embedded object "' . get_class($embeddedModel) . '" must be an instance or descendant of "' . Model::className() . '".');
                    }
                    if (!$embeddedModel->validate()) {
                        $error = $this->message;
                    }
                }
            }
        } else {
            if (!($embedded instanceof Model)) {
                throw new InvalidConfigException('Embedded object "' . get_class($embedded) . '" must be an instance or descendant of "' . Model::className() . '".');
            }
            if (!$embedded->validate()) {
                $error = $this->message;
            }
        }

        if (!empty($error)) {
            $this->addError($model, $this->addErrorToSource ? $mapping->source : $attribute, $error);
        }
    }
} 