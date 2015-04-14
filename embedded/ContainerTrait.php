<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\embedded;

use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use Yii;

/**
 * ContainerTrait can be used to satisfy [[ContainerInterface]].
 *
 * For each embedded entity a mapping declaration should be provided.
 * In order to do so you need to declare method, which name is prefixed with 'embedded', which
 * should return the [[Mapping]] instance. You may use [[hasEmbedded()]] and [[hasEmbeddedList()]] for this.
 *
 * Per each of source field or property a new virtual property will declared, which name will be composed
 * by removing 'embedded' prefix from the declaration method name.
 *
 * Note: watch for the naming collisions: if you have a source property named 'profile' the mapping declaration
 * for it should have different name, like 'profileModel'.
 *
 * Example:
 *
 * ~~~php
 * use yii\base\Model;
 * use yii\mongodb\embedded\ContainerInterface;
 * use yii\mongodb\embedded\ContainerTrait;
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
 *         return $this->mapEmbedded('profileData', 'Profile');
 *     }
 *
 *     public function embedComments()
 *     {
 *         return $this->mapEmbeddedList('commentsData', 'Comment');
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
 * In order to synchronize values between embedded entities and container use [[refreshFromEmbedded()]] method.
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
    private $_embedded = [];

    /**
     * PHP getter magic method.
     * This method is overridden so that embedded objects can be accessed like properties.
     *
     * @param string $name property name
     * @throws \yii\base\InvalidParamException if relation name is wrong
     * @return mixed property value
     * @see getAttribute()
     */
    public function __get($name)
    {
        if ($this->hasEmbedded($name)) {
            return $this->getEmbedded($name);
        }
        return parent::__get($name);
    }

    /**
     * PHP setter magic method.
     * This method is overridden so that embedded objects can be accessed like properties.
     * @param string $name property name
     * @param mixed $value property value
     */
    public function __set($name, $value)
    {
        if ($this->hasEmbedded($name)) {
            $this->setEmbedded($name, $value);
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Checks if a property value is null.
     * This method overrides the parent implementation by checking if the embedded object is null or not.
     * @param string $name the property name or the event name
     * @return boolean whether the property value is null
     */
    public function __isset($name)
    {
        if (isset($this->_embedded[$name])) {
            return ($this->_embedded[$name]->getValue($this) === null);
        }
        return parent::__isset($name);
    }

    /**
     * Sets a component property to be null.
     * This method overrides the parent implementation by clearing
     * the specified embedded object.
     * @param string $name the property name or the event name
     */
    public function __unset($name)
    {
        if (isset($this->_embedded[$name])) {
            ($this->_embedded[$name]->setValue(null));
        } else {
            parent::__unset($name);
        }
    }

    /**
     * Sets embedded object or list of objects.
     * @param string $name embedded name
     * @param object|object[]|null $value embedded value.
     */
    public function setEmbedded($name, $value)
    {
        $this->getEmbeddedMapping($name)->setValue($value);
    }

    /**
     * Returns embedded object or list of objects.
     * @param string $name embedded name.
     * @return object|object[]|null embedded value.
     */
    public function getEmbedded($name)
    {
        return $this->getEmbeddedMapping($name)->getValue($this);
    }

    /**
     * @param string $name embedded name
     * @throws \yii\base\InvalidParamException
     * @throws \yii\base\InvalidConfigException
     * @return Mapping embedded mapping.
     */
    private function getEmbeddedMapping($name)
    {
        if (!isset($this->_embedded[$name])) {
            $method = $this->composeEmbeddedDeclarationMethodName($name);
            if (!method_exists($this, $method)) {
                throw new InvalidParamException("'" . get_class($this) . "' has no declaration ('{$method}()') for the embedded '{$name}'");
            }
            $mapping = call_user_func([$this, $method]);
            if (!$mapping instanceof Mapping) {
                throw new InvalidConfigException("Mapping declaration '" . get_class($this) . "::{$method}()' should return instance of '" . Mapping::className() . "'");
            }
            $this->_embedded[$name] = $mapping;
        }
        return $this->_embedded[$name];
    }

    /**
     * Checks if asked embedded declaration exists.
     * @param string $name embedded name
     * @return boolean whether embedded declaration exists.
     */
    public function hasEmbedded($name)
    {
        return (isset($this->_embedded[$name])) || method_exists($this, $this->composeEmbeddedDeclarationMethodName($name));
    }

    /**
     * Declares embedded object.
     * @param string $source source field name
     * @param string|array $target target class or array configuration.
     * @param array $config mapping extra configuration.
     * @return Mapping embedding mapping instance.
     */
    public function mapEmbedded($source, $target, array $config = [])
    {
        return Yii::createObject(array_merge(
            [
                'class' => Mapping::className(),
                'source' => $source,
                'target' => $target,
                'multiple' => false,
            ],
            $config
        ));
    }

    /**
     * Declares embedded list of objects.
     * @param string $source source field name
     * @param string|array $target target class or array configuration.
     * @param array $config mapping extra configuration.
     * @return Mapping embedding mapping instance.
     */
    public function mapEmbeddedList($source, $target, array $config = [])
    {
        return Yii::createObject(array_merge(
            [
                'class' => Mapping::className(),
                'source' => $source,
                'target' => $target,
                'multiple' => true,
            ],
            $config
        ));
    }

    /**
     * @param string $name embedded name.
     * @return string declaration method name.
     */
    private function composeEmbeddedDeclarationMethodName($name)
    {
        return 'embed' . $name;
    }

    /**
     * Returns list of values from embedded objects named by source fields.
     * @return array embedded values.
     */
    public function getEmbeddedValues()
    {
        $values = [];
        foreach ($this->_embedded as $embedded) {
            $values[$embedded->source] = $embedded->extractValues($this);
        }
        return $values;
    }

    /**
     * Fills up own fields by values fetched from embedded objects.
     */
    public function refreshFromEmbedded()
    {
        foreach ($this->getEmbeddedValues() as $name => $value) {
            $this->$name = $value;
        }
    }
}