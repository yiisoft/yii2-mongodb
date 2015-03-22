MongoId specifics
=================

Remember: MongoDB document id ("_id" field) is not scalar, but an instance of [[\MongoId]] class.
To get actual Mongo ID string your should typecast [[\MongoId]] instance to string:

```php
$query = new Query;
$row = $query->from('customer')->one();
var_dump($row['_id']); // outputs: "object(MongoId)"
var_dump((string) $row['_id']); // outputs "string 'acdfgdacdhcbdafa'"
```

Although this fact is very useful sometimes, it often produces some problems.
You may face them in URL composition or attempt of saving "_id" to other storage.
In these cases, ensure you have converted [[\MongoId]] into the string:

```php
/* @var $this yii\web\View */
echo $this->createUrl(['item/update', 'id' => (string) $row['_id']]);
```

While building condition, values for the key '_id' will be automatically cast to [[\MongoId]] instance,
even if they are plain strings. So it is not necessary for you to perform back cast of string '_id'
representation:

```php
use yii\web\Controller;
use yii\mongodb\Query;

class ItemController extends Controller
{
    /**
     * @param string $id MongoId string (not object)
     */
    public function actionUpdate($id)
    {
        $query = new Query;
        $row = $query->from('item')
            where(['_id' => $id]) // implicit typecast to [[\MongoId]]
            ->one();
        ...
    }
}
```

However, if you have other columns, containing [[\MongoId]], you
should take care of possible typecast on your own.