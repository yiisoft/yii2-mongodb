<?php

namespace yiiunit\extensions\mongodb\i18n;

use yii\i18n\I18N;
use yii\mongodb\i18n\MongoDbMessageSource;
use yiiunit\extensions\mongodb\TestCase;

class MongoDbMessageSourceTest extends TestCase
{
    /**
     * @var I18N
     */
    public $i18n;


    protected function setUp()
    {
        $this->mockApplication();
        parent::setUp();
        $this->setupTestRows();
        $this->setupI18N();
    }

    protected function tearDown()
    {
        $this->dropCollection('message');
        parent::tearDown();
    }

    /**
     * Setup test rows.
     */
    protected function setupTestRows()
    {
        $db = $this->getConnection();
        $collection = $db->getCollection('message');
        $collection->batchInsert([
            [
                'language' => 'de',
                'category' => 'test',
                'messages' => [
                    'Hello world!' => 'Hallo Welt!'
                ],
            ],
            [
                'language' => 'de-DE',
                'category' => 'test',
                'messages' => [
                    [
                        'message' => 'The dog runs fast.',
                        'translation' => 'Der Hund rennt schnell.'
                    ],
                    [
                        'message' => 'His speed is about {n} km/h.',
                        'translation' => 'Seine Geschwindigkeit beträgt {n} km/h.'
                    ],
                    [
                        'message' => 'His name is {name} and his speed is about {n, number} km/h.',
                        'translation' => 'Er heißt {name} und ist {n, number} km/h schnell.'
                    ],
                ],
            ],
            [
                'language' => 'en-US',
                'category' => 'test',
                'messages' => [
                    [
                        'message' => 'The dog runs fast.',
                        'translation' => 'The dog runs fast (en-US).'
                    ]
                ],
            ],
            [
                'language' => 'ru-RU',
                'category' => 'test',
                'messages' => [
                    'Hello world!' => 'Здравствуй Мир! (ru-RU)'
                ],
            ],
            [
                'language' => 'ru',
                'category' => 'test',
                'messages' => [
                    'Hello world!' => 'Здравствуй Мир!',
                    [
                        'message' => 'The dog runs fast.',
                        'translation' => 'Собака бегает быстро.',
                    ],
                    [
                        'message' => 'There {n, plural, =0{no cats} =1{one cat} other{are # cats}} on lying on the sofa!',
                        'translation' => 'На диване {n, plural, =0{нет кошек} =1{лежит одна кошка} one{лежит # кошка} few{лежит # кошки} many{лежит # кошек} other{лежит # кошки}}!'
                    ],
                ],
            ],
        ]);
    }

    /**
     * Setup internal test [[I18N]] instance
     */
    protected function setupI18N()
    {
        $this->i18n = new I18N([
            'translations' => [
                '*' => new MongoDbMessageSource([
                    'db' => $this->getConnection(),
                    'sourceLanguage' => 'en-US',
                ])
            ]
        ]);
    }

    // Tests :

    public function testTranslate()
    {
        $msg = 'The dog runs fast.';

        // source = target. Should be returned as is.
        $this->assertEquals('The dog runs fast.', $this->i18n->translate('test', $msg, [], 'en-US'));

        // exact match
        $this->assertEquals('Der Hund rennt schnell.', $this->i18n->translate('test', $msg, [], 'de-DE'));

        // fallback to just language code with absent exact match
        $this->assertEquals('Собака бегает быстро.', $this->i18n->translate('test', $msg, [], 'ru-RU'));

        // fallback to just langauge code with present exact match
        $this->assertEquals('Hallo Welt!', $this->i18n->translate('test', 'Hello world!', [], 'de-DE'));
    }

    /**
     * @depends testTranslate
     */
    public function testTranslatePriority()
    {
        // Ensure fallback entry does not override main one:
        $this->assertEquals('Здравствуй Мир! (ru-RU)', $this->i18n->translate('test', 'Hello world!', [], 'ru-RU'));
    }
}