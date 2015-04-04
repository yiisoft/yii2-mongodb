マイグレーションを使用する
==========================

MongoDB はスキーマレスであり、欠落しているコレクションはすべて最初に要求されたときに作成されます。
しかし、MongoDB データベースに永続的な変更を適用する必要があることはよくあります。
例えば、何らかの特定のオプションを指定してコレクションを作成したり、インデックスを作成したりする場合です。
MongoDB のマイグレーションは [[yii\mongodb\console\controllers\MigrateController]] によって管理されます。
これは通常の [[\yii\console\controllers\MigrateController]] に類似したものです。

このコマンドを有効にするためには、コンソールアプリケーションの構成を次のように修正しなければなりません。

```php
return [
    // ...
    'controllerMap' => [
        'mongodb-migrate' => 'yii\mongodb\console\controllers\MigrateController'
    ],
];
```

以下に、このコマンドのよくある使用方法をいくつか示します。

```
# 'create_user_collection' という名前の新しいマイグレーションを作成する
yii mongodb-migrate/create create_user_collection

# 全ての新しいマイグレーションを適用する
yii mongodb-migrate

# 最後に適用されたマイグレーションを取り消す
yii mongodb-migrate/down
```
