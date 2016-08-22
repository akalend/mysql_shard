<?php namespace MysqlShard\Adapters;

use MysqlShard\Tools\Timer;

/**
 * Class Redis
 *
 * @package MysqlShard\Db
 */
class Redis extends \Redis
{
    /** @var string  */
    public static $callClass = '';

    /** @var \MysqlShard\Services\Profiler\Collectors\Redis */
    protected $_profiler;

    /**
     *
     */
    public function __construct()
    {
        parent::__construct();

        $profiler = \PhpBase\Registry::getInstance()->profiler;
        if ($profiler && isset($profiler['Redis'])) {
            $this->_profiler = $profiler['Redis'];
        }
    }

    /**
     * @param $class
     *
     * @return $this
     */
    public function setCallClass($class)
    {
        static::$callClass = $class;
        return $this;
    }

    /**
     * Connects to a Redis instance.
     *
     * @param string    $host       can be a host, or the path to a unix domain socket
     * @param int       $port       optional
     * @param float     $timeout    value in seconds (optional, default is 0.0 meaning unlimited)
     * @return bool                 TRUE on success, FALSE on error.
     * <pre>
     * $redis->connect('127.0.0.1', 6379);
     * $redis->connect('127.0.0.1');            // port 6379 by default
     * $redis->connect('127.0.0.1', 6379, 2.5); // 2.5 sec timeout.
     * $redis->connect('/tmp/redis.sock');      // unix domain socket.
     * </pre>
     */
    public function connect($host, $port = 6379, $timeout = 0.0)
    {
         return $this->_call('connect', func_get_args());
    }

    /**
     * Switches to a given database.
     *
     * @param   int $dbindex
     *
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/select
     * @example
     * <pre>
     * $redis->select(0);       // switch to DB 0
     * $redis->set('x', '42');  // write 42 to x
     * $redis->move('x', 1);    // move to DB 1
     * $redis->select(1);       // switch to DB 1
     * $redis->get('x');        // will return 42
     * </pre>
     */
    public function select($dbindex)
    {
        return $this->_call('select', func_get_args());
    }


    /**
     * Set the string value in argument as value of the key.
     *
     * @param   string  $key
     * @param   string  $value
     * @param   int   $timeout [optional] Calling setex() is preferred if you want a timeout.
     * @return  bool:   TRUE if the command is successful.
     * @link    http://redis.io/commands/set
     * @example $redis->set('key', 'value');
     */
    public function set($key, $value, $timeout = 0)
    {
        return $this->_call('set', func_get_args());
    }

    /**
     * Set the string value in argument as value of the key, with a time to live.
     *
     * @param   string  $key
     * @param   int     $ttl
     * @param   string  $value
     * @return  bool:   TRUE if the command is successful.
     * @link    http://redis.io/commands/setex
     * @example $redis->setex('key', 3600, 'value'); // sets key → value, with 1h TTL.
     */
    public function setex( $key, $ttl, $value )
    {
        return $this->_call('setex', func_get_args());
    }

    /**
     * Get the value related to the specified key
     *
     * @param   string  $key
     * @return  string|bool: If key didn't exist, FALSE is returned. Otherwise, the value related to this key is returned.
     * @link    http://redis.io/commands/get
     * @example $redis->get('key');
     */
    public function get($key)
    {
        return $this->_call('get', func_get_args());
    }

    /**
     * Increment the number stored at key by one.
     *
     * @param   string $key
     * @return  int    the new value
     * @link    http://redis.io/commands/incr
     * @example
     * <pre>
     * $redis->incr('key1'); // key1 didn't exists, set to 0 before the increment and now has the value 1
     * $redis->incr('key1'); // 2
     * $redis->incr('key1'); // 3
     * $redis->incr('key1'); // 4
     * </pre>
     */
    public function incr($key)
    {
        return $this->_call('incr', func_get_args());
    }

    /**
     * Returns the values of all specified keys.
     *
     * For every key that does not hold a string value or does not exist,
     * the special value false is returned. Because of this, the operation never fails.
     *
     * @param array $array
     * @return array
     * @link http://redis.io/commands/mget
     * @example
     * <pre>
     * $redis->delete('x', 'y', 'z', 'h');	// remove x y z
     * $redis->mset(array('x' => 'a', 'y' => 'b', 'z' => 'c'));
     * $redis->hset('h', 'field', 'value');
     * var_dump($redis->mget(array('x', 'y', 'z', 'h')));
     * // Output:
     * // array(3) {
     * // [0]=>
     * // string(1) "a"
     * // [1]=>
     * // string(1) "b"
     * // [2]=>
     * // string(1) "c"
     * // [3]=>
     * // bool(false)
     * // }
     * </pre>
     */
    public function mget(array $array)
    {
        return $this->_call('mget', func_get_args());
    }

    /**
     * @see del()
     * @param $key1
     * @param null $key2
     * @param null $key3
     */
    public function delete($key1, $key2 = null, $key3 = null)
    {
        return $this->_call('delete', func_get_args());
    }

    /**
     * @param $method
     * @param $arguments
     *
     * @return mixed
     */
    public function _call($method, $arguments)
    {
        if ( $this->_profiler ) {
            $_timer = new Timer();
            $_timer->start();
        }

        switch ( sizeof($arguments) ) {
            case 0:
                $return = parent::$method();
                break;
            case 1:
                $return = parent::$method($arguments[0]);
                break;
            case 2:
                $return = parent::$method($arguments[0], $arguments[1]);
                break;
            case 3:
                $return = parent::$method($arguments[0], $arguments[1], $arguments[2]);
                break;
            case 4:
                $return = parent::$method($arguments[0], $arguments[1], $arguments[2], $arguments[3]);
                break;
            default;
                $return = call_user_func_array(array($this, "parent::{$method}"), $arguments);
                break;
        }

        if ( $this->_profiler ) {
            $_timer->stop();
            if ( in_array($method, ['connect']) ) {
                $arguments = [];
            }
            $this->_profiler->addMessage($_timer, $method, $arguments, static::$callClass, $return);
        }

        static::$callClass = '';

        return $return;
    }
}