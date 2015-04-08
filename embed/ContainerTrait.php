<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\embed;

use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use Yii;

/**
 * ContainerTrait can be used to satisfy [[ContainerInterface]].
 *
 * For each embed entity a mapping declaration should be provided.
 * In order to do so you need to declare method, which name is prefixed with 'embed', which
 * should return the [[Mapping]] instance. You may use [[hasEmbed()]] and [[hasEmbedList()]] for this.
 *
 * Per each of source field or property a new virtual property will declared, which name will be composed
 * by removing 'embed' prefix from the declaration method name.
 *
 * Note: watch for the naming collisions: if you have a source property named 'profile' the mapping declaration
 * for it should have different name, like 'profileModel'.
 *
 * Example:
 *
 * ~~~php
 * use yii\base\Model;
 * use yii\mongodb\embed\ContainerInterface;
 * use yii\mongodb\embed\ContainerTrait;
 *
 * class User extends Model implements ContainerInterface
 * {
 *     use ContainerTrait;
 *
 *     public $profileData = [];
 *     public $commentsData = [];
 *
 *     public function embedProfile()
 *     {
 *         return $this->hasEmbed('profileData', 'Profile');
 *     }
 *
 *     public function embedComments()
 *     {
 *         return $this->hasEmbedList('commentsData', 'Comment');
 *     }
 * }
 *
 * $user = new User();
 * $user->profile->firstName = 'John';
 * $user->profile->lastName = 'Doe';
 *
 * $comment = new Comment();
 * $user->comments[] = $comment;
 * ~~~
 *
 * In order to synchronize values between embed entities and container use [[synchronizeWithEmbed()]] method.
 *
 * @see ContainerInterface
 * @see Mapping
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
trait ContainerTrait
{
    /**
     * @var Mapping[]
     */
    private $_embed = [];

    /**
     * PHP getter magic method.
     * This method is overridden so that embed objects can be accessed like properties.
     *
     * @param string $name property name
     * @throws \yii\base\InvalidParamException if relation name is wrong
     * @return mixed property value
     * @see getAttribute()
     */
    public function __get($name)
    {
        if ($this->embedExists($name)) {
            return $this->getEmbed($name);
        }
        return parent::__get($name);
    }

    /**
     * PHP setter magic method.
     * This method is overridden so that embed objects can be accessed like properties.
     * @param string $name property name
     * @param mixed $value property value
     */
    public function __set($name, $value)
    {
        if ($this->embedExists($name)) {
            $this->setEmbed($name, $value);
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Checks if a property value is null.
     * This method overrides the parent implementation by checking if the embed object is null or not.
     * @param string $name the property name or the event name
     * @return boolean whether the property value is null
     */
    public function __isset($name)
    {
        if (isset($this->_embed[$name])) {
            return ($this->_embed[$name]->getValue($this) === null);
        }
        return parent::__isset($name);
    }

    /**
     * Sets a component property to be null.
     * This method overrides the parent implementation by clearing
     * the specified embed object.
     * @param string $name the property name or the event name
     */
    public function __unset($name)
    {
        if (isset($this->_embed[$name])) {
            ($this->_embed[$name]->setValue(null));
        } else {
            parent::__unset($name);
        }
    }

    /**
     * Sets embed object or list of objects.
     * @param string $name embed name
     * @param object|object[]|null $value embed value.
     */
    public function setEmbed($name, $value)
    {
        $this->fetchEmbedMapping($name)->setValue($value);
    }

    /**
     * Returns embed object or list of objects.
     * @param string $name embed name.
     * @return object|object[]|null embed value.
     */
    public function getEmbed($name)
    {
        return $this->fetchEmbedMapping($name)->getValue($this);
    }

    /**
     * @param string $name embed name
     * @throws \yii\base\InvalidParamException
     * @throws \yii\base\InvalidConfigException
     * @return Mapping embed mapping.
     */
    private function fetchEmbedMapping($name)
    {
        if (!isset($this->_embed[$name])) {
            $method = $this->composeEmbedDeclarationMethodName($name);
            if (!method_exists($this, $method)) {
                throw new InvalidParamException("'" . get_class($this) . "' has no declaration ('{$method}()') for the embed '{$name}'");
            }
            $mapping = call_user_func([$this, $method]);
            if (!$mapping instanceof Mapping) {
                throw new InvalidConfigException("Mapping declaration '" . get_class($this) . "::{$method}()' should return instance of '" . Mapping::className() . "'");
            }
            $this->_embed[$name] = $mapping;
        }
        return $this->_embed[$name];
    }

    /**
     * Checks if asked embed declaration exists.
     * @param string $name embed name
     * @return boolean whether embed declaration exists.
     */
    public function embedExists($name)
    {
        return (isset($this->_embed[$name])) || method_exists($this, $this->composeEmbedDeclarationMethodName($name));
    }

    /**
     * Declares embed object.
     * @param string $source source field name
     * @param string|array $target target class or array configuration.
     * @return Mapping embedding mapping instance.
     */
    public function hasEmbed($source, $target)
    {
        return Yii::createObject([
            'class' => Mapping::className(),
            'source' => $source,
            'target' => $target,
            'multiple' => false,
        ]);
    }

    /**
     * Declares embed list of objects.
     * @param string $source source field name
     * @param string|array $target target class or array configuration.
     * @return Mapping embedding mapping instance.
     */
    public function hasEmbedList($source, $target)
    {
        return Yii::createObject([
            'class' => Mapping::className(),
            'source' => $source,
            'target' => $target,
            'multiple' => true,
        ]);
    }

    /**
     * @param string $name embed name.
     * @return string declaration method name.
     */
    private function composeEmbedDeclarationMethodName($name)
    {
        return 'embed' . $name;
    }

    /**
     * Returns list of values from embed objects named by source fields.
     * @return array embed values.
     */
    public function getEmbedValues()
    {
        $values = [];
        foreach ($this->_embed as $embed) {
            $values[$embed->source] = $embed->extractValues($this);
        }
        return $values;
    }

    /**
     * Fills up own fields by values fetched from embed objects.
     */
    public function synchronizeWithEmbed()
    {
        foreach ($this->getEmbedValues() as $name => $value) {
            $this->$name = $value;
        }
    }
}