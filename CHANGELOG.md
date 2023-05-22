Yii Framework 2 mongodb extension Change Log
============================================

3.0.1 May 22, 2023
------------------

- Bug #356 Fixed error "implicit conversion from float" in debug panel (squio)


3.0.0 September 04, 2022
------------------------

- Bug #297: Fixed zero-index key problem in `ActiveQuery::Each()` (ziaratban)
- Bug #299: Drop limit of `batchSize` cursor option in application level according to [jira.mongodb(PHP-457)](https://jira.mongodb.org/browse/PHP-457) (ziaratban)
- Bug #348: Add PHP 8.1 support (scrummitch, samdark)
- Enh #294: Add transactions support (ziaratban)


2.1.12 August 09, 2021
----------------------

- Enh #342: Use `random_int()` for cache garbage collection (samdark)


2.1.11 December 23, 2020
------------------------

- Bug #327: Fix `yii\mongodb\BatchQueryResult` to be compatible with PECL MongoDb 1.9.0 driver (bizley)


2.1.10 November 10, 2020
------------------------

- Bug #308: Fix `yii\mongodb\file\Upload::addFile()` error when uploading file with readonly permissions (sparchatus)
- Enh #319: Added support for the 'session.use_strict_mode' ini directive in `yii\web\Session` (rhertogh)



2.1.9 November 19, 2019
-----------------------

- Bug #286: Fix `Collection::dropAllIndexes()` error when no indexes were dropped (samdark)


2.1.8 October 08, 2019
----------------------

- Bug #285: Fix `sessionWrite` callback fields (related to https://github.com/yiisoft/yii2/issues/17559 and https://github.com/yiisoft/yii2/pull/17188) (lubosdz)


2.1.7 March 30, 2018
--------------------

- Bug #251: Fixed `yii\mongodb\ActiveQuery::indexBy()` does not apply while using Yii 2.0.14 (klimov-paul)
- Enh: `yii\mongodb\Session` now relies on error handler to display errors (samdark)


2.1.6 February 13, 2018
-----------------------

- Bug #241: Fixed `yii\mongodb\Command::aggregate()` without 'cursor' option produces error on MongoDB Server 3.6 (Lisio, klimov-paul)
- Bug #247: Fixed `yii\mongodb\Collection::dropIndex()` unable to drop index specified with sort via index plugin (klimov-paul)


2.1.5 November 03, 2017
-----------------------

- Bug #223: Usage of deprecated `yii\base\Object` changed to `yii\base\BaseObject` allowing compatibility with PHP 7.2 (klimov-paul)
- Bug #227: Fixed `yii\mongodb\file\Collection::remove()` does not removes all file chunks in case `limit` is specified (klimov-paul)
- Bug #228: Fixed `yii\mongodb\Command::aggregate()` does not support 'cursor' option (klimov-paul)
- Enh #224: Provided support for 'migrate/fresh' command to truncate database and apply migrations again (klimov-paul)
- Enh #225: Added `yii\mongodb\Migration::$compact` supporting `yii\console\controllers\BaseMigrateController::$compact` option (klimov-paul)
- Chg #158: Data structure for `yii\mongodb\i18n\MongoDbMessageSource` changed avoiding usage message key as BSON key (klimov-paul)


2.1.4 June 23, 2017
-------------------

- Bug #187: Fixed exception is thrown on `yii\mongodb\rbac\MongoDbManager::invalidateCache()` invocation (jafaripur)
- Bug #201: Fixed selection of master/slave server for read/write operations at `yii\mongodb\Command` (KhristenkoYura)
- Bug #205: Fixed negative value passed to `yii\mongodb\Query::limit()` or `yii\mongodb\Query::offset()` does not disables query limit or offset correspondingly (klimov-paul)
- Bug #207: Fixed `yii\mongodb\validators\MongoDateValidator` corrupts date value, while validating existing `MongoDB\BSON\UTCDateTime` instance (klimov-paul)
- Bug #210: Fixed `yii\mongodb\debug\MongoDbPanel` overrides explain action of `yii\debug\panels\DbPanel` (Liv1020, klimov-paul)
- Bug #213: Made `MigrateController` compatible with Yii 2.0.12 (cebe)


2.1.3 February 15, 2017
-----------------------

- Bug #168: Fixed `yii\mongodb\Command::update()` uses `upsert` option by default (klimov-paul)
- Bug #170: Fixed incorrect order of migrations history in case `yii\mongodb\console\controllers\MigrateController::$migrationNamespaces` is in use (klimov-paul)
- Bug #173: Fixed `yii\mongodb\ActiveQuery` does not respects relational link at methods `count()`, `distinct()`, `sum()`, `average()`, `modify()` (tuyakhov, klimov-paul)
- Bug #176: Fixed `yii\mongodb\validators\MongoDateValidator` uses seconds instead of milliseconds while creating `MongoDB\BSON\UTCDateTime` instance (reza-id, klimov-paul)
- Bug #179: Fixed `yii\mongodb\file\Upload` unable to handle custom `_id` value, if it does not provided as `\MongoDB\BSON\ObjectID` instance (klimov-paul)
- Bug #186: Fixed `yii\mongodb\rbac\MongoDbManager::getRolesByUser()` results now includes default roles (klimov-paul)
- Enh #171: Added support for `yii\db\QueryInterface::emulateExecution()` to force returning an empty result for a query (klimov-paul)
- Enh #177: Method `yii\mongodb\ActiveQuery::exists()` optimized avoiding redundant ActiveRecord and relations population (klimov-paul)


2.1.2 October 31, 2016
----------------------

- Bug #150: Fixed `yii\mongodb\Query::exists()` always returning true (klimov-paul)
- Bug #155: Fixed `yii\mongodb\Query` unable to process `not` condition with `null` compare value (klimov-paul)
- Enh #152: Added support for namespaced migrations via [[yii\mongodb\console\controllers\MigrateController::migrationNamespaces]] (klimov-paul)
- Enh #153: Added `yii\mongodb\rbac\MongoDbManager::getChildRoles()` method allowing finding child roles for the given one (githubjeka, klimov-paul)
- Enh #154: Methods `scalar()` and `column()` added to `yii\mongodb\Query` (klimov-paul)


2.1.1 August 29, 2016
---------------------

- Bug #136: Fixed `yii\mongodb\Collection::findOne()` returns `false` instead of `null` on empty result (klimov-paul)
- Bug #142: Fixed `yii\mongodb\Migration::createIndexes()` triggers E_NOTICE (klimov-paul)
- Bug #145: Fixed `yii\mongodb\ActiveFixture` fails to find default data file if `collectionName` is specified in array format (klimov-paul)
- Bug #146: Fixed `yii\mongodb\ActiveRecord` and `yii\mongodb\file\ActiveRecord` looses `_id` custom value on insertion (lxyfirst, klimov-paul)
- Enh #147: Added unknown methods `stream_seek` and `stream_tell` to `yii\mongodb\file\StreamWrapper` for `fseek()` and `ftell()` (AstRonin)
- Enh: Added `yii\mongodb\Migration::listCollections()` method (klimov-paul)


2.1.0 June 27, 2016
-------------------

- Enh #33: Added support for batch (bulk) write operations (klimov-paul)
- Enh #56: Now 'mongodb' PHP extension used instead of 'mongo' (klimov-paul, hardsetting, Sammaye)
- Enh #76: Added ability to disable logging and/or profiling for the commands and queries (klimov-paul)
- Enh #77: Added support for fetching data from MongoDB in batches (klimov-paul)
- Enh #79: `yii\mongodb\ActiveRecord::toArray()` provides better representation for BSON objects in recursive mode (klimov-paul, rowdyroad)


2.0.5 May 9, 2016
-----------------

- Bug #40: Fixed `yii\mongodb\ActiveFixture` throws exception on empty fixture data (darkunz)
- Bug #73: Fixed `yii\mongodb\Collection::buildInCondition()` unable to process composite 'IN' condition (klimov-paul)
- Bug #75: Fixed `yii\mongodb\Collection::distinct()` always returns `false` on empty condition for MongoDB 2.8 (boxoft)
- Bug #101: Fixed `yii\mongodb\Collection::buildCondition()` does not compose 'IN' condition for the values with broken index sequence (klimov-paul)
- Bug: Avoid serializing PHP 7 errors (zuozp8, cebe)
- Enh #23: Added support for complex sort specification at `yii\mongodb\Query` (raoptimus)
- Enh #24: `yii\mongodb\Query` now contains a `andFilterCompare()` method that allows filtering using operators in the query value (lennartvdd)
- Enh #27: Added support for saving extra fields in session collection for `yii\mongodb\Session` (klimov-paul)
- Enh #35: Added support for cursor options setup at `yii\mongodb\Query` (klimov-paul)
- Enh #36: Added support for compare operators (like '>', '<' and so on) at `yii\mongodb\Query` (klimov-paul)
- Enh #37: Now `yii\mongodb\Collection::buildInCondition` is not added '$in' for array contains one element (webdevsega)
- Enh #41: Added `yii\mongodb\Connection::driverOptions` allowing setup of the options for the MongoDB driver (klimov-paul)
- Enh #57: Added i18n support via `yii\mongodb\i18n\MongoDbMessageSource` (klimov-paul)
- Enh #69: Fixed log target to display exceptions like DbTarget in Yii core, also avoids problems with Exceptions that contain closures (cebe)
- Enh #74: Added explain method to `MongoDbPanel` debug panel (webdevsega)
- Enh #87: Added RBAC support via `yii\mongodb\rbac\MongoDbManager` (klimov-paul)
- Enh #102: `MongoDbTarget` now uses `batchInsert()` while exporting log messages (klimov-paul)


2.0.4 May 10, 2015
------------------

- Bug #7010: Fixed `yii\mongodb\Query::one()` fails on PHP MongoDB extension version 1.6.x (im-kulikov, klimov-paul)
- Enh #5802: Added `yii\mongodb\validators\MongoIdValidator` and `yii\mongodb\validators\MongoDateValidator` validators (klimov-paul)
- Enh #7798: Added support for 'NOT' conditions at `yii\mongodb\Collection` (klimov-paul)
- Chg #7924: Migrations in history are now ordered by time applied allowing to roll back in reverse order no matter how these were applied (klimov-paul)


2.0.3 March 01, 2015
--------------------

- Bug #7010: Fixed `yii\mongodb\Query::select` now allows excluding fields (Sammaye, klimov-paul)


2.0.2 January 11, 2015
----------------------

- Bug #6376: Fixed lazy load of relations to `yii\mongodb\file\ActiveRecord` (klimov-paul)


2.0.1 December 07, 2014
-----------------------

- Bug #6026: Fixed `yii\mongodb\ActiveRecord` saves `null` as `_id`, if attributes are empty (klimov-paul)
- Enh #3855: Added debug toolbar panel for MongoDB (klimov-paul)
- Enh #5592: Added support for 'findAndModify' operation at `yii\mongodb\Query` and `yii\mongodb\ActiveQuery` (klimov-paul)


2.0.0 October 12, 2014
----------------------

- Bug #5303: Fixed `yii\mongodb\Collection` unable to fetch default database name from DSN with parameters (klimov-paul)
- Bug #5411: Fixed `yii\mongodb\ActiveRecord` unable to fetch 'hasMany' referred by array of `\MongoId` (klimov-paul)


2.0.0-rc September 27, 2014
---------------------------

- Bug #2337: `yii\mongodb\Collection::buildLikeCondition()` fixed to escape regular expression (klimov-paul)
- Bug #3385: Fixed "The 'connected' property is deprecated" (samdark)
- Bug #4879: Fixed `yii\mongodb\Collection::buildInCondition()` handles non-sequent key arrays (klimov-paul)
- Enh #3520: Added `unlinkAll()`-method to active record to remove all records of a model relation (NmDimas, samdark, cebe)
- Enh #3778: Gii generator for Active Record model added (klimov-paul)
- Enh #3947: Migration support added (klimov-paul)
- Enh #4048: Added `init` event to `ActiveQuery` classes (qiangxue)
- Enh #4086: changedAttributes of afterSave Event now contain old values (dizews)
- Enh #4335: `yii\mongodb\log\MongoDbTarget` log target added (klimov-paul)


2.0.0-beta April 13, 2014
-------------------------

- Initial release.
