<?php
/**
 * класс генерации последовательности таблиц
 *
 *
 * User: kalendarev.aleksandr
 * Date: 10/11/15
 * Time: 15:01
 */

namespace MysqlShard\Generator;

use MysqlShard\Strategy;
use MysqlShard\Adapters;

class LinesGenerator {

    const table = 'CREATE TABLE %%db.lines_%s (
id bigint unsigned NOT NULL AUTO_INCREMENT,
ts int DEFAULT NULL,
ua_id int DEFAULT NULL,
PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=%d;';

    const drop_table = 'DROP TABLE %%db.lines_%s';

    static public function getCreateTableSQL( $shard_id) {
        $begin_id = Strategy\LinearStrategy::MAX_RECORD_COUNT * $shard_id;

        return sprintf(self::table,  $shard_id, $begin_id);
    }


    static public function getDropTableSQL( $shard_id) {

        return sprintf(self::drop_table,  $shard_id);
    }
}