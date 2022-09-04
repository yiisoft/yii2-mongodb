<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\mongodb\console\controllers;

use Yii;
use yii\console\controllers\BaseMigrateController;
use yii\console\Exception;
use yii\mongodb\Connection;
use yii\mongodb\Query;
use yii\helpers\ArrayHelper;

/**
 * Manages application MongoDB migrations.
 *
 * This is an analog of [[\yii\console\controllers\MigrateController]] for MongoDB.
 *
 * This command provides support for tracking the migration history, upgrading
 * or downloading with migrations, and creating new migration skeletons.
 *
 * The migration history is stored in a MongoDB collection named
 * as [[migrationCollection]]. This collection will be automatically created the first time
 * this command is executed, if it does not exist.
 *
 * In order to enable this command you should adjust the configuration of your console application:
 *
 * ```php
 * return [
 *     // ...
 *     'controllerMap' => [
 *         'mongodb-migrate' => 'yii\mongodb\console\controllers\MigrateController'
 *     ],
 * ];
 * ```
 *
 * Below are some common usages of this command:
 *
 * ```php
 * # creates a new migration named 'create_user_collection'
 * yii mongodb-migrate/create create_user_collection
 *
 * # applies ALL new migrations
 * yii mongodb-migrate
 *
 * # reverts the last applied migration
 * yii mongodb-migrate/down
 * ```
 *
 * Since 2.1.2, in case of usage Yii version >= 2.0.10, you can use namespaced migrations. In order to enable this
 * feature you should configure [[migrationNamespaces]] property for the controller at application configuration:
 *
 * ```php
 * return [
 *     'controllerMap' => [
 *         'mongodb-migrate' => [
 *             'class' => 'yii\mongodb\console\controllers\MigrateController',
 *             'migrationNamespaces' => [
 *                 'app\migrations',
 *                 'some\extension\migrations',
 *             ],
 *             //'migrationPath' => null, // allows to disable not namespaced migration completely
 *         ],
 *     ],
 * ];
 * ```
 *
 * @author Klimov Paul <klimov@zfort.com>
 * @since 2.0
 */
class MigrateController extends BaseMigrateController
{
    /**
     * @var string|array the name of the collection for keeping applied migration information.
     */
    public $migrationCollection = 'migration';
    /**
     * {@inheritdoc}
     */
    public $templateFile = '@yii/mongodb/views/migration.php';
    /**
     * @var Connection|string the DB connection object or the application
     * component ID of the DB connection.
     */
    public $db = 'mongodb';


    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        return array_merge(
            parent::options($actionID),
            ['migrationCollection', 'db'] // global for all actions
        );
    }

    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * It checks the existence of the [[migrationPath]].
     * @param \yii\base\Action $action the action to be executed.
     * @throws Exception if db component isn't configured
     * @return bool whether the action should continue to be executed.
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            if ($action->id !== 'create') {
                if (is_string($this->db)) {
                    $this->db = Yii::$app->get($this->db);
                }
                if (!$this->db instanceof Connection) {
                    throw new Exception("The 'db' option must refer to the application component ID of a MongoDB connection.");
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Creates a new migration instance.
     * @param string $class the migration class name
     * @return \yii\mongodb\Migration the migration instance
     */
    protected function createMigration($class)
    {
        // since Yii 2.0.12 includeMigrationFile() exists, which replaced the code below
        // remove this construct when composer requirement raises above 2.0.12
        if (method_exists($this, 'includeMigrationFile')) {
            $this->includeMigrationFile($class);
        } else {
            $class = trim($class, '\\');
            if (strpos($class, '\\') === false) {
                $file = $this->migrationPath . DIRECTORY_SEPARATOR . $class . '.php';
                require_once($file);
            }
        }

        return new $class(['db' => $this->db, 'compact' => isset($this->compact) ? $this->compact : false]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getMigrationHistory($limit)
    {
        $this->ensureBaseMigrationHistory();

        $query = (new Query())
            ->select(['version', 'apply_time'])
            ->from($this->migrationCollection)
            ->orderBy(['apply_time' => SORT_DESC, 'version' => SORT_DESC]);

        if (empty($this->migrationNamespaces)) {
            $query->limit($limit);
            $rows = $query->all($this->db);
            $history = ArrayHelper::map($rows, 'version', 'apply_time');
            unset($history[self::BASE_MIGRATION]);
            return $history;
        }

        $rows = $query->all($this->db);

        $history = [];
        foreach ($rows as $key => $row) {
            if ($row['version'] === self::BASE_MIGRATION) {
                continue;
            }
            if (preg_match('/m?(\d{6}_?\d{6})(\D.*)?$/is', $row['version'], $matches)) {
                $time = str_replace('_', '', $matches[1]);
                $row['canonicalVersion'] = $time;
            } else {
                $row['canonicalVersion'] = $row['version'];
            }
            $row['apply_time'] = (int)$row['apply_time'];
            $history[] = $row;
        }

        usort($history, function ($a, $b) {
            if ($a['apply_time'] === $b['apply_time']) {
                if (($compareResult = strcasecmp($b['canonicalVersion'], $a['canonicalVersion'])) !== 0) {
                    return $compareResult;
                }
                return strcasecmp($b['version'], $a['version']);
            }
            return ($a['apply_time'] > $b['apply_time']) ? -1 : +1;
        });

        $history = array_slice($history, 0, $limit);

        $history = ArrayHelper::map($history, 'version', 'apply_time');

        return $history;
    }

    private $baseMigrationEnsured = false;

    /**
     * Ensures migration history contains at least base migration entry.
     */
    protected function ensureBaseMigrationHistory()
    {
        if (!$this->baseMigrationEnsured) {
            $query = new Query;
            $row = $query->select(['version'])
                ->from($this->migrationCollection)
                ->andWhere(['version' => self::BASE_MIGRATION])
                ->limit(1)
                ->one($this->db);
            if (empty($row)) {
                $this->addMigrationHistory(self::BASE_MIGRATION);
            }
            $this->baseMigrationEnsured = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function addMigrationHistory($version)
    {
        $this->db->getCollection($this->migrationCollection)->insert([
            'version' => $version,
            'apply_time' => time(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeMigrationHistory($version)
    {
        $this->db->getCollection($this->migrationCollection)->remove([
            'version' => $version,
        ]);
    }

    /**
     * {@inheritdoc}
     * @since 2.1.5
     */
    protected function truncateDatabase()
    {
        $collections = $this->db->getDatabase()->createCommand()->listCollections();

        foreach ($collections as $collection) {
            if (in_array($collection['name'], ['system.roles', 'system.users', 'system.indexes'])) {
                // prevent deleting database auth data
                // access to 'system.indexes' is more likely to be restricted, thus indexes will be dropped manually per collection
                $this->stdout("System collection {$collection['name']} skipped.\n");
                continue;
            }

            if (in_array($collection['name'], ['system.profile', 'system.js'])) {
                // dropping of system collection is unlikely to be permitted, attempt to clear them out instead
                $this->db->getDatabase()->createCommand()->delete($collection['name'], []);
                $this->stdout("System collection {$collection['name']} truncated.\n");
                continue;
            }

            $this->db->getDatabase()->createCommand()->dropIndexes($collection['name'], '*');
            $this->db->getDatabase()->dropCollection($collection['name']);
            $this->stdout("Collection {$collection['name']} dropped.\n");
        }
    }
}
