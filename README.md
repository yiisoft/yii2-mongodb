<p align="center">
    <a href="https://www.mongodb.com/" target="_blank" rel="external">
        <img src="https://webassets.mongodb.com/_com_assets/cms/mongodb-logo-rgb-j6w271g1xn.jpg" height="80px">
    </a>
    <h1 align="center">MongoDB Extension for Yii 2</h1>
    <br>
</p>

This extension provides the [MongoDB](https://www.mongodb.com/) integration for the [Yii framework 2.0](https://www.yiiframework.com).

For license information check the [LICENSE](LICENSE.md)-file.

Documentation is at [docs/guide/README.md](docs/guide/README.md).

[![Latest Stable Version](https://poser.pugx.org/yiisoft/yii2-mongodb/v/stable.png)](https://packagist.org/packages/yiisoft/yii2-mongodb)
[![Total Downloads](https://poser.pugx.org/yiisoft/yii2-mongodb/downloads.png)](https://packagist.org/packages/yiisoft/yii2-mongodb)
[![Build Status](https://github.com/yiisoft/yii2-mongodb/workflows/build/badge.svg)](https://github.com/yiisoft/yii2-mongodb/actions)
[![codecov](https://codecov.io/gh/yiisoft/yii2-mongodb/graph/badge.svg?token=1Xo867R6He)](https://codecov.io/gh/yiisoft/yii2-mongodb)

Requirements
------------

- PHP 7.3 or higher.

Installation
------------

This extension requires [MongoDB PHP Extension](https://www.php.net/manual/en/set.mongodb.php) version 1.20.1 or higher.

This extension requires MongoDB server version 4.0 or higher.

The preferred way to install this extension is through [composer](https://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yiisoft/yii2-mongodb
```

or add

```
"yiisoft/yii2-mongodb": "~3.0.0"
```

to the `require` section of your `composer.json`.

Configuration
-------------

To use this extension, simply add the following code in your application configuration:

```php
return [
    //....
    'components' => [
        'mongodb' => [
            'class' => '\yii\mongodb\Connection',
            'dsn' => 'mongodb://localhost:27017/mydatabase',
            'options' => [
                "username" => "Username",
                "password" => "Password"
            ]
        ],
    ],
];
```

Known issues
------------
<ul>
<li>yii\mongodb\Exception: no such command: 'group' with MongoDB server version 4.2 or higher.<br/>
Starting in version 4.2, MongoDB removes the group command (deprecated since version 3.4) and its mongo shell helper db.collection.group().</li>
</ul>