MongoDb Extension for Yii 2
===========================

This extension provides the [MongoDB](http://www.mongodb.org/) integration for the [Yii framework 2.0](http://www.yiiframework.com).

For license information check the [LICENSE](LICENSE.md)-file.

Documentation is at [docs/guide/README.md](docs/guide/README.md).

[![Latest Stable Version](https://poser.pugx.org/yiisoft/yii2-mongodb/v/stable.png)](https://packagist.org/packages/yiisoft/yii2-mongodb)
[![Total Downloads](https://poser.pugx.org/yiisoft/yii2-mongodb/downloads.png)](https://packagist.org/packages/yiisoft/yii2-mongodb)
[![Build Status](https://travis-ci.org/yiisoft/yii2-mongodb.svg?branch=master)](https://travis-ci.org/yiisoft/yii2-mongodb)


Installation
------------

This extension requires [MongoDB PHP Extension](http://us1.php.net/manual/en/set.mongodb.php) version 1.0.0 or higher.

This extension requires MongoDB server version 3.0 or higher.

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yiisoft/yii2-mongodb
```

or add

```
"yiisoft/yii2-mongodb": "~2.1.0"
```

to the require section of your composer.json.

Configuration
-------------

To use this extension, simply add the following code in your application configuration:

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
