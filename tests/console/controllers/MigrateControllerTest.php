<?php

namespace yiiunit\extensions\mongodb\console\controllers;

use yii\console\controllers\BaseMigrateController;
use yii\helpers\FileHelper;
use yii\mongodb\Exception;
use yii\mongodb\Migration;
use yii\mongodb\Query;
use Yii;
use yiiunit\extensions\mongodb\TestCase;

/**
 * Unit test for [[\yii\mongodb\console\controllers\MigrateController]].
 * @see MigrateController
 *
 * @group mongodb
 * @group console
 */
class MigrateControllerTest extends TestCase
{
    /**
     * @var string name of the migration controller class, which is under test.
     */
    protected $migrateControllerClass;
    /**
     * @var string name of the migration base class.
     */
    protected $migrationBaseClass;
    /**
     * @var string test migration path.
     */
    protected $migrationPath;
    /**
     * @var string test migration namespace
     */
    protected $migrationNamespace;


    protected function setUp(): void
    {
        $this->migrateControllerClass = EchoMigrateController::className();
        $this->migrationBaseClass = Migration::className();

        parent::setUp();

        $this->migrationNamespace = 'yiiunit\extensions\mongodb\runtime\test_migrations';

        $this->setUpMigrationPath();

        $this->mockApplication();
        Yii::$app->setComponents(['mongodb' => $this->getConnection()]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (extension_loaded('mongodb')) {
            try {
                $this->getConnection()->getCollection('migration')->drop();
            } catch (Exception $e) {
                // shutdown exception
            }
        }
        $this->tearDownMigrationPath();
    }

    public function setUpMigrationPath()
    {
        $this->migrationPath = Yii::getAlias('@yiiunit/extensions/mongodb/runtime/test_migrations');
        FileHelper::createDirectory($this->migrationPath);
        if (!file_exists($this->migrationPath)) {
            $this->markTestIncomplete('Unit tests runtime directory should have writable permissions!');
        }
    }

    public function tearDownMigrationPath()
    {
        FileHelper::removeDirectory($this->migrationPath);
    }

    /**
     * Creates test migrate controller instance.
     * @param array $config controller configuration.
     * @return BaseMigrateController migrate command instance.
     */
    protected function createMigrateController(array $config = [])
    {
        $module = $this->getMockBuilder('yii\\base\\Module')
            ->setConstructorArgs(['console'])
            ->setMethods(['fake'])
            ->getMock();

        $class = $this->migrateControllerClass;
        $migrateController = new $class('migrate', $module);
        $migrateController->interactive = false;
        $migrateController->migrationPath = $this->migrationPath;

        if (array_key_exists('migrationNamespaces', $config) && !$migrateController->canSetProperty('migrationNamespaces')) {
            $this->markTestSkipped("`migrationNamespaces` not supported by this Yii framework version");
        }

        return Yii::configure($migrateController, $config);
    }

    /**
     * @return array applied migration entries
     */
    protected function getMigrationHistory()
    {
        $query = new Query();
        return $query->from('migration')->all();
    }

    /**
     * Emulates running of the migrate controller action.
     * @param string $actionID id of action to be run.
     * @param array $args action arguments.
     * @param array $config controller configuration.
     * @return string command output.
     */
    protected function runMigrateControllerAction($actionID, array $args = [], array $config = [])
    {
        $controller = $this->createMigrateController($config);
        ob_start();
        ob_implicit_flush(false);
        $controller->run($actionID, $args);

        return ob_get_clean();
    }

    /**
     * @param string $name
     * @param string|null $date
     * @return string generated class name
     */
    protected function createMigration($name, $date = null)
    {
        if ($date === null) {
            $date = gmdate('ymd_His');
        }
        $class = 'm' . $date . '_' . $name;
        $baseClass = $this->migrationBaseClass;

        $code = <<<CODE
<?php

class {$class} extends {$baseClass}
{
    public function up()
    {
    }

    public function down()
    {
    }
}
CODE;
        file_put_contents($this->migrationPath . DIRECTORY_SEPARATOR . $class . '.php', $code);
        return $class;
    }

    /**
     * @param string $name
     * @param string|null $date
     * @return string generated class name
     */
    protected function createNamespaceMigration($name, $date = null)
    {
        if ($date === null) {
            $date = gmdate('ymdHis');
        }
        $class = 'M' . $date . ucfirst($name);
        $baseClass = $this->migrationBaseClass;
        $namespace = $this->migrationNamespace;

        $code = <<<CODE
<?php

namespace {$namespace};

class {$class} extends \\{$baseClass}
{
    public function up()
    {
    }

    public function down()
    {
    }
}
CODE;
        file_put_contents($this->migrationPath . DIRECTORY_SEPARATOR . $class . '.php', $code);
        return $class;
    }

    /**
     * Checks if applied migration history matches expected one.
     * @param array $expectedMigrations migration names in expected order
     * @param string $message failure message
     */
    protected function assertMigrationHistory(array $expectedMigrations, $message = '')
    {
        $success = true;
        $migrationHistory = $this->getMigrationHistory();
        $appliedMigrations = $migrationHistory;
        foreach ($expectedMigrations as $expectedMigrationName) {
            $appliedMigration = array_shift($appliedMigrations);
            if (!fnmatch(strtr($expectedMigrationName, ['\\' => DIRECTORY_SEPARATOR]), strtr($appliedMigration['version'], ['\\' => DIRECTORY_SEPARATOR]))) {
                $success = false;
                break;
            }
        }
        if (!$success) {
            $message .= "\n";
            $message .= "Expected: " . var_export($expectedMigrations, true) . "\n";

            $actualMigrations = [];
            foreach ($migrationHistory as $row) {
                $actualMigrations[] = $row['version'];
            }
            $message .= "Actual: " . var_export($actualMigrations, true) . "\n";
        }
        $this->assertTrue($success, $message);
    }

    // Tests :

    public function testCreate()
    {
        $migrationName = 'test_migration';
        $this->runMigrateControllerAction('create', [$migrationName]);
        $files = FileHelper::findFiles($this->migrationPath);
        $this->assertCount(1, $files, 'Unable to create new migration!');
        $this->assertStringContainsString($migrationName, basename($files[0]), 'Wrong migration name!');
    }

    public function testUp()
    {
        $this->createMigration('test1');
        $this->createMigration('test2');

        $this->runMigrateControllerAction('up');

        $this->assertMigrationHistory(['m*_base', 'm*_test1', 'm*_test2']);
    }

    /**
     * @depends testUp
     */
    public function testUpCount()
    {
        $this->createMigration('test1');
        $this->createMigration('test2');

        $this->runMigrateControllerAction('up', [1]);

        $this->assertMigrationHistory(['m*_base', 'm*_test1']);
    }

    /**
     * @depends testUp
     */
    public function testDownCount()
    {
        $this->createMigration('test1');
        $this->createMigration('test2');

        $this->runMigrateControllerAction('up');
        $this->runMigrateControllerAction('down', [1]);

        $this->assertMigrationHistory(['m*_base', 'm*_test1']);
    }

    /**
     * @depends testDownCount
     */
    public function testDownAll()
    {
        $this->createMigration('test1');
        $this->createMigration('test2');

        $this->runMigrateControllerAction('up');
        $this->runMigrateControllerAction('down', ['all']);

        $this->assertMigrationHistory(['m*_base']);
    }

    /**
     * @depends testUp
     */
    public function testHistory()
    {
        $output = $this->runMigrateControllerAction('history');
        $this->assertStringContainsString('No migration', $output);

        $this->createMigration('test1');
        $this->createMigration('test2');
        $this->runMigrateControllerAction('up');

        $output = $this->runMigrateControllerAction('history');
        $this->assertStringContainsString('_test1', $output);
        $this->assertStringContainsString('_test2', $output);
    }

    /**
     * @depends testUp
     */
    public function testNew()
    {
        $this->createMigration('test1');

        $output = $this->runMigrateControllerAction('new');
        $this->assertStringContainsString('_test1', $output);

        $this->runMigrateControllerAction('up');

        $output = $this->runMigrateControllerAction('new');
        $this->assertStringNotContainsString('_test1', $output);
    }

    public function testMark()
    {
        $version = '010101_000001';
        $this->createMigration('mark1', $version);

        $this->runMigrateControllerAction('mark', [$version]);

        $this->assertMigrationHistory(['m*_base', 'm*_mark1']);
    }

    public function testTo()
    {
        $version = '020202_000001';
        $this->createMigration('to1', $version);

        $this->runMigrateControllerAction('to', [$version]);

        $this->assertMigrationHistory(['m*_base', 'm*_to1']);
    }

    /**
     * @depends testUp
     */
    public function testRedo()
    {
        $this->createMigration('redo');
        $this->runMigrateControllerAction('up');

        $this->runMigrateControllerAction('redo');

        $this->assertMigrationHistory(['m*_base', 'm*_redo']);
    }

    // namespace :

    /**
     * @depends testCreate
     */
    public function testNamespaceCreate()
    {
        // default namespace apply :
        $migrationName = 'testDefaultNamespace';
        $this->runMigrateControllerAction('create', [$migrationName], [
            'migrationPath' => null,
            'migrationNamespaces' => [$this->migrationNamespace]
        ]);
        $files = FileHelper::findFiles($this->migrationPath);
        $fileContent = file_get_contents($files[0]);
        $this->assertStringContainsString("namespace {$this->migrationNamespace};", $fileContent);
        $this->assertMatchesRegularExpression('/class M[0-9]{12}' . ucfirst($migrationName) . '/s', $fileContent);
        unlink($files[0]);

        // namespace specify :
        $migrationName = 'test_namespace_specify';
        $this->runMigrateControllerAction('create', [$this->migrationNamespace . '\\' . $migrationName], [
            'migrationPath' => $this->migrationPath,
            'migrationNamespaces' => [$this->migrationNamespace]
        ]);
        $files = FileHelper::findFiles($this->migrationPath);
        $fileContent = file_get_contents($files[0]);
        $this->assertStringContainsString("namespace {$this->migrationNamespace};", $fileContent);
        unlink($files[0]);

        // no namespace:
        $migrationName = 'test_no_namespace';
        $this->runMigrateControllerAction('create', [$migrationName], [
            'migrationPath' => $this->migrationPath,
            'migrationNamespaces' => [$this->migrationNamespace]
        ]);
        $files = FileHelper::findFiles($this->migrationPath);
        $fileContent = file_get_contents($files[0]);
        $this->assertStringNotContainsString("namespace {$this->migrationNamespace};", $fileContent);
    }

    /**
     * @depends testUp
     */
    public function testNamespaceUp()
    {
        $this->createNamespaceMigration('nsTest1');
        $this->createNamespaceMigration('nsTest2');

        $this->runMigrateControllerAction('up', [], [
            'migrationPath' => null,
            'migrationNamespaces' => [$this->migrationNamespace]
        ]);

        $this->assertMigrationHistory([
            'm*_*_base',
            $this->migrationNamespace . '\\M*NsTest1',
            $this->migrationNamespace . '\\M*NsTest2',
        ]);
    }

    /**
     * @depends testNamespaceUp
     * @depends testDownCount
     */
    public function testNamespaceDownCount()
    {
        $this->createNamespaceMigration('down1');
        $this->createNamespaceMigration('down2');

        $controllerConfig = [
            'migrationPath' => null,
            'migrationNamespaces' => [$this->migrationNamespace]
        ];
        $this->runMigrateControllerAction('up', [], $controllerConfig);
        $this->runMigrateControllerAction('down', [1], $controllerConfig);

        $this->assertMigrationHistory([
            'm*_*_base',
            $this->migrationNamespace . '\\M*Down1',
        ]);
    }

    /**
     * @depends testNamespaceUp
     * @depends testHistory
     */
    public function testNamespaceHistory()
    {
        $controllerConfig = [
            'migrationPath' => null,
            'migrationNamespaces' => [$this->migrationNamespace]
        ];

        $output = $this->runMigrateControllerAction('history', [], $controllerConfig);
        $this->assertStringContainsString('No migration', $output);

        $this->createNamespaceMigration('history1');
        $this->createNamespaceMigration('history2');
        $this->runMigrateControllerAction('up', [], $controllerConfig);

        $output = $this->runMigrateControllerAction('history', [], $controllerConfig);
        $this->assertMatchesRegularExpression('/' . preg_quote($this->migrationNamespace) . '.*History1/s', $output);
        $this->assertMatchesRegularExpression('/' . preg_quote($this->migrationNamespace) . '.*History2/s', $output);
    }

    /**
     * @depends testMark
     */
    public function testNamespaceMark()
    {
        $controllerConfig = [
            'migrationPath' => null,
            'migrationNamespaces' => [$this->migrationNamespace]
        ];

        $version = '010101000001';
        $this->createNamespaceMigration('mark1', $version);

        $this->runMigrateControllerAction('mark', [$this->migrationNamespace . '\\M' . $version], $controllerConfig);

        $this->assertMigrationHistory(['m*_base', $this->migrationNamespace . '\\M*Mark1']);
    }

    /**
     * @depends testTo
     */
    public function testNamespaceTo()
    {
        $controllerConfig = [
            'migrationPath' => null,
            'migrationNamespaces' => [$this->migrationNamespace]
        ];

        $version = '020202000020';
        $this->createNamespaceMigration('to1', $version);

        $this->runMigrateControllerAction('to', [$this->migrationNamespace . '\\M' . $version], $controllerConfig);

        $this->assertMigrationHistory(['m*_base', $this->migrationNamespace . '\\M*To1']);
    }

    /**
     * @depends testNamespaceHistory
     *
     * @see https://github.com/yiisoft/yii2-mongodb/issues/170
     */
    public function testGetMigrationHistory()
    {
        $connection = $this->getConnection();

        $controllerConfig = [
            'migrationPath' => null,
            'migrationNamespaces' => [$this->migrationNamespace]
        ];

        $controller = $this->createMigrateController($controllerConfig);
        $controller->db = $this->getConnection();

        $connection->createCommand()->batchInsert('migration', [
            [
                'version' => 'app\migrations\M140506102106One',
                'apply_time' => 10,
            ],
            [
                'version' => 'app\migrations\M160909083544Two',
                'apply_time' => 10,
            ],
            [
                'version' => 'app\modules\foo\migrations\M161018124749Three',
                'apply_time' => 10,
            ],
            [
                'version' => 'app\migrations\M160930135248Four',
                'apply_time' => 20,
            ],
            [
                'version' => 'app\modules\foo\migrations\M161025123028Five',
                'apply_time' => 20,
            ],
            [
                'version' => 'app\migrations\M161110133341Six',
                'apply_time' => 20,
            ],
        ]);

        $rows = $this->invokeMethod($controller, 'getMigrationHistory', [10]);

        $this->assertSame(
            [
                'app\migrations\M161110133341Six',
                'app\modules\foo\migrations\M161025123028Five',
                'app\migrations\M160930135248Four',
                'app\modules\foo\migrations\M161018124749Three',
                'app\migrations\M160909083544Two',
                'app\migrations\M140506102106One',
            ],
            array_keys($rows)
        );

        $rows = $this->invokeMethod($controller, 'getMigrationHistory', [4]);

        $this->assertSame(
            [
                'app\migrations\M161110133341Six',
                'app\modules\foo\migrations\M161025123028Five',
                'app\migrations\M160930135248Four',
                'app\modules\foo\migrations\M161018124749Three',
            ],
            array_keys($rows)
        );
    }

    /**
     * @depends testUp
     */
    public function testRefreshMigration()
    {
        if (!is_callable(['yii\console\controllers\BaseMigrateController', 'actionFresh'])) {
            $this->markTestSkipped('Method "yii\console\controllers\BaseMigrateController::actionFresh()" does not exist in this Yii framework version.');
        }

        $connection = $this->getConnection();

        $collection = $connection->getCollection('hall_of_fame');
        $collection->insert(['name' => 'Qiang Xue']);
        $collection->insert(['name' => 'Alexander Makarov']);

        $result = $this->runMigrateControllerAction('fresh');

        $this->assertStringContainsString('Collection hall_of_fame dropped.', $result);
        $this->assertStringContainsString('No new migrations found. Your system is up-to-date.', $result);

        $this->assertEmpty($connection->getDatabase()->listCollections(['name' => $collection->name]));
    }
}
