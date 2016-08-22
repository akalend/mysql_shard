<?php
/**
 * Created by PhpStorm.
 * User: akalend
 * Date: 15.06.16
 * Time: 1:19
 */


require dirname(__DIR__) . '/vendor/autoload.php';

define( 'MYSQLSHARD_DIR', dirname( __DIR__) );
define( 'MYSQLSHARD_SRC', __DIR__  );
define( 'MYSQLSHARD_CONF', MYSQLSHARD_DIR . '/configs' );
/**
 *
 * Регистрация автозагрузчика
 */
spl_autoload_register(
    function($className)
    {
        if (substr($className, 0, 10) !== 'MysqlShard') {
            return;
        }

        $subPath = strtolower(substr($className, 10));
        $subPath = str_replace('\\', DIRECTORY_SEPARATOR, $subPath);


        $filePath = MYSQLSHARD_SRC . $subPath . '.php';

        if (file_exists($filePath)) {
            require $filePath;
        }
    }
);
