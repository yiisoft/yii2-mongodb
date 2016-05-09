MongoDB DebugPanel を使用する
=============================

Yii 2 MongoDB エクステンションは、Yii のデバッグモジュールと統合できるデバッグパネルを提供しています。
これは実行された MongoDB クエリを表示するものです。

これを有効にするためには、下記のコードをあなたのアプリケーションの構成情報に追加してください
(既にデバッグモジュールを有効にしている場合は、パネルの構成を追加するだけで十分です)。

```php
    // ...
    'bootstrap' => ['debug'],
    'modules' => [
        'debug' => [
            'class' => 'yii\\debug\\Module',
            'panels' => [
                'mongodb' => [
                    'class' => 'yii\\mongodb\\debug\\MongoDbPanel',
                     // 'db' => 'mongodb', // MongoDB のコンポーネント ID。デフォルトは `db`。
                                           // MongoDB のコンポーネントを別の ID で登録した場合は、コメントを外して書き換えること。
                ],
            ],
        ],
    ],
    // ...
```
