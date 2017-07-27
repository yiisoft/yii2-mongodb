Использование GridFS
============

Расширение поддерживает [MongoGridFS](https://docs.mongodb.com/manual/core/gridfs/) с помощью классов из пространства имен "\yii\mongodb\file".
Там вы найдете классы Collection, Query и ActiveRecord.

Вы можете загрузить файл с помощью [[\yii\mongodb\file\Upload]]:

```php
$document = Yii::$app->mongodb->getFileCollection()->createUpload()
    ->addContent('Part 1')
    ->addContent('Part 2')
    // ...
    ->complete();
```

Вы можете скачать файл с помощью [[\yii\mongodb\file\Download]]:

```php
Yii::$app->mongodb->getFileCollection()->createDownload($document['_id'])->toFile('/path/to/file.dat');
```

Каждая строка, файла результата запроса, содержит ключ 'file' экземпляра [[\yii\mongodb\file\Download]]:

```php
use yii\mongodb\file\Query;

$query = new Query();
$rows = $query->from('fs')
    ->limit(10)
    ->all();

foreach ($rows as $row) {
    var_dump($row['file']); // вывод: "object(\yii\mongodb\file\Download)"
    echo $row['file']->toString(); // содержание файла вывода
}
```

С помощью [\yii\mongodb\file\ActiveRecord]] вы можете манипулировать файлами используя свойство 'file':

```php
use yii\mongodb\file\ActiveRecord;

class ImageFile extends ActiveRecord
{
    //...
}

$record = new ImageFile();
$record->number = 15;
$record->file = '/path/to/some/file.jpg'; // локальная загрузка файла GridFS
$record->save();

$record = ImageFile::find()->where(['number' => 15])->one();
var_dump($record->file); // вывод: "object(\yii\mongodb\file\Download)"
echo $row['file']->toString(); // содержание файла вывода
```

Вы также можете управлять файлами GridFS через регулярные потоки ресурсов PHP.
Вам нужно будет зарегистрировать обертку потока предоставленную этим расширением - [[\yii\mongodb\file\StreamWrapper]].
Это может быть сделано с помощью [[\yii\mongodb\file\Connection::registerFileStreamWrapper()]].
После того как обертка потока зарегистрирована, вы можете открыть поток используя следующий формат:

```
'protocol://databaseName.fileCollectionPrefix?file_attribute=value'
```

Для примера:

```php
Yii::$app->mongodb->registerFileStreamWrapper(); // register stream wrapper

// запись файла:
$resource = fopen('gridfs://mydatabase.fs?filename=new_file.txt', 'w');
fwrite($resource, 'some content');
// ...
fclose($resource);

// запись файла с несколькими полями:
$resource = fopen('gridfs://mydatabase.fs?filename=new_file.txt&number=17&status=active', 'w');
fwrite($resource, 'file number 17 with status "active"');
fclose($resource);

// чтение файла:
$resource = fopen('gridfs://mydatabase.fs?filename=my_file.txt', 'r');
$fileContent = stream_get_contents($resource);
```
