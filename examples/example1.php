<?php

require '../src/autoload.php';

use MysqlShard\Adapters\MysqlShard;
use MysqlShard\Tools\Config;
use MysqlShard\Strategy\LinearStrategy;
use MysqlShard\Tools\Service;


$shard = new MysqlShard();
$strategy = new LinearStrategy(null, 'lines', $shard->getConfig());

$shard->query( "select 1",  $strategy);
