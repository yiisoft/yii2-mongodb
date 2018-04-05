Aggregation
===========

このエクステンションは、対応するコマンドを [[\yii\mongodb\Command]] PHP メソッドにラップして、[MongoDB aggregation 機能](https://docs.mongodb.com/manual/aggregation/) に対するサポートを提供しています。


単一目的の Aggregation 操作
---------------------------

最も単純な MongoDB の aggregation 操作は `count` と `distinct` です。これらは、それぞれ、[[\yii\mongodb\Command::count()]]
と [[\yii\mongodb\Command::distinct()]] によって利用可能です。例えば、

```php
$booksCount = Yii::$app->mongodb->createCommand()->count('books', ['category' => 'programming']);
```

[[\yii\mongodb\Collection::count()]] および [[\yii\mongodb\Collection::distinct()]] のショートカット・メソッドを使っても構いません。

```php
$booksCount = Yii::$app->mongodb->getCollection('books')->count(['category' => 'programming']);
```

`count()` および `distinct()` のメソッドは、[[\yii\mongodb\Query]] クラスでも利用可能です。

```php
$booksCount = (new Query())
    ->from('books')
    ->where(['category' => 'programming'])
    ->count();
```


パイプライン
------------

[Aggregation パイプライン](https://docs.mongodb.com/manual/core/aggregation-pipeline/) を [[\yii\mongodb\Command::aggregate()]] によって実行することが出来ます。
次のサンプルは、`authorId` フィールドで本をグループ化する方法を示すものです。

```php
$authors = Yii::$app->mongodb->createCommand()->aggregate('books', [
    [
        '$group' => [
            '_id' => '$authorId',
        ],
    ],
]);
```

ショートカットとして [[\yii\mongodb\Collection::aggregate()]] を使うことも出来ます。
次の例では、`authorId` と `category` のフィールドで本をグループ化しています。

```php
$collection = Yii::$app->mongodb->getCollection('books');
$authors = $collection->aggregate([
    [
        '$group'   => [
            '_id'      => '$authorId',
            'category' => '$category',
        ],
    ],
]);
```

さらに洗練された aggregation のために、複数のパイプラインを指定することが出来ます。
次の例では、本を `authorId` フィールドでグループ化し、`createdAt` フィールドで降順にソートし、
そして、最初の 300 レコードをスキップして、結果を 100 ドキュメントまでに限定しています。

```php
$collection = Yii::$app->mongodb->getCollection('books');
$authors = $collection->aggregate([
    [
        '$match' => [
            'name' => ['$ne' => ''],
        ],
    ],
    [
        '$group' => [
            '_id' => '$authorId',
        ],
    ],
    [
        '$sort' => ['createdAt' => -1]
    ],
    [
        '$skip' => 300
    ],
    [
        '$limit' => 100
    ],
]);
```

パイプラインの仕様についての詳細は [MongoDB Aggregation Pipeline Docs](https://docs.mongodb.com/manual/core/aggregation-pipeline/) を参照して下さい。


## [[\yii\mongodb\Query]] による Aggregation

単純な aggregation は [[\yii\mongodb\Query]] クラスの次のメソッドによって実行することが出来ます。

 - `sum()` - 指定されたカラムの値の合計を返す。
 - `average()` - 指定されたカラムの値の平均値を返す。
 - `min()` - 指定されたカラムの値の最小値を返す。
 - `max()` - 指定されたカラムの値の最大値を返す。

これらのメソッドが呼び出されるときは [[\yii\mongodb\Query::$where]] がパイプライン組成時の `$match` として使用されます。

```php
use yii\mongodb\Query;

$maxPrice = (new Query())
    ->from('books')
    ->where(['name' => ['$ne' => '']])
    ->max('price', $db);
```


マップ・リデュース
------------------

[マップ・リデュース](https://docs.mongodb.com/manual/core/map-reduce/) は [[\yii\mongodb\Command::mapReduce()]] によって実行することが出来ます。

```php
$result = Yii::$app->mongodb->createCommand()->mapReduce('books',
    'function () {emit(this.status, this.amount)}',
    'function (key, values) {return Array.sum(values)}',
    'mapReduceOut',
    ['status' => ['$lt' => 3]]
);
```

[[\yii\mongodb\Collection::mapReduce()]] をショートカットとして使うことも出来ます。

```php
$result = Yii::$app->mongodb->getCollection('books')->mapReduce(
    'function () {emit(this.status, this.amount)}',
    'function (key, values) {return Array.sum(values)}',
    'mapReduceOut',
    ['status' => ['$lt' => 3]]
);
```
