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

Please refer to [[\yii\mongodb\i18n\MongoDbMessageSource]] for more details about configuration and translation
collection data structure.