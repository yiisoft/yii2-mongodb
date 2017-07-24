Using GridFS
============

This extension supports [MongoGridFS](https://docs.mongodb.com/manual/core/gridfs/) via
classes under namespace "\yii\mongodb\file".
There you will find specific Collection, Query and ActiveRecord classes.

You can upload a file using [[\yii\mongodb\file\Upload]]:

```php
$document = Yii::$app->mongodb->getFileCollection()->createUpload()
    ->addContent('Part 1')
    ->addContent('Part 2')
    // ...
    ->complete();
```

You can download the file using [[\yii\mongodb\file\Download]]:

```php
Yii::$app->mongodb->getFileCollection()->createDownload($document['_id'])->toFile('/path/to/file.dat');
```

Each row of the file query result contains [[\yii\mongodb\file\Download]] instance at the key 'file':

```php
use yii\mongodb\file\Query;

$query = new Query();
$rows = $query->from('fs')
    ->limit(10)
    ->all();

foreach ($rows as $row) {
    var_dump($row['file']); // outputs: "object(\yii\mongodb\file\Download)"
    echo $row['file']->toString(); // outputs file content
}
```

Using [\yii\mongodb\file\ActiveRecord]] you can manipulate the file using 'file' property:

```php
use yii\mongodb\file\ActiveRecord;

class ImageFile extends ActiveRecord
{
    //...
}

$record = new ImageFile();
$record->number = 15;
$record->file = '/path/to/some/file.jpg'; // upload local file to GridFS
$record->save();

$record = ImageFile::find()->where(['number' => 15])->one();
var_dump($record->file); // outputs: "object(\yii\mongodb\file\Download)"
echo $row['file']->toString(); // outputs file content
```

You may as well operate GridFS files via regular PHP stream resource.
You will need to register a stream wrapper provided by this extension - [[\yii\mongodb\file\StreamWrapper]].
This can be done via [[\yii\mongodb\file\Connection::registerFileStreamWrapper()]].
Once stream wrapper is registered, you may open a stream resource using following format:

```
'protocol://databaseName.fileCollectionPrefix?file_attribute=value'
```

For example:

```php
Yii::$app->mongodb->registerFileStreamWrapper(); // register stream wrapper

// write a file:
$resource = fopen('gridfs://mydatabase.fs?filename=new_file.txt', 'w');
fwrite($resource, 'some content');
// ...
fclose($resource);

// write file with several fields:
$resource = fopen('gridfs://mydatabase.fs?filename=new_file.txt&number=17&status=active', 'w');
fwrite($resource, 'file number 17 with status "active"');
fclose($resource);

// read a file:
$resource = fopen('gridfs://mydatabase.fs?filename=my_file.txt', 'r');
$fileContent = stream_get_contents($resource);
```
