<?php

namespace yiiunit\extensions\mongodb\embedded;

use yii\base\Model;
use yii\mongodb\embedded\ContainerInterface;
use yii\mongodb\embedded\ContainerTrait;
use yii\mongodb\embedded\Validator;
use yiiunit\extensions\mongodb\TestCase;

class ValidatorTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
    }

    public function testValidate()
    {
        $model = new UserModel();
        $model->name = 'some name';
        $this->assertFalse($model->validate());
        $this->assertContains(' is invalid', $model->getFirstError('contactData'));
        $this->assertContains(' cannot be blank', $model->contact->getFirstError('email'));

        $model = new UserModel();
        $model->name = 'some name';
        $model->contact->email = 'invalid email address';
        $this->assertFalse($model->validate());
        $this->assertContains(' is invalid', $model->getFirstError('contactData'));
        $this->assertContains(' is not a valid email address', $model->contact->getFirstError('email'));

        $model = new UserModel();
        $model->name = 'some name';
        $model->contact->email = 'user@domain.com';
        $this->assertTrue($model->validate());
    }

    /**
     * @depends testValidate
     */
    public function testValidateList()
    {
        $model = new UserModel();
        $model->name = 'some name';
        $model->contact->email = 'user@domain.com';
        $model->messages[] = new MessageModel();
        $model->messages[] = new MessageModel();
        $this->assertFalse($model->validate());
        $this->assertContains(' is invalid', $model->getFirstError('messagesData'));
        $this->assertContains(' cannot be blank', $model->messages[0]->getFirstError('content'));
        $this->assertContains(' cannot be blank', $model->messages[1]->getFirstError('content'));

        $model = new UserModel();
        $model->name = 'some name';
        $model->contact->email = 'user@domain.com';
        $model->messages[] = new MessageModel(['content' => 'content 1']);
        $model->messages[] = new MessageModel(['content' => 'content 2']);
        $this->assertTrue($model->validate());
    }
}

/**
 * @property ContactModel $contact
 * @property MessageModel[] $messages
 */
class UserModel extends Model implements ContainerInterface
{
    use ContainerTrait;

    public $name;
    public $contactData;
    public $messagesData;

    public function rules()
    {
        return [
            ['name', 'required'],
            ['contact', Validator::className()],
            ['messages', Validator::className()],
        ];
    }

    public function embedContact()
    {
        return $this->mapEmbedded('contactData', ContactModel::className());
    }

    public function embedMessages()
    {
        return $this->mapEmbeddedList('messagesData', MessageModel::className());
    }
}

class ContactModel extends Model
{
    public $email;

    public function rules()
    {
        return [
            ['email', 'required'],
            ['email', 'email'],
        ];
    }
}

class MessageModel extends Model
{
    public $content;

    public function rules()
    {
        return [
            ['content', 'required'],
        ];
    }
}