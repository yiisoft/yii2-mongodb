Использование MongoDB ActiveRecord
==============================

Расширение предоставляет паттерн ActiveRecord аналогично [[\yii\db\ActiveRecord]].
Чтобы объявить класс ActiveRecord вам необходимо расширить [[\yii\mongodb\ActiveRecord]] и реализовать методы `collectionName` и `attributes`:

```php
use yii\mongodb\ActiveRecord;

class Customer extends ActiveRecord
{
    /**
     * @return string the name of the index associated with this ActiveRecord class.
     */
    public static function collectionName()
    {
        return 'customer';
    }

    /**
     * @return array list of attribute names.
     */
    public function attributes()
    {
        return ['_id', 'name', 'email', 'address', 'status'];
    }
}
```

> Note: первичный ключ названия коллекции (`_id`) должен быть всегда установлен в явном виде в качестве атрибута.

Вы можете использовать [[\yii\data\ActiveDataProvider]] с [[\yii\mongodb\Query]] и [[\yii\mongodb\ActiveQuery]]:

```php
use yii\data\ActiveDataProvider;
use yii\mongodb\Query;

$query = new Query();
$query->from('customer')->where(['status' => 2]);
$provider = new ActiveDataProvider([
    'query' => $query,
    'pagination' => [
        'pageSize' => 10,
    ]
]);
$models = $provider->getModels();
```

```php
use yii\data\ActiveDataProvider;
use app\models\Customer;

$provider = new ActiveDataProvider([
    'query' => Customer::find(),
    'pagination' => [
        'pageSize' => 10,
    ]
]);
$models = $provider->getModels();
```
