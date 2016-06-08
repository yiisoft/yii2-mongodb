Upgrading Instructions for Yii Framework v2
===========================================

!!!IMPORTANT!!!

The following upgrading instructions are cumulative. That is,
if you want to upgrade from version A to version C and there is
version B between A and C, you need to following the instructions
for both A and B.

Upgrade from Yii 2.0.5
----------------------

* PHP [mongodb](http://us1.php.net/manual/en/set.mongodb.php) extension is now used instead of [mongo](http://us1.php.net/manual/en/book.mongo.php).
  Make sure you have 'mongodb' extension at your environment. Some features based on old driver may become unavailable.
  In particular: fields `Connection::mongoClient`, `Database::mongoDb` and `Collection::mongoCollection` are no longer exist.

* MongoDB server versions < 3.0 are no longer supported. Make sure you are running MongoDB server >= 3.0

* The signature of numerous methods has been changed. In case you are extending some of the `yii\mongodb\*` classes,
  you should check, if overridden methods match parent declaration.

* Method `Database::executeCommand()` has been removed. Use `Command` class for plain MongoDB command execution.
  You may create command with database scope using `Database::createCommand()` method.

* Method `Collection::fullTextSearch()` has been removed. Use `$text` query condition instead.

* Method `Collection::getName()` has been removed. Use `Collection::name` in order to get collection self name.

Upgrade from Yii 2.0.1
----------------------

* MongoDB PHP extension min version raised up to 1.5.0. You should upgrade your environment in case you are
  using older version.

Upgrade from Yii 2.0.0
----------------------

* MongoDB PHP extension min version raised up to 1.4.0. You should upgrade your environment in case you are
  using older version.

