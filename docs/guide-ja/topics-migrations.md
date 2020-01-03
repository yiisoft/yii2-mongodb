マイグレーションを使用する
==========================

MongoDB はスキーマレスであり、欠落しているコレクションはすべて最初に要求されたときに作成されます。
しかし、MongoDB データベースに永続的な変更を適用する必要があることはよくあります。
例えば、何らかの特定のオプションを指定してコレクションを作成したり、インデックスを作成したりする場合です。
MongoDB のマイグレーションは [[yii\mongodb\console\controllers\MigrateController]] によって管理されます。
これは通常の [[\yii\console\controllers\MigrateController]] に類似したものです。

このコマンドを有効にするためには、コンソール・アプリケーションの構成を次のように修正しなければなりません。

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
## 二つ以上の DB エンジンを使用するアプリケーションのための特殊な構成

アプリケーションが複数のデータベースを使う場合の例です。

- MySQL + MongoDB

マイグレーション・コマンドを実行すると、同時に MySQL と MongoDB の両方のマイグレーション・ファイルが対象として扱われます。これは両者が既定では同じフォルダを共有するためです。

**問題: MongoDB が MySQL のマイグレーション・ファイルを実行しようとし、MySQL が MongoDB のマイグレーション・ファイルを実行しようとする。**

この問題を回避するためには、`migrations` フォルダの下に `mongodb` という新しいフォルダを作って、コンソール・アプリケーションを次のようにセットアップすることが出来ます。

```php
return [
    // ...
    'controllerMap' => [
        'mongodb-migrate' => [
          'class' => 'yii\mongodb\console\controllers\MigrateController',
          'migrationPath' => '@app/migrations/mongodb',
        ],
    ],
];
```
