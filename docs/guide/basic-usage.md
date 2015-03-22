Basic Usage
===========

Using the connection instance you may access databases and collections.
Most of the MongoDB commands are accessible via [[\yii\mongodb\Collection]] instance:

```php
$collection = Yii::$app->mongodb->getCollection('customer');
$collection->insert(['name' => 'John Smith', 'status' => 1]);
```

To perform "find" queries, you should use [[\yii\mongodb\Query]]:

```php
use yii\mongodb\Query;

$query = new Query;
// compose the query
$query->select(['name', 'status'])
    ->from('customer')
    ->limit(10);
// execute the query
$rows = $query->all();
```

This extension supports logging and profiling, however log messages does not contain
actual text of the performed queries, they contains only a “close approximation” of it
composed on the values which can be extracted from PHP Mongo extension classes.
If you need to see actual query text, you should use specific tools for that.
