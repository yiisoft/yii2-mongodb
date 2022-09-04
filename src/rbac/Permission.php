<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb\rbac;

/**
 * Permission is a special version of [[\yii\rbac\Permission]] dedicated to MongoDB RBAC implementation.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.5
 */
class Permission extends \yii\rbac\Permission
{
    /**
     * @var array|null list of parent item names.
     */
    public $parents;
}