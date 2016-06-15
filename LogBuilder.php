<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use MongoDB\BSON\Binary;
use MongoDB\BSON\Javascript;
use MongoDB\BSON\MaxKey;
use MongoDB\BSON\MinKey;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;
use MongoDB\BSON\Timestamp;
use MongoDB\BSON\Type;
use MongoDB\BSON\UTCDatetime;
use yii\base\Object;
use yii\helpers\StringHelper;

/**
 * LogBuilder allows composition and escaping of the log entries.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class LogBuilder extends Object
{
    /**
     * Generate log/profile token.
     * @param string|array $namespace command namespace
     * @param array $data command data.
     * @return string token.
     */
    public function generateToken($namespace, $data = [])
    {
        if (is_array($namespace)) {
            $namespace = implode('.', $namespace);
        }
        return $namespace . '(' . $this->encodeData($data) . ')';
    }

    /**
     * Encodes complex log data into JSON format string.
     * @param mixed $data raw data.
     * @return string encoded data string.
     */
    public function encodeData($data)
    {
        return json_encode($this->processData($data));
    }

    /**
     * Pre-processes the log data before sending it to `json_encode()`.
     * @param mixed $data raw data.
     * @return mixed the processed data.
     */
    protected function processData($data)
    {
        if (is_object($data)) {
            if ($data instanceof ObjectID ||
                $data instanceof Regex ||
                $data instanceof UTCDateTime ||
                $data instanceof Timestamp
            ) {
                $data = get_class($data) . '(' . $data->__toString() . ')';
            } elseif ($data instanceof Javascript) {
                $data = print_r($data, true);
            } elseif ($data instanceof MinKey || $data instanceof MaxKey) {
                $data = StringHelper::basename(get_class($data));
            } elseif ($data instanceof Binary) {
                if (in_array($data->getType(), [Binary::TYPE_MD5, Binary::TYPE_UUID, Binary::TYPE_OLD_UUID], true)) {
                    $data = $data->getData();
                } else {
                    $data = 'Binary(...)';
                }
            } elseif ($data instanceof Type) {
                // Covers 'Binary', 'DBRef' and others
                $data = StringHelper::basename(get_class($data)) . '(...)';
            } else {
                $result = [];
                foreach ($data as $name => $value) {
                    $result[$name] = $value;
                }
                $data = $result;
            }

            if ($data === []) {
                return new \stdClass();
            }
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = $this->processData($value);
                }
            }
        }

        return $data;
    }
}