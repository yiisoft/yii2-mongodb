Logging and Profiling
=====================

This extension provides logging for executed commands or queries.
Logging is optional and can be enabled or disabled at [[\yii\mongodb\Connection]] level:

```php
return [
    //....
    'components' => [
        'mongodb' => [
            'class' => '\yii\mongodb\Connection',
            'dsn' => 'mongodb://developer:password@localhost:27017/mydatabase',
            'enableLogging' => true, // enable logging
            'enableProfiling' => true, // enable profiling
        ],
    ],
];
```

> Note: log messages generated for the commands and queries do not contain actual
  text of the performed queries, they contains only a **close approximation** of it,
  composed on the values which can be extracted from PHP MongoDB extension classes.
  If you need to see actual query text, you should use specific tools for that.

> Tip: Keep in mind that composition of the log messages take some time and program resources.
  Thus it make sense to disable logging at the production environment.
