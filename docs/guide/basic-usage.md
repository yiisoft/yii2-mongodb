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
#### Aggregation
Originally Mongodb [aggregate](https://docs.mongodb.com/manual/aggregation/) is accepting a pipeline, pipeline is a group of
stages which will executed sequentially, each stage do operation on it's previous stage results to filter the result.

You can use Yii aggregate method to do functions like grouping results by one or more field 
or even build more complex aggregation pipeline.
  
#### Group by one or more fields

This example display how you can group books by field $authorId
```php
$collection = Yii::$app->mongodb->getCollection('books');

$authors = $collection->aggregate(
            [
                [
                    '$group' => [
                        '_id' => '$authorId',
                    ],
                ],
             
            ]
        );
        
```

##### Group by multiple fields
In this example we are grouping books by both fields 'authorId' and 'category'

```php
$collection = Yii::$app->mongodb->getCollection('books');
$authors = $collection->aggregate(
            [
                [
                    '$group'   => [
                    '_id'      => '$authorId',
                    'category' => '$category',
                    ],
                ],
             
            ]
        );
        
```
#### Aggregation Projection
By default mongodb will return only the fields which defined explicitly on the query,
to return extra fields you can add them to your query like:


```php
$collection = Yii::$app->mongodb->getCollection('books');
$authors = $collection->aggregate(
            [
                [
                    '$group' => [
                        '_id'       => '$authorId',
                        'name'      => ['$first' => '$name'],
                        'publisher' => ['$first' => '$publisher'],

                    ],
                ],
                ['$sort' => ['createdAt' => -1]],
                ['$limit' => 100],
            ]
        );
        
```

or you can use [Projection](https://docs.mongodb.com/manual/reference/operator/aggregation/project/) ,

```php
$collection = Yii::$app->mongodb->getCollection('books');
$authors = $collection->aggregate(
            [
                [
                    '$group' => [
                        '_id' => '$authorId',
                    ],
                ],
                ['$sort' => ['createdAt' => -1]],
                ['$limit' => 100],
                ['$project' => 
                            [
                            'name'      => 1,
                            'cateogry'  => 1,
                            'publisher' => 1,
                            ],
                ],
            ]
        );
        
```

##### Group by and Count 
This example shows How to group results by field 'authorId' and count the number of books for each author
then sort them by this total.

```php
   $collection = app()->mongodb->getCollection(self::collectionName());

        $searches = $collection->aggregate(
            [
                [
                    '$group' => [
                        '_id'         => '$authorId',
                        'count'       => [
                            '$sum' => 1,
                        ],
                    ],
                ],
                [
                    '$sort' => ['count' => -1],
                ]
            ]
        );
```

#### Full Aggregation example
In this example we are grouping books by 'authorId'' field then sorting them by 'createdAt' field descending
and last we are limiting the result to 100 document only after skipping 300 record.

```php
$collection = Yii::$app->mongodb->getCollection('books');
$authors = $collection->aggregate(
            [
                [
                    '$match' => ['name' => [ '$ne' => '' ]  ],
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
            ]
        );
   
```
