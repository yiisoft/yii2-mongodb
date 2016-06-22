Basic Usage
===========

Once you have a MongoDB connection instance, you can execute a MongoDB commands and queries
using [[yii\mongodb\Command]]:

```php
// execute command:
$result = Yii::$app->mongodb->createCommand(['listIndexes' => 'some_collection'])->execute();

// execute query (find):
$cursor = Yii::$app->mongodb->createCommand(['projection' => ['name' => true]])->query('some_collection');

// execute batch (bulk) operations:
Yii::$app->mongodb->createCommand()
    ->addInsert(['name' => 'new'])
    ->addUpdate(['name' => 'existing'], ['name' => 'updated'])
    ->addDelete(['name' => 'old'])
    ->executeBatch('customer');
```

Using the connection instance you may access databases and collections.
Most of the MongoDB commands are accessible via [[\yii\mongodb\Collection]] instance:

```php
$collection = Yii::$app->mongodb->getCollection('customer');
$collection->insert(['name' => 'John Smith', 'status' => 1]);
```

To perform "find" queries, you should use [[\yii\mongodb\Query]]:

```php
use yii\mongodb\Query;

$query = new Query();
// compose the query
$query->select(['name', 'status'])
    ->from('customer')
    ->limit(10);
// execute the query
$rows = $query->all();
```

