Installation
============

## Requirements

l'extention  [MongoDB PHP Extension](http://us1.php.net/manual/en/set.mongodb.php) version 1.0.0 ou plus.

## Installation du composer:

Il est preferable d'installer composer depuis :  [composer](http://getcomposer.org/download/).

et puis executer la commande: 

```
php composer.phar require --prefer-dist yiisoft/yii2-mongodb
```

ou bien ajouter : 

```
"yiisoft/yii2-mongodb": "~2.1.0"
```

a la section `require` de votre `composer.json`.

## Configuration de votre application

Pour l'utilisation de cette extention, il suffit d'ajouter le code suivant a configuration de votre application:

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
