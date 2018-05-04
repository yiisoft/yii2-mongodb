基本的な使用方法
================

一旦 MongoDB 接続インスタンスを取得すれば、
[[yii\mongodb\Command]] を使って MongoDB のコマンドとクエリを実行することが出来ます。

```php
// コマンドを実行する
$result = Yii::$app->mongodb->createCommand(['listIndexes' => 'some_collection'])->execute();

// クエリ (find) を実行する
$cursor = Yii::$app->mongodb->createCommand(['projection' => ['name' => true]])->query('some_collection');

// バッチ (バルク) オペレーションを実行する
Yii::$app->mongodb->createCommand()
    ->addInsert(['name' => 'new'])
    ->addUpdate(['name' => 'existing'], ['name' => 'updated'])
    ->addDelete(['name' => 'old'])
    ->executeBatch('customer');
```

接続のインスタンスを使用して、データベースとコレクションにアクセスすることが出来ます。
ほとんどの MongoDB コマンドは [[\yii\mongodb\Collection]] によってアクセスすることが出来ます。

```php
$collection = Yii::$app->mongodb->getCollection('customer');
$collection->insert(['name' => 'John Smith', 'status' => 1]);
```

"find" クエリを実行するためには、[[\yii\mongodb\Query]] を使わなければなりません。

```php
use yii\mongodb\Query;

$query = new Query();
// クエリを構築する
$query->select(['name', 'status'])
    ->from('customer')
    ->limit(10);
// クエリを実行する
$rows = $query->all();
```

