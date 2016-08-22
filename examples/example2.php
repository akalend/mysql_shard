<?php

require '../src/autoload.php';

use \MysqlShard\Adapters\MysqlShard;
use MysqlShard\Tools\Config;
use \MysqlShard\Strategy\LinearStrategy;
use MysqlShard\Generator\LinesGenerator;


$shard = new MysqlShard();
$strategy = new LinearStrategy(null, 'lines', $shard->getConfig());

// create 10 tables for shards 0..9
for ($i=0; $i < 10; $i++) {
    $query = LinesGenerator::getCreateTableSQL($i);
    $strategy->setId( $i * LinearStrategy::MAX_RECORD_COUNT +1 );
    $shard->query( $query,  $strategy);
}




