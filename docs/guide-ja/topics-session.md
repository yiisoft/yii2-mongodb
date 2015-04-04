セッションコンポーネントを使用する
==================================

`Session` コンポーネントを使用するためには、[インストール](installation.md) で説明した接続の構成に加えて、`session` コンポーネントを `yii\mongodb\Session` として構成する必要があります。

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
