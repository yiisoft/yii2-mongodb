Upgrading Instructions for Yii Framework v2
===========================================

!!!IMPORTANT!!!

The following upgrading instructions are cumulative. That is,
if you want to upgrade from version A to version C and there is
version B between A and C, you need to following the instructions
for both A and B.

Upgrade from 2.1.9
----------------------

* `yii\mongodb\Session` now respects the 'session.use_strict_mode' ini directive.
  In case you use a custom `Session` class and have overwritten the `Session::openSession()` and/or 
  `Session::writeSession()` functions changes might be required:
  * When in strict mode the `openSession()` function should check if the requested session id exists
    (and mark it for forced regeneration if not).
    For example, the `yii\mongodb\Session` does this at the beginning of the function as follows:
    ```php
    if ($this->getUseStrictMode()) {
        $id = $this->getId();
        $collection = $this->db->getCollection($this->sessionCollection);
        $condition = [
            'id' => $id,
            'expire' => ['$gt' => time()],
        ];
        if (!$collection->documentExists($condition)) {
            //This session id does not exist, mark it for forced regeneration
            $this->_forceRegenerateId = $id;
        }
    }
    // ... normal function continues ...
    ``` 
  * When in strict mode the `writeSession()` function should ignore writing the session under the old id.
    For example, the `yii\mongodb\Session` does this at the beginning of the function as follows:
    ```php
    if ($this->getUseStrictMode() && $id === $this->_forceRegenerateId) {
        //Ignore write when forceRegenerate is active for this id
        return true;
    }
    // ... normal function continues ...
    ```
  > Note: In case your custom functions call their `parent` functions, there are probably no changes needed to your 
    code if those parents implement the `useStrictMode` checks.

  > Warning: in case `openSession()` and/or `writeSession()` functions do not implement the `useStrictMode` code
    the session could be stored under the forced id without warning even if `useStrictMode` is enabled.


Upgrade from 2.0.5
----------------------

* PHP [mongodb](http://php.net/manual/en/set.mongodb.php) extension is now used instead of [mongo](http://php.net/manual/en/book.mongo.php).
  Make sure you have 'mongodb' extension at your environment. Some features based on old driver may become unavailable.
  In particular: fields `Connection::mongoClient`, `Database::mongoDb` and `Collection::mongoCollection` are no longer exist.
  Old driver type classes such as `\MongoId`, `\MongoCode`, `\MongoDate` and so on, are no longer returned or
  recognized. Make sure you are using their analogs from `\MongoDB\BSON\*` namespace.

* MongoDB server versions < 3.0 are no longer supported. Make sure you are running MongoDB server >= 3.0

* The signature of the following `\yii\mongodb\Collection` methods has been changed: `aggregate()`, `distinct()`,
  `find()`, `findOne()`, `findAndModify()`. Make sure you invoke those methods correctly. In case you are
  extending `\yii\mongodb\Collection`, you should check, if overridden methods match parent declaration.

* Command and query composition methods at `\yii\mongodb\Collection`, such as `buildCondition()`, `ensureMongoId()`
  and so on, have been removed. Use `\yii\mongodb\QueryBuilder` methods instead.

* Method `Database::executeCommand()` has been removed. Use `Command` class for plain MongoDB command execution.
  You may create command with database scope using `Database::createCommand()` method.

* Method `Collection::fullTextSearch()` has been removed. Use `$text` query condition instead.

* Method `Collection::getName()` has been removed. Use `Collection::name` in order to get collection self name.

* For GridFS `yii\mongodb\file\Download` is returned instead of `\MongoGridFSFile` for the query result set.

* Cursor composed via `yii\mongodb\file\Collection::find()` now returns result in the same format as `yii\mongodb\file\Query::one()`.
  If you wish to perform file manipulations on returned row you should use `file` key instead of direct method invocations.

Upgrade from 2.0.1
----------------------

* MongoDB PHP extension min version raised up to 1.5.0. You should upgrade your environment in case you are
  using older version.

Upgrade from 2.0.0
----------------------

* MongoDB PHP extension min version raised up to 1.4.0. You should upgrade your environment in case you are
  using older version.

