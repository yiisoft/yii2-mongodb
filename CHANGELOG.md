Yii Framework 2 mongodb extension Change Log
============================================

2.0.5 under development
-----------------------

- Enh #23: Added support for complex sort specification at `yii\mongodb\Query` (raoptimus)
- Enh #27: Added support for saving extra fields in session collection for `yii\mongodb\Session` (klimov-paul)
- Enh #35: Added support for cursor options setup at `yii\mongodb\Query` (klimov-paul)
- Enh #36: Added support for compare operators (like '>', '<' and so on) at `yii\mongodb\Query` (klimov-paul)
- Enh #37: Now `yii\mongodb\Collection::buildInCondition` is not added '$in' for array contains one element (webdevsega)


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
