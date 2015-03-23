<?php

/**
 * This is the configuration file for the 'yii2-mongodb' unit tests.
 * You can override configuration values by creating a `config.local.php` file
 * and manipulate the `$config` variable.
 */

$config = [
    'mongodb' => [
        'dsn' => 'mongodb://travis:test@localhost:27017',
        'defaultDatabaseName' => 'yii2test',
        'options' => [],
    ]
];

if (is_file(__DIR__ . '/config.local.php')) {
    include(__DIR__ . '/config.local.php');
}

return $config;