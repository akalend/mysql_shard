<?php namespace MysqlShard\Tools;
/**
 * Created by PhpStorm.
 * User: akalend
 * Date: 15.06.16
 * Time: 23:23
 */

use MysqlShard\Tools\Config;


/**
 * Сервис-локатор
 */
class Service
{


    /**
     * @var \MysqlShard\Adapters\MysqlShard
     */
    protected static $_mysqlShard;

    /**
     * Возвращает объект адаптера бд
     *
     * @return \MysqlShard\Adapters\MysqlShard
     */
    public static function mysqlShard()
    {
        if ( self::$_mysqlShard === null ) {

            $conf = self::conf()->getValues('sharding');

            self::$_mysqlShard = new \MysqlShard\Adapters\MysqlShard($conf);
        }

        return self::$_mysqlShard;
    }


    /**
     * @var \Tools\Config
     */
    protected static $_conf;


    /**
     * Объект для работы с конфигом
     *
     * @return \Tools\Config
     */
//    public static function conf()
//    {
//        if (self::$_conf === null) {
//
//            self::$_conf = new Config( );
//        }
//
//        return self::$_conf;
//    }



    /**
     * @var array
     */
    protected static $_redisConnections = null;
    /**
     * Возвращает инстанс редиса
     *
     * @param int $db Номер базы данных
     * @return \Zotto\Db\Adapters\Redis
     */
    public static function redis()
    {
        if (!isset(self::$_redisConnections)) {

            $conf = Config::get('redis');
            $host = $conf['host'];
            $port = $conf['port'];

            $redis = new \Redis();
            $redis->connect($host, $port);

            self::$_redisConnections= $redis;
        }

        return self::$_redisConnections;
    }

}
