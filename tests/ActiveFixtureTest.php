<?php

namespace yiiunit\extensions\mongodb;

use Yii;
use yii\helpers\FileHelper;
use yii\helpers\VarDumper;
use yii\mongodb\ActiveFixture;
use yiiunit\extensions\mongodb\data\ar\Customer;

class ActiveFixtureTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
    }

    protected function tearDown()
    {
        $this->dropCollection(Customer::collectionName());
        FileHelper::removeDirectory(Yii::getAlias('@runtime/fixtures'));

        parent::tearDown();
    }

    // Tests :

    public function testLoadCollection()
    {
        /* @var $fixture ActiveFixture|\PHPUnit_Framework_MockObject_MockObject */
        $fixture = $this->getMockBuilder(ActiveFixture::className())
            ->setConstructorArgs([
                [
                    'db' => $this->getConnection(),
                    'collectionName' => Customer::collectionName()
                ]
            ])
            ->setMethods(['getData'])
            ->getMock();
        $fixture->expects($this->any())->method('getData')->will($this->returnValue([
            ['name' => 'name1'],
            ['name' => 'name2'],
        ]));

        $fixture->load();

        $rows = $this->findAll($this->getConnection()->getCollection(Customer::collectionName()));
        $this->assertCount(2, $rows);
    }

    public function testLoadClass()
    {
        /* @var $fixture ActiveFixture|\PHPUnit_Framework_MockObject_MockObject */
        $fixture = $this->getMockBuilder(ActiveFixture::className())
            ->setConstructorArgs([
                [
                    'db' => $this->getConnection(),
                    'collectionName' => Customer::collectionName()
                ]
            ])
            ->setMethods(['getData'])
            ->getMock();
        $fixture->expects($this->any())->method('getData')->will($this->returnValue([
            ['name' => 'name1'],
            ['name' => 'name2'],
        ]));

        $fixture->load();

        $rows = $this->findAll($this->getConnection()->getCollection(Customer::collectionName()));
        $this->assertCount(2, $rows);
    }

    /**
     * @depends testLoadCollection
     *
     * @see https://github.com/yiisoft/yii2-mongodb/pull/40
     */
    public function testLoadEmptyData()
    {
        /* @var $fixture ActiveFixture|\PHPUnit_Framework_MockObject_MockObject */
        $fixture = $this->getMockBuilder(ActiveFixture::className())
            ->setConstructorArgs([
                [
                    'db' => $this->getConnection(),
                    'collectionName' => Customer::collectionName()
                ]
            ])
            ->setMethods(['getData'])
            ->getMock();
        $fixture->expects($this->any())->method('getData')->will($this->returnValue([
            // empty
        ]));

        $fixture->load(); // should be no error

        $rows = $this->findAll($this->getConnection()->getCollection(Customer::collectionName()));
        $this->assertEmpty($rows);
    }

    /**
     * @depends testLoadCollection
     *
     * @see https://github.com/yiisoft/yii2-mongodb/issues/145
     */
    public function testDefaultDataFile()
    {
        $db = $this->getConnection();

        $fixturePath = Yii::getAlias('@runtime/fixtures');
        $fixtureDataPath = $fixturePath . DIRECTORY_SEPARATOR . 'data';
        FileHelper::createDirectory($fixtureDataPath);

        $className = 'TestFixture_' . sha1(get_class($this));
        $classDefinition = <<<PHP
<?php

class {$className} extends \yii\mongodb\ActiveFixture
{
}
PHP;

        $classFile = $fixturePath . DIRECTORY_SEPARATOR . $className . '.php';
        file_put_contents($classFile, $classDefinition);
        require_once($classFile);

        $fixtureData = [
            ['name' => 'name1'],
            ['name' => 'name2'],
        ];
        $fixtureDataFile = $fixtureDataPath . DIRECTORY_SEPARATOR . Customer::collectionName() . '.php';
        $fixtureDataContent = '<?php return ' . VarDumper::export($fixtureData) . ';';
        file_put_contents($fixtureDataFile, $fixtureDataContent);

        $fixtureData = [
            ['name' => 'name1'],
            ['name' => 'name2'],
            ['name' => 'name3'],
        ];
        $fixtureDataFile = $fixtureDataPath . DIRECTORY_SEPARATOR . $db->getDefaultDatabaseName() . '.' . Customer::collectionName() . '.php';
        $fixtureDataContent = '<?php return ' . VarDumper::export($fixtureData) . ';';
        file_put_contents($fixtureDataFile, $fixtureDataContent);

        /* @var $fixture ActiveFixture */

        $fixture = new $className([
            'db' => $db,
            'collectionName' => Customer::collectionName(),
        ]);
        $fixture->load();
        $rows = $this->findAll($this->getConnection()->getCollection(Customer::collectionName()));
        $this->assertCount(2, $rows);

        $fixture = new $className([
            'db' => $db,
            'collectionName' => [
                $db->getDefaultDatabaseName(),
                Customer::collectionName()
            ],
        ]);
        $fixture->load();
        $rows = $this->findAll($this->getConnection()->getCollection(Customer::collectionName()));
        $this->assertCount(3, $rows);
    }
}