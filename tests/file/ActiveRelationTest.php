<?php

namespace yiiunit\extensions\mongodb\file;

use yiiunit\extensions\mongodb\data\ar\Customer;
use yiiunit\extensions\mongodb\data\ar\file\CustomerFile;
use yiiunit\extensions\mongodb\TestCase;

/**
 * @group mongodb
 */
class ActiveRelationTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        \yiiunit\extensions\mongodb\data\ar\ActiveRecord::$db = $this->getConnection();
        \yiiunit\extensions\mongodb\data\ar\file\ActiveRecord::$db = $this->getConnection();
        $this->setUpTestRows();
    }

    protected function tearDown()
    {
        $this->dropCollection(Customer::collectionName());
        $this->dropCollection(CustomerFile::collectionName());
        parent::tearDown();
    }

    /**
     * Sets up test rows.
     */
    protected function setUpTestRows()
    {
        $fileCollection = $this->getConnection()->getFileCollection(CustomerFile::collectionName());
        $customers = [];
        $files = [];
        for ($i = 1; $i <= 5; $i++) {
            $file = [
                'tag' => 'tag' . $i,
                'status' => $i,
            ];
            $content = 'content' . $i;
            $file['_id'] = $fileCollection->insertFileContent($content, $file);
            $file['content'] = $content;
            $files[] = $file;

            $customers[] = [
                'name' => 'name' . $i,
                'email' => 'email' . $i,
                'address' => 'address' . $i,
                'status' => $i,
                'file_id' => $file['_id'],
            ];
        }
        $customerCollection = $this->getConnection()->getCollection(Customer::collectionName());
        $customers = $customerCollection->batchInsert($customers);
    }

    // Tests :

    public function testFindLazy()
    {
        /* @var $customer Customer */
        $customer = Customer::findOne(['status' => 2]);
        $this->assertFalse($customer->isRelationPopulated('file'));
        $file = $customer->file;
        $this->assertTrue($customer->isRelationPopulated('file'));
        $this->assertTrue($file instanceof CustomerFile);
        $this->assertEquals((string) $file->_id, (string) $customer->file_id);
        $this->assertEquals(1, count($customer->relatedRecords));
    }

    public function testFindEager()
    {
        /* @var $customers Customer[] */
        $customers = Customer::find()->with('file')->all();
        $this->assertEquals(5, count($customers));
        $this->assertTrue($customers[0]->isRelationPopulated('file'));
        $this->assertTrue($customers[1]->isRelationPopulated('file'));
        $this->assertTrue($customers[0]->file instanceof CustomerFile);
        $this->assertEquals((string) $customers[0]->file->_id, (string) $customers[0]->file_id);
        $this->assertTrue($customers[1]->file instanceof CustomerFile);
        $this->assertEquals((string) $customers[1]->file->_id, (string) $customers[1]->file_id);
    }
}