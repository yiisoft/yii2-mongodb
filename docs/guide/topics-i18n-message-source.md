Using the I18N Message Source
=============================

You may use [[\yii\mongodb\i18n\MongoDbMessageSource]] for the i18n message translations storage.
Application configuration example:

```php
return [
    //....
    'components' => [
        // ...
        'i18n' => [
            'translations' => [
                '*' => [
                    'class' => 'yii\mongodb\i18n\MongoDbMessageSource'
                ]
            ]
        ],
    ]
];
```

[[\yii\mongodb\i18n\MongoDbMessageSource]] uses single collection to store all translations.
Each entry in this collection should have 3 fields:

 - language: string, translation language
 - category: string, name translation category
 - messages: array, list of actual message translations, in each element: the 'message' key is raw message name
   and 'translation' key - message translation.

For example:

```json
{
    "category": "app",
    "language": "de",
    "messages": [
        {
            "message": "Hello world!",
            "translation": "Hallo Welt!"
        },
        {
            "message": "The dog runs fast.",
            "translation": "Der Hund rennt schnell.",
        },
        ...
    ],
}
```

You also can specify 'messages' using source message as a direct BSON key, while its value holds the translation.
For example:

```json
{
    "category": "app",
    "language": "de",
    "messages": {
        "Hello world!": "Hallo Welt!",
        "See more": "Mehr sehen",
        ...
    },
}
```

However such approach is not recommended as BSON keys can not contain symbols like `.` or `$`.

Please refer to [[\yii\mongodb\i18n\MongoDbMessageSource]] for more details about configuration and translation
collection data structure.
