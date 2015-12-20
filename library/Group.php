<?php

namespace yii\mongodb\library;


use MongoDB\Driver\Command;
use MongoDB\Driver\Server;
use MongoDB\Exception\InvalidArgumentException;
use MongoDB\Exception\InvalidArgumentTypeException;

use MongoDB\Operation\Executable;

/**
 * Operation for grouping documents with the group command.
 *
 * @api
 * @see MongoDB\Collection::group()
 * @see http://docs.mongodb.org/manual/reference/command/group/
 */
class Group implements Executable
{
    private $databaseName;
    private $collectionName;

    private $keys;
    private $initial;
    private $reduce;

    private $options;

    /**
     * Constructs an insert command.
     *
     * Supported options:
     *
     *  * writeConcern (MongoDB\Driver\WriteConcern): Write concern.
     *
     * @param string       $databaseName   Database name
     * @param string       $collectionName Collection name
     * @param array|object $keys The keys to group the documents by
     * @param array|object $initial The group prototype
     * @param string $reduce Reduce function
     * @param array        $options        Command options
     * @throws InvalidArgumentException
     */
    public function __construct($databaseName, $collectionName, $keys, $initial = [], $reduce = 'function() {}', array $options = [])
    {
        if ( !is_array($keys) && ! is_object($keys)) {
            throw new InvalidArgumentTypeException('$keys', $keys, 'array or object');
        }

        if ( !is_array($initial) && ! is_object($initial)) {
            throw new InvalidArgumentTypeException('$initial', $initial, 'array or object');
        }

        if ( !is_string($reduce) ) {
            throw new InvalidArgumentTypeException('$reduce', $reduce, 'string');
        }

        $this->databaseName = (string) $databaseName;
        $this->collectionName = (string) $collectionName;
        $this->keys = $keys;
        $this->initial = $initial;
        $this->reduce = $reduce;
        $this->options = $options;
    }

    /**
     * Execute the operation.
     *
     * @see Executable::execute()
     * @param Server $server
     * @return object
     */
    public function execute(Server $server)
    {
        $command = new Command([
            'group' => [
                'ns' => $this->collectionName,
                'key' => $this->keys,
                'initial' => $this->initial,
                '$reduce' => $this->reduce
            ]
        ]);

        $cursor = $server->executeCommand($this->databaseName, $command);

        // Get first element of iterator
        foreach($cursor as $result) {
            break;
        };

        return isset($result) ? $result : null;
    }
}