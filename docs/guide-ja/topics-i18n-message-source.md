I18N メッセージソースを使用する
===============================

[[\yii\mongodb\i18n\MongoDbMessageSource]] を i18n メッセージ翻訳ストレージとして使用することができます。
アプリケーション構成の例:

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


構成と翻訳コレクションのデータ構造に関する詳細は [[\yii\mongodb\i18n\MongoDbMessageSource]] を参照してください。
