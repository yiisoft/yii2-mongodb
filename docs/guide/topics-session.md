Using the Session component
===========================

To use the `Session` component, in addition to configuring the connection as described above,
you also have to configure the `session` component to be `yii\mongodb\Session`:

```php
return [
    //....
    'components' => [
        // ...
        'session' => [
            'class' => 'yii\mongodb\Session',
        ],
    ]
];
```
