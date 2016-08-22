<?php namespace MysqlShard\Tools;
/**
 * Created by PhpStorm.
 * User: akalend
 * Date: 16.06.16
 * Time: 23:47
 */


/**
 * Load configuration
 *
 * Class Config
 * @package MysqlShard\tools
 */
class Config
{
    public static function get($name)
    {
        if (!is_dir(MYSQLSHARD_CONF)) {
            throw new \Exception('The configs dir is error');
        }

        return require( MYSQLSHARD_CONF .'/'. $name . '.php');
    }
}