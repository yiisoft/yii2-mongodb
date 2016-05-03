<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\rbac;

use yii\caching\Cache;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\mongodb\Connection;
use yii\mongodb\Query;
use yii\rbac\Assignment;
use yii\rbac\BaseManager;
use yii\rbac\Item;
use yii\rbac\Permission;
use yii\rbac\Role;
use yii\rbac\Rule;

/**
 * MongoDbManager represents an authorization manager that stores authorization information in MongoDB.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.5
 */
class MongoDbManager extends BaseManager
{
    /**
     * @var Connection|array|string the MongoDB connection object or the application component ID of the MongoDB connection.
     * After the MongoDbManager object is created, if you want to change this property, you should only assign it
     * with a MongoDB connection object.
     */
    public $db = 'mongodb';
    /**
     * @var Cache|array|string the cache used to improve RBAC performance. This can be one of the following:
     *
     * - an application component ID (e.g. `cache`)
     * - a configuration array
     * - a [[\yii\caching\Cache]] object
     *
     * When this is not set, it means caching is not enabled.
     *
     * Note that by enabling RBAC cache, all auth items, rules and auth item parent-child relationships will
     * be cached and loaded into memory. This will improve the performance of RBAC permission check. However,
     * it does require extra memory and as a result may not be appropriate if your RBAC system contains too many
     * auth items. You should seek other RBAC implementations (e.g. RBAC based on Redis storage) in this case.
     *
     * Also note that if you modify RBAC items, rules or parent-child relationships from outside of this component,
     * you have to manually call [[invalidateCache()]] to ensure data consistency.
     */
    public $cache;
    /**
     * @var string the key used to store RBAC data in cache
     * @see cache
     */
    public $cacheKey = 'rbac';
    /**
     * @var string|array the name of the collection storing authorization items. Defaults to "auth_item".
     */
    public $itemCollection = 'auth_item';
    /**
     * @var string|array the name of the collection storing authorization item assignments. Defaults to "auth_assignment".
     */
    public $assignmentCollection = 'auth_assignment';
    /**
     * @var string|array the name of the collection storing rules. Defaults to "auth_rule".
     */
    public $ruleCollection = 'auth_rule';

    /**
     * @var Item[] all auth items (name => Item)
     */
    protected $items;
    /**
     * @var Rule[] all auth rules (name => Rule)
     */
    protected $rules;
    /**
     * @var array auth item parent-child relationships (childName => list of parents)
     */
    protected $parents;


    /**
     * Initializes the application component.
     * This method overrides the parent implementation by establishing the MongoDB connection.
     */
    public function init()
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::className());
        if ($this->cache !== null) {
            $this->cache = Instance::ensure($this->cache, Cache::className());
        }
    }

    /**
     * @inheritdoc
     */
    public function checkAccess($userId, $permissionName, $params = [])
    {
        $assignments = $this->getAssignments($userId);
        $this->loadFromCache();
        if ($this->items !== null) {
            return $this->checkAccessFromCache($userId, $permissionName, $params, $assignments);
        } else {
            return $this->checkAccessRecursive($userId, $permissionName, $params, $assignments);
        }
    }

    /**
     * @inheritdoc
     */
    protected function getItem($name)
    {
        if (empty($name)) {
            return null;
        }

        if (!empty($this->items[$name])) {
            return $this->items[$name];
        }

        $row = (new Query)->from($this->itemCollection)
            ->where(['name' => $name])
            ->one($this->db);

        if ($row === false) {
            return null;
        }

        return $this->populateItem($row);
    }

    /**
     * @inheritdoc
     */
    protected function getItems($type)
    {
        $query = (new Query)
            ->from($this->itemCollection)
            ->where(['type' => $type]);

        $items = [];
        foreach ($query->all($this->db) as $row) {
            $items[$row['name']] = $this->populateItem($row);
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    protected function addItem($item)
    {
        $time = time();
        if ($item->createdAt === null) {
            $item->createdAt = $time;
        }
        if ($item->updatedAt === null) {
            $item->updatedAt = $time;
        }

        $this->db->getCollection($this->itemCollection)
            ->insert([
                'name' => $item->name,
                'type' => $item->type,
                'description' => $item->description,
                'rule_name' => $item->ruleName,
                'data' => $item->data === null ? null : serialize($item->data),
                'created_at' => $item->createdAt,
                'updated_at' => $item->updatedAt,
            ]);

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function addRule($rule)
    {
        $time = time();
        if ($rule->createdAt === null) {
            $rule->createdAt = $time;
        }
        if ($rule->updatedAt === null) {
            $rule->updatedAt = $time;
        }
        $this->db->getCollection($this->ruleCollection)
            ->insert([
                'name' => $rule->name,
                'data' => serialize($rule),
                'created_at' => $rule->createdAt,
                'updated_at' => $rule->updatedAt,
            ]);

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function removeItem($item)
    {
        // TODO: handle parent/child
        $this->db->getCollection($this->assignmentCollection)
            ->remove(['item_name' => $item->name]);

        $this->db->getCollection($this->itemCollection)
            ->remove(['name' => $item->name]);

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function removeRule($rule)
    {
        $this->db->getCollection($this->itemCollection)
            ->update(['rule_name' => $rule->name], ['rule_name' => null]);

        $this->db->getCollection($this->ruleCollection)
            ->remove(['name' => $rule->name]);

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function updateItem($name, $item)
    {
        if ($item->name !== $name) {
            // TODO: update parent/child info
            $this->db->getCollection($this->assignmentCollection)
                ->update(['item_name' => $name], ['item_name' => $item->name]);
        }

        $item->updatedAt = time();

        $this->db->getCollection($this->itemCollection)
            ->update(
                [
                    'name' => $name,
                ],
                [
                    'name' => $item->name,
                    'description' => $item->description,
                    'rule_name' => $item->ruleName,
                    'data' => $item->data === null ? null : serialize($item->data),
                    'updated_at' => $item->updatedAt,
                ]
            );

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function updateRule($name, $rule)
    {
        if ($rule->name !== $name) {
            $this->db->getCollection($this->itemCollection)
                ->update(['rule_name' => $name], ['rule_name' => $rule->name]);
        }

        $rule->updatedAt = time();

        $this->db->getCollection($this->ruleCollection)
            ->update(
                [
                    'name' => $name,
                ],
                [
                    'name' => $rule->name,
                    'data' => serialize($rule),
                    'updated_at' => $rule->updatedAt,
                ]
            );

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getRolesByUser($userId)
    {
        if (!isset($userId) || $userId === '') {
            return [];
        }

        $rows = (new Query)->select(['item_name'])
            ->from($this->assignmentCollection)
            ->where(['user_id' => (string) $userId])
            ->all($this->db);

        if (empty($rows)) {
            return [];
        }

        $itemNames = ArrayHelper::getColumn($rows, 'item_name');

        $query = (new Query)
            ->from($this->itemCollection)
            ->where(['name' => $itemNames])
            ->andWhere(['user_id' => (string) $userId])
            ->andWhere(['type' => Item::TYPE_ROLE]);

        $roles = [];
        foreach ($query->all($this->db) as $row) {
            $roles[$row['name']] = $this->populateItem($row);
        }
        return $roles;
    }

    /**
     * Returns all permissions that the specified role represents.
     * @param string $roleName the role name
     * @return Permission[] all permissions that the role represents. The array is indexed by the permission names.
     */
    public function getPermissionsByRole($roleName)
    {
        // TODO: Implement getPermissionsByRole() method.
    }

    /**
     * Returns all permissions that the user has.
     * @param string|integer $userId the user ID (see [[\yii\web\User::id]])
     * @return Permission[] all permissions that the user has. The array is indexed by the permission names.
     */
    public function getPermissionsByUser($userId)
    {
        // TODO: Implement getPermissionsByUser() method.
    }

    /**
     * @inheritdoc
     */
    public function getRule($name)
    {
        if ($this->rules !== null) {
            return isset($this->rules[$name]) ? $this->rules[$name] : null;
        }

        $row = (new Query)->select(['data'])
            ->from($this->ruleCollection)
            ->where(['name' => $name])
            ->one($this->db);
        return $row === false ? null : unserialize($row['data']);
    }

    /**
     * @inheritdoc
     */
    public function getRules()
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $query = (new Query)->from($this->ruleCollection);

        $rules = [];
        foreach ($query->all($this->db) as $row) {
            $rules[$row['name']] = unserialize($row['data']);
        }

        return $rules;
    }

    /**
     * Checks the possibility of adding a child to parent
     * @param Item $parent the parent item
     * @param Item $child the child item to be added to the hierarchy
     * @return boolean possibility of adding
     *
     * @since 2.0.8
     */
    public function canAddChild($parent, $child)
    {
        // TODO: Implement canAddChild() method.
    }

    /**
     * Adds an item as a child of another item.
     * @param Item $parent
     * @param Item $child
     * @return boolean whether the child successfully added
     * @throws \yii\base\Exception if the parent-child relationship already exists or if a loop has been detected.
     */
    public function addChild($parent, $child)
    {
        // TODO: Implement addChild() method.
    }

    /**
     * Removes a child from its parent.
     * Note, the child item is not deleted. Only the parent-child relationship is removed.
     * @param Item $parent
     * @param Item $child
     * @return boolean whether the removal is successful
     */
    public function removeChild($parent, $child)
    {
        // TODO: Implement removeChild() method.
    }

    /**
     * Removed all children form their parent.
     * Note, the children items are not deleted. Only the parent-child relationships are removed.
     * @param Item $parent
     * @return boolean whether the removal is successful
     */
    public function removeChildren($parent)
    {
        // TODO: Implement removeChildren() method.
    }

    /**
     * Returns a value indicating whether the child already exists for the parent.
     * @param Item $parent
     * @param Item $child
     * @return boolean whether `$child` is already a child of `$parent`
     */
    public function hasChild($parent, $child)
    {
        // TODO: Implement hasChild() method.
    }

    /**
     * Returns the child permissions and/or roles.
     * @param string $name the parent name
     * @return Item[] the child permissions and/or roles
     */
    public function getChildren($name)
    {
        // TODO: Implement getChildren() method.
    }

    /**
     * @inheritdoc
     */
    public function assign($role, $userId)
    {
        $assignment = new Assignment([
            'userId' => $userId,
            'roleName' => $role->name,
            'createdAt' => time(),
        ]);

        $this->db->getCollection($this->assignmentCollection)
            ->insert([
                'user_id' => $assignment->userId,
                'item_name' => $assignment->roleName,
                'created_at' => $assignment->createdAt,
            ]);

        return $assignment;
    }

    /**
     * @inheritdoc
     */
    public function revoke($role, $userId)
    {
        if (empty($userId)) {
            return false;
        }

        return $this->db->getCollection($this->assignmentCollection)
            ->remove(['user_id' => (string) $userId, 'item_name' => $role->name]) > 0;
    }

    /**
     * @inheritdoc
     */
    public function revokeAll($userId)
    {
        if (empty($userId)) {
            return false;
        }

        return $this->db->getCollection($this->assignmentCollection)
            ->remove(['user_id' => (string) $userId]) > 0;
    }

    /**
     * @inheritdoc
     */
    public function getAssignment($roleName, $userId)
    {
        if (empty($userId)) {
            return null;
        }

        $row = (new Query)->from($this->assignmentCollection)
            ->where(['user_id' => (string) $userId, 'item_name' => $roleName])
            ->one($this->db);

        if ($row === false) {
            return null;
        }

        return new Assignment([
            'userId' => $row['user_id'],
            'roleName' => $row['item_name'],
            'createdAt' => $row['created_at'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getAssignments($userId)
    {
        if (empty($userId)) {
            return [];
        }

        $query = (new Query)
            ->from($this->assignmentCollection)
            ->where(['user_id' => (string) $userId]);

        $assignments = [];
        foreach ($query->all($this->db) as $row) {
            $assignments[$row['item_name']] = new Assignment([
                'userId' => $row['user_id'],
                'roleName' => $row['item_name'],
                'createdAt' => $row['created_at'],
            ]);
        }

        return $assignments;
    }

    /**
     * @inheritdoc
     */
    public function getUserIdsByRole($roleName)
    {
        if (empty($roleName)) {
            return [];
        }

        $rows = (new Query)->select(['user_id'])
            ->from($this->assignmentCollection)
            ->where(['item_name' => $roleName])
            ->all($this->db);

        return ArrayHelper::getColumn($rows, 'user_id');
    }

    /**
     * @inheritdoc
     */
    public function removeAll()
    {
        $this->removeAllAssignments();
        $this->db->getCollection($this->itemCollection)->remove();
        $this->db->getCollection($this->ruleCollection)->remove();
        $this->invalidateCache();
    }

    /**
     * @inheritdoc
     */
    public function removeAllPermissions()
    {
        $this->removeAllItems(Item::TYPE_PERMISSION);
    }

    /**
     * @inheritdoc
     */
    public function removeAllRoles()
    {
        $this->removeAllItems(Item::TYPE_ROLE);
    }

    /**
     * @inheritdoc
     */
    public function removeAllRules()
    {
        $this->db->getCollection($this->itemCollection)
            ->update([], ['rule_name' => null]);

        $this->db->getCollection($this->ruleCollection)->remove();

        $this->invalidateCache();
    }

    /**
     * @inheritdoc
     */
    public function removeAllAssignments()
    {
        $this->db->getCollection($this->assignmentCollection)->remove();
    }

    /**
     * Invalidates RBAC related cache
     */
    public function invalidateCache()
    {
        if ($this->cache !== null) {
            $this->cache->delete($this->cacheKey);
            $this->items = null;
            $this->rules = null;
            $this->parents = null;
        }
    }

    /**
     * Populates an auth item with the data fetched from collection
     * @param array $row the data from the auth item collection
     * @return Item the populated auth item instance (either Role or Permission)
     */
    protected function populateItem($row)
    {
        $class = $row['type'] == Item::TYPE_PERMISSION ? Permission::className() : Role::className();

        if (!isset($row['data']) || ($data = @unserialize($row['data'])) === false) {
            $data = null;
        }

        return new $class([
            'name' => $row['name'],
            'type' => $row['type'],
            'description' => $row['description'],
            'ruleName' => $row['rule_name'],
            'data' => $data,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ]);
    }

    /**
     * Removes all auth items of the specified type.
     * @param integer $type the auth item type (either Item::TYPE_PERMISSION or Item::TYPE_ROLE)
     */
    protected function removeAllItems($type)
    {
        $rows = (new Query)
            ->select(['name'])
            ->from($this->itemCollection)
            ->where(['type' => $type])
            ->all($this->db);
        if (empty($rows)) {
            return;
        }

        $names = ArrayHelper::getColumn($rows, 'name');
        // TODO: handle parent/child
        //$key = $type == Item::TYPE_PERMISSION ? 'child' : 'parent';
        $this->db->getCollection($this->assignmentCollection)
            ->remove(['item_name' => $names]);

        $this->db->getCollection($this->itemCollection)
            ->remove(['type' => $type]);

        $this->invalidateCache();
    }
}