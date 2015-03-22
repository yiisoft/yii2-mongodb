Using the MongoDB DebugPanel
============================

The yii2 MongoDB extensions provides a debug panel that can be integrated with the yii debug module
and shows the executed MongoDB queries.

Add the following to you application config to enable it (if you already have the debug module
enabled, it is sufficient to just add the panels configuration):

```php
    // ...
    'bootstrap' => ['debug'],
    'modules' => [
        'debug' => [
            'class' => 'yii\\debug\\Module',
            'panels' => [
                'mongodb' => [
                    'class' => 'yii\\mongodb\\debug\\MongoDbPanel',
                ],
            ],
        ],
    ],
    // ...
```
