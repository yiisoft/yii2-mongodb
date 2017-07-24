GridFS を使用する
=================

このエクステンションは、名前空間 "\yii\mongodb\file" の下にある諸クラスによって [MongoGridFS](https://docs.mongodb.com/manual/core/gridfs/) をサポートしています。
そこに　GridFS のためのコレクション、クエリ、アクティブレコードのクラスがあります。

[[\yii\mongodb\file\Upload]] を使ってファイルをアップロードすることが出来ます。

```php
$document = Yii::$app->mongodb->getFileCollection()->createUpload()
    ->addContent('Part 1')
    ->addContent('Part 2')
    // ...
    ->complete();
```

[[\yii\mongodb\file\Download]] を使ってファイルをダウンロードすることが出来ます。

```php
Yii::$app->mongodb->getFileCollection()->createDownload($document['_id'])->toFile('/path/to/file.dat');
```

ファイルクエリの結果の各行は、'file' というキーで [[\yii\mongodb\file\Download]] のインスタンスを含みます。

```php
use yii\mongodb\file\Query;

$query = new Query();
$rows = $query->from('fs')
    ->limit(10)
    ->all();

foreach ($rows as $row) {
    var_dump($row['file']); // 出力: "object(\yii\mongodb\file\Download)"
    echo $row['file']->toString(); // ファイルのコンテントを出力
}
```

[\yii\mongodb\file\ActiveRecord]] を使うと、'file' プロパティを使ってファイルを操作することが出来ます。

```php
use yii\mongodb\file\ActiveRecord;

class ImageFile extends ActiveRecord
{
    //...
}

$record = new ImageFile();
$record->number = 15;
$record->file = '/path/to/some/file.jpg'; // ローカルのファイルを GridFS にアップロード
$record->save();

$record = ImageFile::find()->where(['number' => 15])->one();
var_dump($record->file); // 出力: "object(\yii\mongodb\file\Download)"
echo $row['file']->toString(); // ファイルのコンテントを出力
```

GridFS のファイルを通常の PHP ストリームリソースを通じて操作することも出来ます。
そのためには、このエクステンションによって提供されるストリームラッパー [[\yii\mongodb\file\StreamWrapper]] を登録する必要があります。
登録は [[\yii\mongodb\file\Connection::registerFileStreamWrapper()]] によって行うことが出来ます。
ストリームラッパーを登録すれば、次のフォーマットを使ってストリームリソースをオープンすることが出来ます。

```
'protocol://databaseName.fileCollectionPrefix?file_attribute=value'
```

例えば、

```php
Yii::$app->mongodb->registerFileStreamWrapper(); // ストリームラッパーを登録

// ファイルを書き込む
$resource = fopen('gridfs://mydatabase.fs?filename=new_file.txt', 'w');
fwrite($resource, '何らかのコンテント');
// ...
fclose($resource);

// いくつかのフィールドを持つファイルを書き込む
$resource = fopen('gridfs://mydatabase.fs?filename=new_file.txt&number=17&status=active', 'w');
fwrite($resource, 'ファイル番号 = 17, ステータス = "active"');
fclose($resource);

// ファイルを読み出す
$resource = fopen('gridfs://mydatabase.fs?filename=my_file.txt', 'r');
$fileContent = stream_get_contents($resource);
```
