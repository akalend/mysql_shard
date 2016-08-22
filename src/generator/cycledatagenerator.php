<?php
/**
 * класс генерации  таблиц click2old
 *
 *
 * User: kalendarev.aleksandr
 * Date: 10/11/15
 * Time: 15:01
 */

namespace MysqlShard\Generator;

use MysqlShard\Strategy;
use MysqlShard\Adapters;

class CycleDataGenerator {


    const table = 'CREATE TABLE %%db.xdata(
        id bigint unsigned NOT NULL ,
        data varchar(64) NOT NULL
	) ENGINE=InnoDB ;';

    static public function getCreateTableSQL( ) {
    
        return sprintf(self::table);
    }

    static public function getDropTableSQL( ) {
		return "DROP TABLE  %db.xdata";
	}    
}