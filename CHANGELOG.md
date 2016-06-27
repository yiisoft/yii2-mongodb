Yii Framework 2 mongodb extension Change Log
============================================

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
