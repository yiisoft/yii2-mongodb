Aggregation
===========

This extension provides support for the [MongoDB aggregation functionality](https://docs.mongodb.com/manual/aggregation/) wrapping corresponding commands into PHP methods of [[\yii\mongodb\Command]].


Single Purpose Aggregation Operations
-------------------------------------

The simplest MongoDB aggregation operations are `count` and `distinct`, which are available via [[\yii\mongodb\Command::count()]]
and [[\yii\mongodb\Command::distinct()]] correspondingly. For example:

```php
$booksCount = Yii::$app->mongodb->createCommand()->count('books', ['category' => 'programming']);
```

You may as well use [[\yii\mongodb\Collection::count()]] and [[\yii\mongodb\Collection::distinct()]] shortcut methods:

```php
$booksCount = Yii::$app->mongodb->getCollection('books')->count(['category' => 'programming']);
```

Methods `count()` and `distinct()` are also available at [[\yii\mongodb\Query]] class:

```php
$booksCount = (new Query())
    ->from('books')
    ->where(['category' => 'programming'])
    ->count();
```


Pipeline
--------

[Aggregation Pipeline](https://docs.mongodb.com/manual/core/aggregation-pipeline/) can be executed via [[\yii\mongodb\Command::aggregate()]].
The following example display how you can group books by `authorId` field:

```php
$authors = Yii::$app->mongodb->createCommand()->aggregate('books', [
    [
        '$group' => [
            '_id' => '$authorId',
        ],
    ],
]);
```

You may as well use [[\yii\mongodb\Collection::aggregate()]] as shortcut.
In the following example we are grouping books by both `authorId` and `category` fields:

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

Multiple pipelines can be specified for more sophisticated aggregation.
In the following example we are grouping books by `authorId` field, sorting them by `createdAt` field descending
and then we are limiting the result to 100 documents skipping first 300 records.

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

Please refer to [MongoDB Aggregation Pipeline Docs](https://docs.mongodb.com/manual/core/aggregation-pipeline/) for detailed information
about pipeline specifications.


## Aggregation via [[\yii\mongodb\Query]]

Simple aggregations can be performed via following methods of the [[\yii\mongodb\Query]] class:

 - `sum()` - returns the sum of the specified column values.
 - `average()` - returns the average of the specified column values.
 - `min()` - returns the minimum of the specified column values.
 - `max()` - returns the maximum of the specified column values.

In case of these methods invocation [[\yii\mongodb\Query::$where]] will be used for `$match` pipeline composition.

```php
use yii\mongodb\Query;

$maxPrice = (new Query())
    ->from('books')
    ->where(['name' => ['$ne' => '']])
    ->max('price', $db);
```


Map Reduce
----------

[Map Reduce](https://docs.mongodb.com/manual/core/map-reduce/) can be executed via [[\yii\mongodb\Command::mapReduce()]].

```php
$result = Yii::$app->mongodb->createCommand()->mapReduce('books',
    'function () {emit(this.status, this.amount)}',
    'function (key, values) {return Array.sum(values)}',
    'mapReduceOut',
    ['status' => ['$lt' => 3]]
);
```

You may as well use [[\yii\mongodb\Collection::mapReduce()]] as shortcut.

```php
$result = Yii::$app->mongodb->getCollection('books')->mapReduce(
    'function () {emit(this.status, this.amount)}',
    'function (key, values) {return Array.sum(values)}',
    'mapReduceOut',
    ['status' => ['$lt' => 3]]
);
```
