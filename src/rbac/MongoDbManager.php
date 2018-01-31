<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\rbac;

use Yii;
use yii\base\InvalidCallException;
use yii\base\InvalidParamException;
use yii\caching\Cache;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\mongodb\Connection;
use yii\mongodb\Query;
use yii\rbac\Assignment;
use yii\rbac\BaseManager;
use yii\rbac\Item;
use yii\rbac\Rule;

/**
 * MongoDbManager represents an authorization manager that stores authorization information in MongoDB.
 *
 * Manager uses 3 collections for the RBAC data storage:
 *
 * - [[itemCollection]] - stores item data and item parents list
 * - [[assignmentCollection]] - stores assignments info
 * - [[ruleCollection]] - stores rule data
 *
 * These collection are better to be pre-created with search fields indexed.
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
     * {@inheritdoc}
     */
    public function checkAccess($userId, $permissionName, $params = [])
    {
        $assignments = $this->getAssignments($userId);
        $this->loadFromCache();
        return $this->items !== null
            ? $this->checkAccessFromCache($userId, $permissionName, $params, $assignments)
            : $this->checkAccessRecursive($userId, $permissionName, $params, $assignments);
    }

    /**
     * Performs access check for the specified user based on the data loaded from cache.
     * This method is internally called by [[checkAccess()]] when [[cache]] is enabled.
     * @param string|int $user the user ID. This should can be either an integer or a string representing
     * the unique identifier of a user. See [[\yii\web\User::id]].
     * @param string $itemName the name of the operation that need access check
     * @param array $params name-value pairs that would be passed to rules associated
     * with the tasks and roles assigned to the user. A param with name 'user' is added to this array,
     * which holds the value of `$userId`.
     * @param Assignment[] $assignments the assignments to the specified user
     * @return bool whether the operations can be performed by the user.
     */
    protected function checkAccessFromCache($user, $itemName, $params, $assignments)
    {
        if (!isset($this->items[$itemName])) {
            return false;
        }

        $item = $this->items[$itemName];

        Yii::trace($item instanceof Role ? "Checking role: $itemName" : "Checking permission: $itemName", __METHOD__);

        if (!$this->executeRule($user, $item, $params)) {
            return false;
        }

        if (isset($assignments[$itemName]) || in_array($itemName, $this->defaultRoles)) {
            return true;
        }

        if (!empty($item->parents)) {
            foreach ($item->parents as $parent) {
                if ($this->checkAccessFromCache($user, $parent, $params, $assignments)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Performs access check for the specified user.
     * This method is internally called by [[checkAccess()]].
     * @param string|int $user the user ID. This should can be either an integer or a string representing
     * the unique identifier of a user. See [[\yii\web\User::id]].
     * @param string $itemName the name of the operation that need access check
     * @param array $params name-value pairs that would be passed to rules associated
     * with the tasks and roles assigned to the user. A param with name 'user' is added to this array,
     * which holds the value of `$userId`.
     * @param Assignment[] $assignments the assignments to the specified user
     * @return bool whether the operations can be performed by the user.
     */
    protected function checkAccessRecursive($user, $itemName, $params, $assignments)
    {
        if (($item = $this->getItem($itemName)) === null) {
            return false;
        }

        Yii::trace($item instanceof Role ? "Checking role: $itemName" : "Checking permission: $itemName", __METHOD__);

        if (!$this->executeRule($user, $item, $params)) {
            return false;
        }

        if (isset($assignments[$itemName]) || in_array($itemName, $this->defaultRoles)) {
            return true;
        }

        if (!empty($item->parents)) {
            foreach ($item->parents as $parent) {
                if ($this->checkAccessRecursive($user, $parent, $params, $assignments)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    protected function removeItem($item)
    {
        $this->db->getCollection($this->assignmentCollection)
            ->remove(['item_name' => $item->name]);

        $this->db->getCollection($this->itemCollection)
            ->remove(['name' => $item->name]);

        $this->db->getCollection($this->itemCollection)
            ->update(
                [
                    'parents' => [
                        '$in' => [$item->name]
                    ],
                ],
                [
                    '$pull' => [
                        'parents' => [
                            '$in' => [$item->name],
                        ]
                    ]
                ],
                [
                    'multi' => true
                ]
            );

        $this->invalidateCache();

        return true;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    protected function updateItem($name, $item)
    {
        if ($item->name !== $name) {
            $this->db->getCollection($this->assignmentCollection)
                ->update(['item_name' => $name], ['item_name' => $item->name]);

            $this->db->getCollection($this->itemCollection)
                ->update(
                    [
                        'parents' => [
                            '$in' => [$item->name]
                        ],
                    ],
                    [
                        '$pull' => [
                            'parents' => [
                                '$in' => [$item->name],
                            ]
                        ],
                    ],
                    [
                        'multi' => true
                    ]
                );

            $this->db->getCollection($this->itemCollection)
                ->update(
                    [
                        'parents' => [
                            '$in' => [$item->name]
                        ],
                    ],
                    [
                        '$push' => [
                            'parents' => $name
                        ]
                    ],
                    [
                        'multi' => true
                    ]
                );
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getRolesByUser($userId)
    {
        if (!isset($userId) || $userId === '') {
            return [];
        }

        $roles = $this->instantiateDefaultRoles();

        $rows = (new Query())
            ->select(['item_name'])
            ->from($this->assignmentCollection)
            ->where(['user_id' => (string) $userId])
            ->all($this->db);

        if (empty($rows)) {
            return $roles;
        }

        $itemNames = ArrayHelper::getColumn($rows, 'item_name');

        $query = (new Query())
            ->from($this->itemCollection)
            ->where(['name' => $itemNames])
            ->andWhere(['type' => Item::TYPE_ROLE]);

        foreach ($query->all($this->db) as $row) {
            $roles[$row['name']] = $this->populateItem($row);
        }

        return $roles;
    }

    /**
     * {@inheritdoc}
     * @since 2.1.2
     */
    public function getChildRoles($roleName)
    {
        $role = $this->getRole($roleName);

        if (is_null($role)) {
            throw new InvalidParamException("Role '{$roleName}' not found.");
        }

        $result = [];
        $this->getChildrenRecursive($roleName, $this->getChildrenList(), $result);

        $roles = [$roleName => $role];

        $roles += array_filter($this->getRoles(), function (Role $roleItem) use ($result) {
            return array_key_exists($roleItem->name, $result);
        });

        return $roles;
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionsByRole($roleName)
    {
        $childrenList = $this->getChildrenList();
        $result = [];
        $this->getChildrenRecursive($roleName, $childrenList, $result);
        if (empty($result)) {
            return [];
        }

        $query = (new Query)
            ->from($this->itemCollection)
            ->where([
                'type' => Item::TYPE_PERMISSION,
                'name' => array_keys($result),
            ]);
        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            $permissions[$row['name']] = $this->populateItem($row);
        }
        return $permissions;
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionsByUser($userId)
    {
        if (empty($userId)) {
            return [];
        }

        $this->getAssignments($userId);

        $rows = (new Query)
            ->select(['item_name'])
            ->from($this->assignmentCollection)
            ->where(['user_id' => (string) $userId])
            ->all($this->db);

        if (empty($rows)) {
            return [];
        }

        $names = ArrayHelper::getColumn($rows, 'item_name');

        $childrenList = $this->getChildrenList();
        $result = [];
        foreach ($names as $roleName) {
            $this->getChildrenRecursive($roleName, $childrenList, $result);
        }

        $names = array_merge($names, array_keys($result));

        $query = (new Query)
            ->from($this->itemCollection)
            ->where([
                'type' => Item::TYPE_PERMISSION,
                'name' => $names,
            ]);

        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            $permissions[$row['name']] = $this->populateItem($row);
        }
        return $permissions;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function canAddChild($parent, $child)
    {
        return !$this->detectLoop($parent, $child);
    }

    /**
     * {@inheritdoc}
     */
    public function addChild($parent, $child)
    {
        if ($parent->name === $child->name) {
            throw new InvalidParamException("Cannot add '{$parent->name}' as a child of itself.");
        }

        if ($parent instanceof Permission && $child instanceof Role) {
            throw new InvalidParamException('Cannot add a role as a child of a permission.');
        }

        if ($this->detectLoop($parent, $child)) {
            throw new InvalidCallException("Cannot add '{$child->name}' as a child of '{$parent->name}'. A loop has been detected.");
        }

        $result = $this->db->getCollection($this->itemCollection)
            ->update(
                [
                    'name' => $child->name,
                ],
                [
                    '$push' => [
                        'parents' => $parent->name
                    ]
                ],
                [
                    'multi' => false
                ]
            ) > 0;

        $this->invalidateCache();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function removeChild($parent, $child)
    {
        $result = $this->db->getCollection($this->itemCollection)
            ->update(
                [
                    'name' => $child->name,
                ],
                [
                    '$pull' => [
                        'parents' => [
                            '$in' => [$parent->name]
                        ]
                    ]
                ],
                [
                    'multi' => false
                ]
            ) > 0;

        $this->invalidateCache();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function removeChildren($parent)
    {
        $result = $this->db->getCollection($this->itemCollection)
            ->update(
                [],
                [
                    '$pull' => [
                        'parents' => [
                            '$in' => [$parent->name]
                        ]
                    ]
                ],
                [
                    'multi' => true
                ]
            ) > 0;

        $this->invalidateCache();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function hasChild($parent, $child)
    {
        return (new Query)
            ->from($this->itemCollection)
            ->where([
                'name' => $child->name
            ])
            ->andWhere([
                'parents' => [
                    '$in' => [$parent->name]
                ]
            ])
            ->one($this->db) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren($name)
    {
        $query = (new Query)
            ->from($this->itemCollection)
            ->where([
                'parents' => [
                    '$in' => [$name]
                ]
            ]);

        $children = [];
        foreach ($query->all($this->db) as $row) {
            $children[$row['name']] = $this->populateItem($row);
        }

        return $children;
    }

    /**
     * {@inheritdoc}
     */
    public function assign($role, $userId)
    {
        $assignment = new Assignment([
            'userId' => (string)$userId,
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function removeAll()
    {
        $this->removeAllAssignments();
        $this->db->getCollection($this->itemCollection)->remove();
        $this->db->getCollection($this->ruleCollection)->remove();
        $this->invalidateCache();
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllPermissions()
    {
        $this->removeAllItems(Item::TYPE_PERMISSION);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllRoles()
    {
        $this->removeAllItems(Item::TYPE_ROLE);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllRules()
    {
        $this->db->getCollection($this->itemCollection)
            ->update([], ['rule_name' => null]);

        $this->db->getCollection($this->ruleCollection)->remove();

        $this->invalidateCache();
    }

    /**
     * {@inheritdoc}
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
            'parents' => isset($row['parents']) ? $row['parents'] : null,
        ]);
    }

    /**
     * Removes all auth items of the specified type.
     * @param int $type the auth item type (either Item::TYPE_PERMISSION or Item::TYPE_ROLE)
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

        $this->db->getCollection($this->assignmentCollection)
            ->remove(['item_name' => $names]);

        $this->db->getCollection($this->itemCollection)
            ->remove(['type' => $type]);

        $this->db->getCollection($this->itemCollection)
            ->update(
                [],
                [
                    '$pull' => [
                        'parents' => [
                            '$in' => $names,
                        ]
                    ],
                ],
                [
                    'multi' => true
                ]
            );

        $this->invalidateCache();
    }

    /**
     * Loads data from cache
     */
    public function loadFromCache()
    {
        if ($this->items !== null || !$this->cache instanceof Cache) {
            return;
        }

        $data = $this->cache->get($this->cacheKey);
        if (is_array($data) && isset($data[0], $data[1])) {
            list ($this->items, $this->rules) = $data;
            return;
        }

        $query = (new Query)->from($this->itemCollection);
        $this->items = [];
        foreach ($query->all($this->db) as $row) {
            $this->items[$row['name']] = $this->populateItem($row);
        }

        $query = (new Query)->from($this->ruleCollection);
        $this->rules = [];
        foreach ($query->all($this->db) as $row) {
            $this->rules[$row['name']] = unserialize($row['data']);
        }

        $this->cache->set($this->cacheKey, [$this->items, $this->rules]);
    }

    /**
     * Returns the children for every parent.
     * @return array the children list. Each array key is a parent item name,
     * and the corresponding array value is a list of child item names.
     */
    protected function getChildrenList()
    {
        $query = (new Query)
            ->select(['name', 'parents'])
            ->from($this->itemCollection);
        $children = [];
        foreach ($query->all($this->db) as $row) {
            if (!empty($row['parents'])) {
                foreach ($row['parents'] as $name) {
                    $children[$name][] = $row['name'];
                }
            }
        }
        return $children;
    }

    /**
     * Recursively finds all children and grand children of the specified item.
     * @param string $name the name of the item whose children are to be looked for.
     * @param array $childrenList the child list built via [[getChildrenList()]]
     * @param array $result the children and grand children (in array keys)
     */
    protected function getChildrenRecursive($name, $childrenList, &$result)
    {
        if (isset($childrenList[$name])) {
            foreach ($childrenList[$name] as $child) {
                $result[$child] = true;
                $this->getChildrenRecursive($child, $childrenList, $result);
            }
        }
    }

    /**
     * Checks whether there is a loop in the authorization item hierarchy.
     * @param Item $parent the parent item
     * @param Item $child the child item to be added to the hierarchy
     * @return bool whether a loop exists
     */
    protected function detectLoop($parent, $child)
    {
        if ($child->name === $parent->name) {
            return true;
        }
        foreach ($this->getChildren($child->name) as $grandchild) {
            if ($this->detectLoop($parent, $grandchild)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns defaultRoles as array of Role objects
     * @since 2.1.3
     * @return Role[] default roles. The array is indexed by the role names
     */
    private function instantiateDefaultRoles()
    {
        // this method can be removed in favor of `yii\rbac\BaseManager::getDefaultRoles()` in case
        // extension dependency on `yii2` is raised up to 2.0.12
        $result = [];
        foreach ($this->defaultRoles as $roleName) {
            $result[$roleName] = $this->createRole($roleName);
        }
        return $result;
    }
}