<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb\file;

use yii\base\Object;

/**
 * StreamWrapper
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class StreamWrapper extends Object
{
    const PROTOCOL = 'gridfs';

    /**
     * @var resource associated stream resource context
     */
    public $context;


    /**
     * Registers this steam wrapper.
     */
    public static function register()
    {
        if (in_array(self::PROTOCOL, stream_get_wrappers())) {
            stream_wrapper_unregister(self::PROTOCOL);
        }

        stream_wrapper_register(self::PROTOCOL, get_called_class(), STREAM_IS_URL);
    }

    public function stream_close()
    {
        ;
    }

    public function stream_eof()
    {
        return true;
    }

    public function stream_open($path, $mode, $options, &$openedPath)
    {
        ;
    }

    public function stream_read($count)
    {
        ;
    }

    public function stream_write($data)
    {
        ;
    }
}