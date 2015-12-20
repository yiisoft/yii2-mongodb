<?php

namespace yii\mongodb\library;

use MongoDB\Driver\ReadPreference;
use MongoDB\Operation\FindAndModify;
use Prophecy\Exception\Doubler\MethodNotFoundException;

class Collection
{
    /** @var \MongoDB\Collection Mongo original collection instance */
    protected $original;

    /** @var \MongoDB\Driver\Manager Mongo driver instance */
    protected $manager;

    /** @var string Database name */
    protected $dbName;

    /** @var string Collection name */
    protected $collectionName;

    /** @var ReadPreference */
    protected $readPreference;

    public function __construct($manager, $dbName, $collectionName)
    {
        $this->dbName = $dbName;
        $this->collectionName = $collectionName;

        $this->original = new \MongoDB\Collection($manager, $dbName . '.' .$collectionName);
        $this->manager = $manager;

        $this->readPreference = $this->manager->getReadPreference();
    }

    /**
     * Performs aggregation using Mongo "group" command.
     * @param mixed $keys fields to group by. If an array or non-code object is passed,
     * it will be the key used to group results.
     * @param array $initial Initial value of the aggregation counter object.
     * @param string $reduce function that takes two arguments (the current
     * document and the aggregation to this point) and does the aggregation.
     * @param array $options optional parameters to the group command. Valid options include:
     *  - condition - criteria for including a document in the aggregation.
     *  - finalize - function called once per unique key that takes the final output of the reduce function.
     * @return array the result of the aggregation.
     */
    public function group($keys, $initial, $reduce, $options)
    {
        $operation = new Group($this->dbName, $this->collectionName, $keys, $initial, $reduce, $options);

        $readPreference = !empty($options['readPreference']) ? $options['readPreference'] : $this->readPreference;
        $server = $this->manager->selectServer($readPreference);
        return $operation->execute($server);
    }

    public function findAndModify($condition, $update, $options)
    {
        $operation = new FindAndModify(
            $this->dbName,
            $this->collectionName,
            ['query' => $condition, 'update' => $update] + $options
        );

        $readPreference = !empty($options['readPreference']) ? $options['readPreference'] : $this->readPreference;

        $server = $this->manager->selectServer($readPreference);
        return $operation->execute($server);
    }

    /**
     * Redirect method call to original Collection instance
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        try {
            return call_user_func_array([$this->original, $name], $arguments);
        } catch (\Exception $e) {
            throw new MethodNotFoundException($e->getMessage(), '\MongoDB\Collection', $name, $arguments);
        }
    }
}