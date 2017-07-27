Особенности MongoId
=================

## Получение скаляра из ID документа

> Remember: идентификатор документа MongoDB (поле `_id`) не является скаляром, но представляет собой экземпляр класса [[\MongoDB\BSON\ObjectID]].
Чтобы получить фактическую строку Mongo ID, вы должны привести тип экземпляра [[\MongoDB\BSON\ObjectID]] к строке:

```php
$query = new Query();
$row = $query->from('customer')->one();
var_dump($row['_id']); // вывод: "object(\MongoDB\BSON\ObjectID)"
var_dump((string) $row['_id']); // вывод: "string 'acdfgdacdhcbdafa'"
```
Хотя это обстоятельство, иногда, весьма полезно, но часто приводит к некоторым проблемам.
Вы можете столкнуться с ними составляя URL или при попытке сохранения `_id` в другое хранилище.
В этих случаях убедитесь, что вы конвертировали [[\MongoDB\BSON\ObjectID]] в строку:

```php
/* @var $this yii\web\View */
echo $this->createUrl(['item/update', 'id' => (string) $row['_id']]);
```

То же самое относится и к реализации идентификационных данных пользователя, которые хранятся в MongoDB. При реализации
[[\yii\web\IdentityInterface::getId()]] вы должны привести [[\MongoDB\BSON\ObjectID]] класс к скаляру чтобы аутентификация начала работать.

## Получение ID документа из скаляра

При создании условии, значения для ключа `_id` будет автоматически приведено к [[\MongoDB\BSON\ObjectID]]
например, даже если они простые строки. Так что нет необходимости выполнять обратное приведение `_id` к строке:

```php
use yii\web\Controller;
use yii\mongodb\Query;

class ItemController extends Controller
{
    /**
     * @param string $id MongoId строка (не является объектом)
     */
    public function actionUpdate($id)
    {
        $query = new Query;
        $row = $query->from('item')
            where(['_id' => $id]) // неявное приведение типа [[\MongoDB\BSON\ObjectID]]
            ->one();
        ...
    }
}
```

Тем не менее, если у вас есть другие столбцы, содержащие [[\MongoDB\BSON\ObjectID]], вы должны позаботиться о возможном приведении типов на свое усмотрение.
