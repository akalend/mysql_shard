<?php

require '../src/autoload.php';

use MysqlShard\Generator\CycleDataGenerator;
use MysqlShard\Adapters\MysqlShard;
use MysqlShard\Strategy\CycleStrategy;

$shard = new MysqlShard();

$strategy = new CycleStrategy(null, 'months', $shard->getConfig());
$shard->setStrategy($strategy);


//$shard->setNoExec();
foreach ($shard as $shardItem) {
    $query = CycleDataGenerator::getCreateTableSQL();
//    $query = CycleDataGenerator::getDropTableSQL();
    $shardItem->query( $query,  $strategy);
    echo  $shardItem->getQuery(), "\n";
}


