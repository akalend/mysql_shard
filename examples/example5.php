<?php

require '../src/autoload.php';

use MysqlShard\Adapters\MysqlShard;
use MysqlShard\Tools\Config;
use MysqlShard\Strategy\CycleStrategy;
use MysqlShard\Tools\Service;


$shard = new MysqlShard();
$strategy = new CycleStrategy(null, 'months', $shard->getConfig());
$shard->setStrategy($strategy);

foreach ($shard as $shardItem) {
	$shardItem->query("TRUNCATE TABLE  %db.xdata", $strategy);
}

$shard->reset();
$time = microtime(true);
// $shard->setNoExec();

for ($i=1; $i < 100; $i++ ) {
	$strategy->setId($i);
	$shard->push( "INSERT INTO `%db`.`xdata` (id,data) VALUES( $i, 'xxx')");
//	$res = $shard->query( "INSERT INTO `%db`.`xdata` (id,data) VALUES( $i, 'xxx')", $strategy);
//	echo "shard_id=", $strategy->getShardId(),'  ' , $shard->getQuery(),"\n";
}

$shard->flush();
echo microtime(true) - $time, PHP_EOL;

exit;




$shard->reset();
foreach ($shard as $shardItem) {
	$shardItem->query("TRUNCATE TABLE  %db.xdata", $strategy);
}

$shard->reset();
$time = microtime(true);
for ($i=1; $i < 100; $i++ ) {
	$strategy->setId($i);
	$res = $shard->query( "INSERT INTO `%db`.`xdata` (id,data) VALUES( $i, 'xxx')", $strategy);
//	echo "sid=", $strategy->getShardId(),'  ' , $shard->getQuery(),"\n";
}
echo microtime(true) - $time, PHP_EOL;
//
// exit();

