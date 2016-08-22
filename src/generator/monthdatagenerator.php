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

class MonthDataGenerator {


    const table = 'CREATE TABLE %%db.data(
	old_click_id bigint unsigned NOT NULL ,
	click_id bigint unsigned NOT NULL,
	PRIMARY KEY (old_click_id)
	) ENGINE=InnoDB ;';

    static public function getCreateTableSQL( ) {
    
        return sprintf(self::table);
    }

    static public function getDropTableSQL( ) {
		return "DROP TABLE  %%db.data";
	}    
}