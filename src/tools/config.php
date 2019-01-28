<?php namespace MysqlShard\Tools;
/**
 * Created by PhpStorm.
 * User: akalend
 * Date: 16.06.16
 * Time: 23:47
 */


use MysqlShard\Tools\Service;
use MysqlShard\Tools\Config;



/**
 * Load configuration, Use redis cache
 *
 * Class Config
 * @package MysqlShard\tools
 *
 */
class Config
{

	const KEY_PREFIX = 'CONF_';

	static $_conf         = null;
    static $_redis_conf   = null;
    static $_parse_conf   = null;
    static $_pool         = null;
    static $_shard_count  = null;
    static $_shard2cnn    = null;

    public static function get($name,  $noCache = false )
    {
        
        if ($name == 'redis') {

        if (static::$_redis_conf) return static::$_redis_conf;

        if (!is_dir(MYSQLSHARD_CONF)) {
            throw new \Exception('The configs dir is error');
        }

            static::$_redis_conf = require( MYSQLSHARD_CONF .'/'. $name . '.php');
            return static::$_redis_conf;
        }


        if (static::$_conf)  {
            return static::$_conf;
        }


        $redis = Service::redis();


        static::$_conf = unserialize($redis->get(static::KEY_PREFIX . $name));

        if (static::$_conf)  {
            return  static::$_conf;
        }


        if (!is_dir(MYSQLSHARD_CONF)) {
            throw new \Exception('The configs dir is error');
        }

        static::$_conf = require( MYSQLSHARD_CONF .'/'. $name . '.php');
        $redis->set(static::KEY_PREFIX . $name,  serialize(static::$_conf) );
        return static::$_conf;
    }

    public static function getParse(){

        if (static::$_parse_conf) return static::$_parse_conf;

        if (!static::$_conf) {
            throw new \Exception('The configs is not set');
        }

        foreach ( static::$_conf['instance'] as $key => $inst ) {

            static::$_pool[$key] = false;

            foreach ( $inst['db'] as $dbName => $db ) {
                if ( !isset(static::$_shard_count[$dbName]) )
                    static::$_shard_count[$dbName] = 0;

                foreach ($db as $shard ) {
                    static::$_shard2cnn[$dbName . $shard] = $key;
                    static::$_shard_count[$dbName]++;
                }
            }
        }

        static::$_parse_conf =  [
            static::$_shard2cnn,
            static::$_shard_count,
            static::$_conf['main']
        ];       

        return static::$_parse_conf;


    }



    public static function setToCache($name,  array $conf ) 
    {

        static::$_conf = $conf;
        static::_parse_conf   = null;
        static::_pool         = null;
        static::_shard_count  = null;
        static::_shard2cnn    = null;


        $redis = Service::redis();
        return $redis->set(static::KEY_PREFIX . $name,  serialize($conf) );
    }


}