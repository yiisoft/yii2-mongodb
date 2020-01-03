Using Migrations
================

MongoDB is schemaless and will create any missing collection on the first demand. However there are many cases, when
you may need applying persistent changes to the MongoDB database. For example: you may need to create a collection with
some specific options or create indexes.
MongoDB migrations are managed via [[yii\mongodb\console\controllers\MigrateController]], which is an analog of regular
[[\yii\console\controllers\MigrateController]].

In order to enable this command you should adjust the configuration of your console application:

```php
return [
    // ...
    'controllerMap' => [
        'mongodb-migrate' => 'yii\mongodb\console\controllers\MigrateController'
    ],
];
```

Below are some common usages of this command:

```
# creates a new migration named 'create_user_collection'
yii mongodb-migrate/create create_user_collection

# applies ALL new migrations
yii mongodb-migrate

# reverts the last applied migration
yii mongodb-migrate/down
```
## Special configuration for an application that uses more than one DB engine

In case your application uses multiple databases, example:  

- MySQL + MongoDB

If you run the migration commands, it will evaluate both MySQL and MongoDB migration files at the same time since both by default share the same folder.

**Problem: MongoDB will try to run MySQL's migration files and the other way around.**

In order to avoid that behavior, you can create a new folder called `mongodb` under your `migrations` folder, and then setup your console application like this:

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
