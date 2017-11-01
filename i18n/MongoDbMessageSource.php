<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\i18n;

use yii\base\InvalidConfigException;
use yii\caching\Cache;
use yii\di\Instance;
use yii\i18n\MessageSource;
use yii\mongodb\Connection;
use yii\mongodb\Query;

/**
 * MongoDbMessageSource extends [[MessageSource]] and represents a message source that stores translated
 * messages in MongoDB collection.
 *
 * This message source uses single collection for the message translations storage, defined via [[collection]].
 * Each entry in this collection should have 3 fields:
 *
 * - language: string, translation language
 * - category: string, name translation category
 * - messages: array, list of actual message translations, in each element: the 'message' key is raw message name
 *   and 'translation' key - message translation.
 *
 * For example:
 *
 * ```json
 * {
 *     "category": "app",
 *     "language": "de",
 *     "messages": {
 *         {
 *             "message": "Hello world!",
 *             "translation": "Hallo Welt!"
 *         },
 *         {
 *             "message": "The dog runs fast.",
 *             "translation": "Der Hund rennt schnell.",
 *         },
 *         ...
 *     },
 * }
 * ```
 *
 * You also can specify 'messages' using source message as a direct BSON key, while its value holds the translation.
 * For example:
 *
 * ```json
 * {
 *     "category": "app",
 *     "language": "de",
 *     "messages": {
 *         "Hello world!": "Hallo Welt!",
 *         "See more": "Mehr sehen",
 *         ...
 *     },
 * }
 * ```
 *
 * However such approach is not recommended as BSON keys can not contain symbols like `.` or `$`.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.5
 */
class MongoDbMessageSource extends MessageSource
{
    /**
     * @var Connection|array|string the MongoDB connection object or the application component ID of the MongoDB connection.
     *
     * After the MongoDbMessageSource object is created, if you want to change this property, you should only assign
     * it with a MongoDB connection object.
     *
     * This can also be a configuration array for creating the object.
     */
    public $db = 'mongodb';
    /**
     * @var Cache|array|string the cache object or the application component ID of the cache object.
     * The messages data will be cached using this cache object.
     * Note, that to enable caching you have to set [[enableCaching]] to `true`, otherwise setting this property has no effect.
     *
     * After the MongoDbMessageSource object is created, if you want to change this property, you should only assign
     * it with a cache object.
     *
     * This can also be a configuration array for creating the object.
     * @see cachingDuration
     * @see enableCaching
     */
    public $cache = 'cache';
    /**
     * @var string|array the name of the MongoDB collection, which stores translated messages.
     * This collection is better to be pre-created with fields 'category' and 'language' indexed.
     */
    public $collection = 'message';
    /**
     * @var int the time in seconds that the messages can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     * @see enableCaching
     */
    public $cachingDuration = 0;
    /**
     * @var bool whether to enable caching translated messages
     */
    public $enableCaching = false;


    /**
     * Initializes the DbMessageSource component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * Configured [[cache]] component would also be initialized.
     * @throws InvalidConfigException if [[db]] is invalid or [[cache]] is invalid.
     */
    public function init()
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::className());
        if ($this->enableCaching) {
            $this->cache = Instance::ensure($this->cache, Cache::className());
        }
    }

    /**
     * Loads the message translation for the specified language and category.
     * If translation for specific locale code such as `en-US` isn't found it
     * tries more generic `en`.
     *
     * @param string $category the message category
     * @param string $language the target language
     * @return array the loaded messages. The keys are original messages, and the values
     * are translated messages.
     */
    protected function loadMessages($category, $language)
    {
        if ($this->enableCaching) {
            $key = [
                __CLASS__,
                $category,
                $language,
            ];
            $messages = $this->cache->get($key);
            if ($messages === false) {
                $messages = $this->loadMessagesFromDb($category, $language);
                $this->cache->set($key, $messages, $this->cachingDuration);
            }

            return $messages;
        }

        return $this->loadMessagesFromDb($category, $language);
    }

    /**
     * Loads the messages from MongoDB.
     * You may override this method to customize the message storage in the MongoDB.
     * @param string $category the message category.
     * @param string $language the target language.
     * @return array the messages loaded from database.
     */
    protected function loadMessagesFromDb($category, $language)
    {
        $fallbackLanguage = substr($language, 0, 2);
        $fallbackSourceLanguage = substr($this->sourceLanguage, 0, 2);

        $languages = [
            $language,
            $fallbackLanguage,
            $fallbackSourceLanguage
        ];

        $rows = (new Query())
            ->select(['language', 'messages'])
            ->from($this->collection)
            ->andWhere(['category' => $category])
            ->andWhere(['language' => array_unique($languages)])
            ->all($this->db);

        if (count($rows) > 1) {
            $languagePriorities = [
                $language => 1
            ];
            $languagePriorities[$fallbackLanguage] = 2; // language key may be already taken
            $languagePriorities[$fallbackSourceLanguage] = 3; // language key may be already taken

            usort($rows, function ($a, $b) use ($languagePriorities) {
                $languageA = $a['language'];
                $languageB = $b['language'];

                if ($languageA === $languageB) {
                    return 0;
                }
                if ($languagePriorities[$languageA] < $languagePriorities[$languageB]) {
                    return +1;
                }
                return -1;
            });
        }

        $messages = [];
        foreach ($rows as $row) {
            foreach ($row['messages'] as $key => $value) {
                // @todo drop message as key specification at 2.2
                if (is_array($value)) {
                    $messages[$value['message']] = $value['translation'];
                } else {
                    $messages[$key] = $value;
                }
            }
        }

        return $messages;
    }
}