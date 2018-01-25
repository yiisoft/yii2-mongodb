<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use MongoDB\BSON\Javascript;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;
use MongoDB\Driver\Exception\InvalidArgumentException;
use yii\base\InvalidParamException;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * QueryBuilder builds a MongoDB command statements.
 * It is used by [[Command]] for particular commands and queries composition.
 *
 * MongoDB uses JSON format to specify query conditions with quite specific syntax.
 * However [[buildCondition()]] method provides the ability of "translating" common condition format used "yii\db\*"
 * into MongoDB condition.
 * For example:
 *
 * ```php
 * $condition = [
 *     [
 *         'OR',
 *         ['AND', ['first_name' => 'John'], ['last_name' => 'Smith']],
 *         ['status' => [1, 2, 3]]
 *     ],
 * ];
 * print_r(Yii::$app->mongodb->getQueryBuilder()->buildCondition($condition));
 * // outputs :
 * [
 *     '$or' => [
 *         [
 *             'first_name' => 'John',
 *             'last_name' => 'John',
 *         ],
 *         [
 *             'status' => ['$in' => [1, 2, 3]],
 *         ]
 *     ]
 * ]
 * ```
 *
 * Note: condition values for the key '_id' will be automatically cast to [[\MongoDB\BSON\ObjectID]] instance,
 * even if they are plain strings. However, if you have other columns, containing [[\MongoDB\BSON\ObjectID]], you
 * should take care of possible typecast on your own.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class QueryBuilder extends BaseObject
{
    /**
     * @var Connection the MongoDB connection.
     */
    public $db;


    /**
     * Constructor.
     * @param Connection $connection the database connection.
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($connection, $config = [])
    {
        $this->db = $connection;
        parent::__construct($config);
    }

    // Commands :

    /**
     * Generates 'create collection' command.
     * https://docs.mongodb.com/manual/reference/method/db.createCollection/
     * @param string $collectionName collection name.
     * @param array $options collection options in format: "name" => "value"
     * @return array command document.
     */
    public function createCollection($collectionName, array $options = [])
    {
        $document = array_merge(['create' => $collectionName], $options);

        if (isset($document['indexOptionDefaults'])) {
            $document['indexOptionDefaults'] = (object) $document['indexOptionDefaults'];
        }
        if (isset($document['storageEngine'])) {
            $document['storageEngine'] = (object) $document['storageEngine'];
        }
        if (isset($document['validator'])) {
            $document['validator'] = (object) $document['validator'];
        }

        return $document;
    }

    /**
     * Generates drop database command.
     * https://docs.mongodb.com/manual/reference/method/db.dropDatabase/
     * @return array command document.
     */
    public function dropDatabase()
    {
        return ['dropDatabase' => 1];
    }

    /**
     * Generates drop collection command.
     * https://docs.mongodb.com/manual/reference/method/db.collection.drop/
     * @param string $collectionName name of the collection to be dropped.
     * @return array command document.
     */
    public function dropCollection($collectionName)
    {
        return ['drop' => $collectionName];
    }

    /**
     * Generates create indexes command.
     * @see https://docs.mongodb.com/manual/reference/method/db.collection.createIndex/
     * @param string|null $databaseName database name.
     * @param string $collectionName collection name.
     * @param array[] $indexes indexes specification. Each specification should be an array in format: optionName => value
     * The main options are:
     *
     * - keys: array, column names with sort order, to be indexed. This option is mandatory.
     * - unique: bool, whether to create unique index.
     * - name: string, the name of the index, if not set it will be generated automatically.
     * - background: bool, whether to bind index in the background.
     * - sparse: bool, whether index should reference only documents with the specified field.
     *
     * See [[https://docs.mongodb.com/manual/reference/method/db.collection.createIndex/#options-for-all-index-types]]
     * for the full list of options.
     * @return array command document.
     */
    public function createIndexes($databaseName, $collectionName, $indexes)
    {
        $normalizedIndexes = [];

        foreach ($indexes as $index) {
            if (!isset($index['key'])) {
                throw new InvalidParamException('"key" is required for index specification');
            }

            $index['key'] = $this->buildSortFields($index['key']);

            if (!isset($index['ns'])) {
                if ($databaseName === null) {
                    $databaseName = $this->db->getDefaultDatabaseName();
                }
                $index['ns'] = $databaseName . '.' . $collectionName;
            }

            if (!isset($index['name'])) {
                $index['name'] = $this->generateIndexName($index['key']);
            }

            $normalizedIndexes[] = $index;
        }

        return [
            'createIndexes' => $collectionName,
            'indexes' => $normalizedIndexes,
        ];
    }

    /**
     * Generates index name for the given column orders.
     * Columns should be normalized using [[buildSortFields()]] before being passed to this method.
     * @param array $columns columns with sort order.
     * @return string index name.
     */
    public function generateIndexName($columns)
    {
        $parts = [];
        foreach ($columns as $column => $order) {
            $parts[] = $column . '_' . $order;
        }
        return implode('_', $parts);
    }

    /**
     * Generates drop indexes command.
     * @param string $collectionName collection name
     * @param string $index index name or pattern, use `*` in order to drop all indexes.
     * @return array command document.
     */
    public function dropIndexes($collectionName, $index)
    {
        return [
            'dropIndexes' => $collectionName,
            'index' => $index,
        ];
    }

    /**
     * Generates list indexes command.
     * @param string $collectionName collection name
     * @param array $options command options.
     * Available options are:
     *
     * - maxTimeMS: int, max execution time in ms.
     *
     * @return array command document.
     */
    public function listIndexes($collectionName, $options = [])
    {
        return array_merge(['listIndexes' => $collectionName], $options);
    }

    /**
     * Generates count command
     * @param string $collectionName
     * @param array $condition
     * @param array $options
     * @return array command document.
     */
    public function count($collectionName, $condition = [], $options = [])
    {
        $document = ['count' => $collectionName];

        if (!empty($condition)) {
            $document['query'] = (object) $this->buildCondition($condition);
        }

        return array_merge($document, $options);
    }

    /**
     * Generates 'find and modify' command.
     * @param string $collectionName collection name
     * @param array $condition filter condition
     * @param array $update update criteria
     * @param array $options list of options in format: optionName => optionValue.
     * @return array command document.
     */
    public function findAndModify($collectionName, $condition = [], $update = [], $options = [])
    {
        $document = array_merge(['findAndModify' => $collectionName], $options);

        if (!empty($condition)) {
            $options['query'] = $this->buildCondition($condition);
        }

        if (!empty($update)) {
            $options['update'] = $update;
        }

        if (isset($options['fields'])) {
            $options['fields'] = $this->buildSelectFields($options['fields']);
        }

        if (isset($options['sort'])) {
            $options['sort'] = $this->buildSortFields($options['sort']);
        }

        foreach (['fields', 'query', 'sort', 'update'] as $name) {
            if (isset($options[$name])) {
                $document[$name] = (object) $options[$name];
            }
        }

        return $document;
    }

    /**
     * Generates 'distinct' command.
     * @param string $collectionName collection name.
     * @param string $fieldName target field name.
     * @param array $condition filter condition
     * @param array $options list of options in format: optionName => optionValue.
     * @return array command document.
     */
    public function distinct($collectionName, $fieldName, $condition = [], $options = [])
    {
        $document = array_merge(
            [
                'distinct' => $collectionName,
                'key' => $fieldName,
            ],
            $options
        );

        if (!empty($condition)) {
            $document['query'] = $this->buildCondition($condition);
        }

        return $document;
    }

    /**
     * Generates 'group' command.
     * @param string $collectionName
     * @@param mixed $keys fields to group by. If an array or non-code object is passed,
     * it will be the key used to group results. If instance of [[Javascript]] passed,
     * it will be treated as a function that returns the key to group by.
     * @param array $initial Initial value of the aggregation counter object.
     * @param Javascript|string $reduce function that takes two arguments (the current
     * document and the aggregation to this point) and does the aggregation.
     * Argument will be automatically cast to [[Javascript]].
     * @param array $options optional parameters to the group command. Valid options include:
     *  - condition - criteria for including a document in the aggregation.
     *  - finalize - function called once per unique key that takes the final output of the reduce function.
     * @return array command document.
     */
    public function group($collectionName, $keys, $initial, $reduce, $options = [])
    {
        if (!($reduce instanceof Javascript)) {
            $reduce = new Javascript((string) $reduce);
        }

        if (isset($options['condition'])) {
            $options['cond'] = $this->buildCondition($options['condition']);
            unset($options['condition']);
        }

        if (isset($options['finalize'])) {
            if (!($options['finalize'] instanceof Javascript)) {
                $options['finalize'] = new Javascript((string) $options['finalize']);
            }
        }

        if (isset($options['keyf'])) {
            $options['$keyf'] = $options['keyf'];
            unset($options['keyf']);
        }
        if (isset($options['$keyf'])) {
            if (!($options['$keyf'] instanceof Javascript)) {
                $options['$keyf'] = new Javascript((string) $options['$keyf']);
            }
        }

        $document = [
            'group' => array_merge(
                [
                    'ns' => $collectionName,
                    'key' => $keys,
                    'initial' => $initial,
                    '$reduce' => $reduce,
                ],
                $options
            )
        ];

        return $document;
    }

    /**
     * Generates 'map-reduce' command.
     * @see https://docs.mongodb.com/manual/core/map-reduce/
     * @param string $collectionName collection name.
     * @param \MongoDB\BSON\Javascript|string $map function, which emits map data from collection.
     * Argument will be automatically cast to [[\MongoDB\BSON\Javascript]].
     * @param \MongoDB\BSON\Javascript|string $reduce function that takes two arguments (the map key
     * and the map values) and does the aggregation.
     * Argument will be automatically cast to [[\MongoDB\BSON\Javascript]].
     * @param string|array $out output collection name. It could be a string for simple output
     * ('outputCollection'), or an array for parametrized output (['merge' => 'outputCollection']).
     * You can pass ['inline' => true] to fetch the result at once without temporary collection usage.
     * @param array $condition filter condition for including a document in the aggregation.
     * @param array $options additional optional parameters to the mapReduce command. Valid options include:
     *
     *  - sort: array, key to sort the input documents. The sort key must be in an existing index for this collection.
     *  - limit: int, the maximum number of documents to return in the collection.
     *  - finalize: \MongoDB\BSON\Javascript|string, function, which follows the reduce method and modifies the output.
     *  - scope: array, specifies global variables that are accessible in the map, reduce and finalize functions.
     *  - jsMode: bool, specifies whether to convert intermediate data into BSON format between the execution of the map and reduce functions.
     *  - verbose: bool, specifies whether to include the timing information in the result information.
     *
     * @return array command document.
     */
    public function mapReduce($collectionName, $map, $reduce, $out, $condition = [], $options = [])
    {
        if (!($map instanceof Javascript)) {
            $map = new Javascript((string) $map);
        }
        if (!($reduce instanceof Javascript)) {
            $reduce = new Javascript((string) $reduce);
        }

        $document = [
            'mapReduce' => $collectionName,
            'map' => $map,
            'reduce' => $reduce,
            'out' => $out
        ];

        if (!empty($condition)) {
            $document['query'] = $this->buildCondition($condition);
        }

        if (!empty($options)) {
            $document = array_merge($document, $options);
        }

        return $document;
    }

    /**
     * Generates 'aggregate' command.
     * @param string $collectionName collection name
     * @param array $pipelines list of pipeline operators.
     * @param array $options optional parameters.
     * @return array command document.
     */
    public function aggregate($collectionName, $pipelines, $options = [])
    {
        foreach ($pipelines as $key => $pipeline) {
            if (isset($pipeline['$match'])) {
                $pipelines[$key]['$match'] = $this->buildCondition($pipeline['$match']);
            }
        }

        $document = array_merge(
            [
                'aggregate' => $collectionName,
                'pipeline' => $pipelines,
                'allowDiskUse' => false,
            ],
            $options
        );

        return $document;
    }

    /**
     * Generates 'explain' command.
     * @param string $collectionName collection name.
     * @param array $query query options.
     * @return array command document.
     */
    public function explain($collectionName, $query)
    {
        $query = array_merge(
            ['find' => $collectionName],
            $query
        );

        if (isset($query['filter'])) {
            $query['filter'] = (object) $this->buildCondition($query['filter']);
        }
        if (isset($query['projection'])) {
            $query['projection'] = $this->buildSelectFields($query['projection']);
        }
        if (isset($query['sort'])) {
            $query['sort'] = $this->buildSortFields($query['sort']);
        }

        return [
            'explain' => $query,
        ];
    }

    /**
     * Generates 'listDatabases' command.
     * @param array $condition filter condition.
     * @param array $options command options.
     * @return array command document.
     */
    public function listDatabases($condition = [], $options = [])
    {
        $document = array_merge(['listDatabases' => 1], $options);
        if (!empty($condition)) {
            $document['filter'] = (object)$this->buildCondition($condition);
        }
        return $document;
    }

    /**
     * Generates 'listCollections' command.
     * @param array $condition filter condition.
     * @param array $options command options.
     * @return array command document.
     */
    public function listCollections($condition = [], $options = [])
    {
        $document = array_merge(['listCollections' => 1], $options);
        if (!empty($condition)) {
            $document['filter'] = (object)$this->buildCondition($condition);
        }
        return $document;
    }

    // Service :

    /**
     * Normalizes fields list for the MongoDB select composition.
     * @param array|string $fields raw fields.
     * @return array normalized select fields.
     */
    public function buildSelectFields($fields)
    {
        $selectFields = [];
        foreach ((array)$fields as $key => $value) {
            if (is_int($key)) {
                $selectFields[$value] = true;
            } else {
                $selectFields[$key] = is_scalar($value) ? (bool)$value : $value;
            }
        }
        return $selectFields;
    }

    /**
     * Normalizes fields list for the MongoDB sort composition.
     * @param array|string $fields raw fields.
     * @return array normalized sort fields.
     */
    public function buildSortFields($fields)
    {
        $sortFields = [];
        foreach ((array)$fields as $key => $value) {
            if (is_int($key)) {
                $sortFields[$value] = +1;
            } else {
                if ($value === SORT_ASC) {
                    $value = +1;
                } elseif ($value === SORT_DESC) {
                    $value = -1;
                }
                $sortFields[$key] = $value;
            }
        }
        return $sortFields;
    }

    /**
     * Converts "\yii\db\*" quick condition keyword into actual Mongo condition keyword.
     * @param string $key raw condition key.
     * @return string actual key.
     */
    protected function normalizeConditionKeyword($key)
    {
        static $map = [
            'AND' => '$and',
            'OR' => '$or',
            'IN' => '$in',
            'NOT IN' => '$nin',
        ];
        $matchKey = strtoupper($key);
        if (array_key_exists($matchKey, $map)) {
            return $map[$matchKey];
        }
        return $key;
    }

    /**
     * Converts given value into [[ObjectID]] instance.
     * If array given, each element of it will be processed.
     * @param mixed $rawId raw id(s).
     * @return array|ObjectID normalized id(s).
     */
    protected function ensureMongoId($rawId)
    {
        if (is_array($rawId)) {
            $result = [];
            foreach ($rawId as $key => $value) {
                $result[$key] = $this->ensureMongoId($value);
            }

            return $result;
        } elseif (is_object($rawId)) {
            if ($rawId instanceof ObjectID) {
                return $rawId;
            } else {
                $rawId = (string) $rawId;
            }
        }
        try {
            $mongoId = new ObjectID($rawId);
        } catch (InvalidArgumentException $e) {
            // invalid id format
            $mongoId = $rawId;
        }

        return $mongoId;
    }

    /**
     * Parses the condition specification and generates the corresponding Mongo condition.
     * @param array $condition the condition specification. Please refer to [[Query::where()]]
     * on how to specify a condition.
     * @return array the generated Mongo condition
     * @throws InvalidParamException if the condition is in bad format
     */
    public function buildCondition($condition)
    {
        static $builders = [
            'NOT' => 'buildNotCondition',
            'AND' => 'buildAndCondition',
            'OR' => 'buildOrCondition',
            'BETWEEN' => 'buildBetweenCondition',
            'NOT BETWEEN' => 'buildBetweenCondition',
            'IN' => 'buildInCondition',
            'NOT IN' => 'buildInCondition',
            'REGEX' => 'buildRegexCondition',
            'LIKE' => 'buildLikeCondition',
        ];

        if (!is_array($condition)) {
            throw new InvalidParamException('Condition should be an array.');
        } elseif (empty($condition)) {
            return [];
        }
        if (isset($condition[0])) { // operator format: operator, operand 1, operand 2, ...
            $operator = strtoupper($condition[0]);
            if (isset($builders[$operator])) {
                $method = $builders[$operator];
            } else {
                $operator = $condition[0];
                $method = 'buildSimpleCondition';
            }
            array_shift($condition);
            return $this->$method($operator, $condition);
        }
        // hash format: 'column1' => 'value1', 'column2' => 'value2', ...
        return $this->buildHashCondition($condition);
    }

    /**
     * Creates a condition based on column-value pairs.
     * @param array $condition the condition specification.
     * @return array the generated Mongo condition.
     */
    public function buildHashCondition($condition)
    {
        $result = [];
        foreach ($condition as $name => $value) {
            if (strncmp('$', $name, 1) === 0) {
                // Native Mongo condition:
                $result[$name] = $value;
            } else {
                if (is_array($value)) {
                    if (ArrayHelper::isIndexed($value)) {
                        // Quick IN condition:
                        $result = array_merge($result, $this->buildInCondition('IN', [$name, $value]));
                    } else {
                        // Mongo complex condition:
                        $result[$name] = $value;
                    }
                } else {
                    // Direct match:
                    if ($name == '_id') {
                        $value = $this->ensureMongoId($value);
                    }
                    $result[$name] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Composes `NOT` condition.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the Mongo conditions to connect.
     * @return array the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildNotCondition($operator, $operands)
    {
        if (count($operands) !== 2) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }

        list($name, $value) = $operands;

        $result = [];
        if (is_array($value)) {
            $result[$name] = ['$not' => $this->buildCondition($value)];
        } else {
            if ($name == '_id') {
                $value = $this->ensureMongoId($value);
            }
            $result[$name] = ['$ne' => $value];
        }

        return $result;
    }

    /**
     * Connects two or more conditions with the `AND` operator.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the Mongo conditions to connect.
     * @return array the generated Mongo condition.
     */
    public function buildAndCondition($operator, $operands)
    {
        $operator = $this->normalizeConditionKeyword($operator);
        $parts = [];
        foreach ($operands as $operand) {
            $parts[] = $this->buildCondition($operand);
        }

        return [$operator => $parts];
    }

    /**
     * Connects two or more conditions with the `OR` operator.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the Mongo conditions to connect.
     * @return array the generated Mongo condition.
     */
    public function buildOrCondition($operator, $operands)
    {
        $operator = $this->normalizeConditionKeyword($operator);
        $parts = [];
        foreach ($operands as $operand) {
            $parts[] = $this->buildCondition($operand);
        }

        return [$operator => $parts];
    }

    /**
     * Creates an Mongo condition, which emulates the `BETWEEN` operator.
     * @param string $operator the operator to use
     * @param array $operands the first operand is the column name. The second and third operands
     * describe the interval that column value should be in.
     * @return array the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildBetweenCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1], $operands[2])) {
            throw new InvalidParamException("Operator '$operator' requires three operands.");
        }
        list($column, $value1, $value2) = $operands;

        if (strncmp('NOT', $operator, 3) === 0) {
            return [
                $column => [
                    '$lt' => $value1,
                    '$gt' => $value2,
                ]
            ];
        }
        return [
            $column => [
                '$gte' => $value1,
                '$lte' => $value2,
            ]
        ];
    }

    /**
     * Creates an Mongo condition with the `IN` operator.
     * @param string $operator the operator to use (e.g. `IN` or `NOT IN`)
     * @param array $operands the first operand is the column name. If it is an array
     * a composite IN condition will be generated.
     * The second operand is an array of values that column value should be among.
     * @return array the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildInCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }

        list($column, $values) = $operands;

        $values = (array) $values;
        $operator = $this->normalizeConditionKeyword($operator);

        if (!is_array($column)) {
            $columns = [$column];
            $values = [$column => $values];
        } elseif (count($column) > 1) {
            return $this->buildCompositeInCondition($operator, $column, $values);
        } else {
            $columns = $column;
            $values = [$column[0] => $values];
        }

        $result = [];
        foreach ($columns as $column) {
            if ($column == '_id') {
                $inValues = $this->ensureMongoId($values[$column]);
            } else {
                $inValues = $values[$column];
            }

            $inValues = array_values($inValues);
            if (count($inValues) === 1 && $operator === '$in') {
                $result[$column] = $inValues[0];
            } else {
                $result[$column][$operator] = $inValues;
            }
        }

        return $result;
    }

    /**
     * @param string $operator MongoDB the operator to use (`$in` OR `$nin`)
     * @param array $columns list of compare columns
     * @param array $values compare values in format: columnName => [values]
     * @return array the generated Mongo condition.
     */
    private function buildCompositeInCondition($operator, $columns, $values)
    {
        $result = [];

        $inValues = [];
        foreach ($values as $columnValues) {
            foreach ($columnValues as $column => $value) {
                if ($column == '_id') {
                    $value = $this->ensureMongoId($value);
                }
                $inValues[$column][] = $value;
            }
        }

        foreach ($columns as $column) {
            $columnInValues = array_values($inValues[$column]);
            if (count($columnInValues) === 1 && $operator === '$in') {
                $result[$column] = $columnInValues[0];
            } else {
                $result[$column][$operator] = $columnInValues;
            }
        }

        return $result;
    }

    /**
     * Creates a Mongo regular expression condition.
     * @param string $operator the operator to use
     * @param array $operands the first operand is the column name.
     * The second operand is a single value that column value should be compared with.
     * @return array the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildRegexCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }
        list($column, $value) = $operands;
        if (!($value instanceof Regex)) {
            if (preg_match('~\/(.+)\/(.*)~', $value, $matches)) {
                $value = new Regex($matches[1], $matches[2]);
            } else {
                $value = new Regex($value, '');
            }
        }

        return [$column => $value];
    }

    /**
     * Creates a Mongo condition, which emulates the `LIKE` operator.
     * @param string $operator the operator to use
     * @param array $operands the first operand is the column name.
     * The second operand is a single value that column value should be compared with.
     * @return array the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildLikeCondition($operator, $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }
        list($column, $value) = $operands;
        if (!($value instanceof Regex)) {
            $value = new Regex(preg_quote($value), 'i');
        }

        return [$column => $value];
    }

    /**
     * Creates an Mongo condition like `{$operator:{field:value}}`.
     * @param string $operator the operator to use. Besides regular MongoDB operators, aliases like `>`, `<=`,
     * and so on, can be used here.
     * @param array $operands the first operand is the column name.
     * The second operand is a single value that column value should be compared with.
     * @return string the generated Mongo condition.
     * @throws InvalidParamException if wrong number of operands have been given.
     */
    public function buildSimpleCondition($operator, $operands)
    {
        if (count($operands) !== 2) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }

        list($column, $value) = $operands;

        if (strncmp('$', $operator, 1) !== 0) {
            static $operatorMap = [
                '>' => '$gt',
                '<' => '$lt',
                '>=' => '$gte',
                '<=' => '$lte',
                '!=' => '$ne',
                '<>' => '$ne',
                '=' => '$eq',
                '==' => '$eq',
            ];
            if (isset($operatorMap[$operator])) {
                $operator = $operatorMap[$operator];
            } else {
                throw new InvalidParamException("Unsupported operator '{$operator}'");
            }
        }

        return [$column => [$operator => $value]];
    }
}