<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\embed;

/**
 * ContainerInterface allows converting any field or property, which is an associative array
 * or list of associative arrays into object or list pf objects correspondently.
 *
 * Embed objects will use the copy of the source data, so modifying of source field will not affect
 * instantiated embed objects and vice versa.
 * In order to synchronize values between embed entities and container use [[synchronizeWithEmbed()]] method.
 *
 * See [[ContainerTrait]] for particular implementation.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
interface ContainerInterface
{
    /**
     * Sets embed object or list of objects.
     * @param string $name embed name
     * @param object|object[]|null $value embed value.
     */
    public function setEmbed($name, $value);

    /**
     * Returns embed object or list of objects.
     * @param string $name embed name.
     * @return object|object[]|null embed value.
     */
    public function getEmbed($name);

    /**
     * Checks if asked embed declaration exists.
     * @param string $name embed name
     * @return boolean whether embed declaration exists.
     */
    public function embedExists($name);

    /**
     * Returns list of values from embed objects named by source fields.
     * @return array embed values.
     */
    public function getEmbedValues();

    /**
     * Fills up own fields by values fetched from embed objects.
     */
    public function synchronizeWithEmbed();
}