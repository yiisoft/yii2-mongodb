Основы использования
===========

После установки экземпляра соединения с MongoDB, вы можете выполнять MongoDB команды и запросы
используя [[yii\mongodb\Command]]:

```php
// выполнить команду:
$result = Yii::$app->mongodb->createCommand(['listIndexes' => 'some_collection'])->execute();

// выполнить запрос (find):
$cursor = Yii::$app->mongodb->createCommand(['projection' => ['name' => true]])->query('some_collection');

// выполнить пакетную операцию:
Yii::$app->mongodb->createCommand()
    ->addInsert(['name' => 'new'])
    ->addUpdate(['name' => 'existing'], ['name' => 'updated'])
    ->addDelete(['name' => 'old'])
    ->executeBatch('customer');
```

Используя экземпляр соединения, вы можете получить доступ к базам данным и коллекциям.
Большинство MongoDB команд доступны через [[\yii\mongodb\Collection]] например:

```php
$collection = Yii::$app->mongodb->getCollection('customer');
$collection->insert(['name' => 'John Smith', 'status' => 1]);
```

Для выполнения `find` запросов, вы должны использовать [[\yii\mongodb\Query]]:

```php
use yii\mongodb\Query;

$query = new Query();
// составление запроса
$query->select(['name', 'status'])
    ->from('customer')
    ->limit(10);
// выполнение запроса
$rows = $query->all();
```
