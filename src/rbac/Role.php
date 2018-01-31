<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\rbac;

/**
 * Role is a special version of [[\yii\rbac\Role]] dedicated to MongoDB RBAC implementation.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.5
 */
class Role extends \yii\rbac\Role
{
    /**
     * @var array|null list of parent item names.
     */
    public $parents;
}