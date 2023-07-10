インストール
============

## 必要条件

このエクステンションは [MongoDB PHP 拡張](https://www.php.net/manual/en/set.mongodb.php) バージョン 1.0.0 以降を必要とします。

## Composer パッケージを取得する

このエクステンションをインストールするのに推奨される方法は [composer](https://getcomposer.org/download/) によるものです。

下記のコマンドを実行してください。

```
php composer.phar require --prefer-dist yiisoft/yii2-mongodb
```

または、あなたの `composer.json` ファイルの `require` セクションに、

```
"yiisoft/yii2-mongodb": "~2.1.0"
```

を追加してください。

## アプリケーションを構成する

このエクステンションを使用するために必要なことは、下記のコードをあなたのアプリケーション構成情報に追加することだけです。

```php
return [
    //....
    'components' => [
        'mongodb' => [
            'class' => '\yii\mongodb\Connection',
            'dsn' => 'mongodb://developer:password@localhost:27017/mydatabase',
        ],
    ],
];
```
