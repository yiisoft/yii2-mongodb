MongoId の詳細
==============

MongoDB のドキュメント ID ("_id" フィールド) はスカラ値ではなく [[\MongoId]] クラスのインスタンスである、ということを記憶してください。
実際の Mongo ID 文字列を取得するためには、[[\MongoId]] インスタンスを文字列に型キャストしなければなりません。

```php
$query = new Query;
$row = $query->from('customer')->one();
var_dump($row['_id']); // "object(MongoId)" が出力される
var_dump((string) $row['_id']); // "string 'acdfgdacdhcbdafa'" が出力される
```

このことは、非常に役に立つこともありますが、しばしば問題を発生させます。
URL を作成したり、"_id" を他のストレージに保存しようとしたりする場合に、問題が生じます。
そのような場合には、[[\MongoId]] を文字列に変換することを忘れないでください。

```php
/* @var $this yii\web\View */
echo $this->createUrl(['item/update', 'id' => (string) $row['_id']]);
```

検索条件を構築する際には、'_id' キーの値は、単純な文字列である場合でも、自動的に [[\MongoId]] インスタンスにキャストされます。
従って、文字列で表された '_id' をあなたがキャストして戻す必要はありません。

```php
use yii\web\Controller;
use yii\mongodb\Query;

class ItemController extends Controller
{
    /**
     * @param string $id MongoId 文字列 (オブジェクトではない)
     */
    public function actionUpdate($id)
    {
        $query = new Query;
        $row = $query->from('item')
            where(['_id' => $id]) // [[\MongoId]] へ暗黙に型キャスト
            ->one();
        ...
    }
}
```

ただし、[[\MongoId]] を含む他のカラムがある場合は、型キャストが必要になるかもしれない可能性について、あなた自身が面倒を見なければなりません。