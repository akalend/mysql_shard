<?php
/**
 *  данный скрипт запускается по крону раз в сутки/неделю и
 *  проверяет заполнение таблиц группы lines
 *  
 *  если осталось две не заполненные таблицы на каждую шарду,
 *  то создается еще ряд таблиц
 *
 * User: kalendarev.aleksandr
 * Date: 20/11/15
 * Time: 12:37
 */

require  '../autoload.php';

use \MysqlShard\Adapters;
use \MysqlShard\Strategy;
use \MysqlShard\Generator;
use \MysqlShard\Tools\Services;
use \MysqlShard\Tools\Config;

class CheckStatTablesAtFulling
{

    /**
     * @const имя группы БД (таблиц), которую мы мониторим
     */
    const name = 'lines';


    /**
     * @const количество новых сгенерированных таблиц
     */
    const count = 12;

    /**
     * @const минимальное количество пустых таблиц, до генерации новых таблиц
     */
    const emptyTables = 2;

    public function run() {

        $conf = Config::get('sharding');

        $shard = new Adapters\MysqlShard($conf);

        $strategy = new Strategy\LinearStrategy(null, self::name, $shard->getConfig());
        $name = self::name;

        $pos = strlen(self::name) + 2;
        $sql = "SELECT  substr( TABLE_NAME, $pos) AS num, TABLE_ROWS as `count`  
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA 
                    LIKE '{$name}_%' 
                    AND  TABLE_NAME REGEXP '^({$name}_)[0-9]+$'";

        $tables = [];

        for ($cnn_id = 0; $cnn_id < $shard->getInstanceCount(); $cnn_id++ ) {

            try {
                $mysql = $shard->getConnectionById($cnn_id);
            } catch (Exception $e) {
                echo $e->getMessage();
            }

            $res = $mysql->query($sql);

            while (($row = $res->fetch_assoc()) != null) {
                  $tables[$row['num']] = $row['count'];
            }
        }

        $sum = count($tables);
        $fillTables = 0;
        $startTabId = 0;

        foreach($tables as $tab_id => $count) {
            if ($count > 0) $fillTables++;
            if ($tab_id > $startTabId) $startTabId = (int)$tab_id;
        }

        if ($sum - $fillTables > self::emptyTables) {
            $out = sprintf('fulling tables %d from %d %s', $fillTables, $sum, date('Y-m-d') );
            echo $out, PHP_EOL;
            exit;
        }

        $out = sprintf('generate: full tables %d from %d', $fillTables, $sum );

        $startTabId ++;
        echo 'generate tables:', PHP_EOL;
//        $shard->setNoExec();

        $table_names = '';
            for ($i = $startTabId; $i < $startTabId + self::count; $i++) {

                $table_names .= self::name . '_' .$i . PHP_EOL;

                $strategy->setId( Strategy\LinearStrategy ::MAX_RECORD_COUNT * $i +1 );
                $table = Generator\LinesGenerator::getCreateTableSQL($i);

                $shard->query( $table, $strategy);
//                echo $shard->getQuery(), PHP_EOL,PHP_EOL;
            }

            echo  $table_names ,PHP_EOL;

    }
}

(new CheckStatTablesAtFulling)->run();