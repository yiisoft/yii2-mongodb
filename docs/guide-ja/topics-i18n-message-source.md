I18N メッセージ・ソースを使用する
=================================

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


[[\yii\mongodb\i18n\MongoDbMessageSource]] は全ての翻訳を保存するのに単一のコレクションを使用します。
このコレクションの全てのエントリは次の三つのフィールドを持っていなければなりません。

 - language: 文字列、翻訳言語
 - category: 文字列、翻訳カテゴリ名
 - messages: 配列、実際のメッセージ翻訳の配列。各要素において、'message' キーは翻訳される生のメッセージ、`translation` キーは翻訳されたメッセージ。

例えば、

```json
{
    "category": "app",
    "language": "ja",
    "messages": [
        {
            "message": "Hello world!",
            "translation": "こんにちは、皆さん!"
        },
        {
            "message": "The dog runs fast.",
            "translation": "犬は速く走る。",
        },
        ...
    ],
}
```

'messages' フィールドは、ソース・メッセージを BSON のキーとし、翻訳を値として保持する形で指定することも出来ます。
例えば、

```json
{
    "category": "app",
    "language": "de",
    "messages": {
        "Hello world!": "こんにちは、皆さん!",
        "See more": "更に見る",
        ...
    },
}
```

ただし、BSON のキーは `.` や `$` などの記号を含むことが出来ませんので、この手法は推奨されません。

構成と翻訳コレクションのデータ構造に関する詳細は [[\yii\mongodb\i18n\MongoDbMessageSource]] を参照してください。
