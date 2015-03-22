Using the Cache component
=========================

To use the `Cache` component, in addition to configuring the connection as described above,
you also have to configure the `cache` component to be `yii\mongodb\Cache`:

```php
return [
    //....
    'components' => [
        // ...
        'cache' => [
            'class' => 'yii\mongodb\Cache',
        ],
    ]
];
```
