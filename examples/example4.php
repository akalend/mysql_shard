<?php

require '../src/autoload.php';

use MysqlShard\Generator\StatsGenerator;
use MysqlShard\Adapters\MysqlShard;
use MysqlShard\Tools\Config;
use MysqlShard\Strategy\MonthStrategy;


$shard = new MysqlShard();
$strategy = new MonthStrategy(null, 'lines', $shard->getConfig(), '2016-06');
$shard->setStrategy($strategy);


$shard->setNoExec();
foreach ($shard as $shardItem) {
    $query = StatsGenerator::getCreateTableSQL();
    $shardItem->query( $query,  $strategy);
    echo  $shardItem->getQuery(), "\n";
}




