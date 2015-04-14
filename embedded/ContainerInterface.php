<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\embedded;

/**
 * ContainerInterface allows converting any field or property, which is an associative array
 * or list of associative arrays into object or list pf objects correspondently.
 *
 * Embedded objects will use the copy of the source data, so modifying of source field will not affect
 * instantiated embedded objects and vice versa.
 * In order to synchronize values between embedded entities and container use [[refreshFromEmbedded()]] method.
 *
 * See [[ContainerTrait]] for particular implementation.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
interface ContainerInterface
{
    /**
     * Sets embedded object or list of objects.
     * @param string $name embedded name
     * @param object|object[]|null $value embedded value.
     */
    public function setEmbedded($name, $value);

    /**
     * Returns embedded object or list of objects.
     * @param string $name embedded name.
     * @return object|object[]|null embedded value.
     */
    public function getEmbedded($name);

    /**
     * Checks if asked embedded declaration exists.
     * @param string $name embedded name
     * @return boolean whether embedded declaration exists.
     */
    public function hasEmbedded($name);

    /**
     * Returns list of values from embedded objects named by source fields.
     * @return array embedded values.
     */
    public function getEmbeddedValues();

    /**
     * Fills up own fields by values fetched from embedded objects.
     */
    public function refreshFromEmbedded();
}