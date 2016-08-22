<?php
/**
 * Sharding tests
 *
 *
 * run: $zotto3/lib/phpunit$ phpunit  [--bootstrap=bootstrap.php] --filter=ShardingTest   tests
 *
 *      phpunit  --filter=ShardingTest --colors --repeat 20  tests
 *
 * User: kalendarev.aleksandr
 * Date: 15/03/16
 * Time: 12:40
 */

use MysqlShard\Tools\Service;
use MysqlShard\Adapters\MysqlShard;
use MysqlShard\Strategy\LinearStrategy;
use MysqlShard\Tools\Config;
use MysqlShard\Generator\LinesGenerator;


define( 'R_KEY', 'current_tab_lines');

class LinearStrategyTest extends PHPUnit_Framework_TestCase
{

    private $sharding = null;

    private $shard_id = null;

    public function setUp() {

        $conf = Config::get('sharding');

        $this->sharding = new MysqlShard($conf);

        // проверяет что установлен конфиг разработчика
        $this->sharding->checkDeveloperConfig();

        // сохранить состояние
        $strategy = new LinearStrategy(1,'lines', $this->sharding->getConfig());
        $this->shard_id = (int)$strategy->getTabIdFromCache();

    }


    public function tearDown() {
        // восстановить состояние
        $strategy = new LinearStrategy(1,'lines', $this->sharding->getConfig());
        $strategy->setTabIdToCache($this->shard_id);
    }


    /**
     * чистка БД
     * шарды 3 и 4 5  на разных физ серверах (коннекциях)
     */
    private function dbInit() {

        $this->sharding->setNoExec(false); // делаем запрос исполняемым
        $strategy = new LinearStrategy(null,'lines', $this->sharding->getConfig());
        
        $strategy->setId(899999890);
        $shard_id = 8;
        $this->sharding->query("DROP TABLE %db.lines_%t", $strategy);        
        $sql = LinesGenerator::getCreateTableSQL($shard_id);
        $this->sharding->query($sql, $strategy); 


        $strategy->setId(499999890);
        $shard_id = 4;
        $this->sharding->query("DROP TABLE %db.lines_%t", $strategy);        
        $sql = LinesGenerator::getCreateTableSQL($shard_id);
        $this->sharding->query($sql, $strategy); 



        $strategy->setId(999999890);
        $shard_id = 9;
        $this->sharding->query("DROP TABLE %db.lines_%t", $strategy);        
        $sql = LinesGenerator::getCreateTableSQL($shard_id);
        $this->sharding->query($sql, $strategy); 

    }


    /**
     *  проверка номеров шард в соответствии с id клика
     */
    public function testCheckLinearStrategyById(){

        $strategy = new LinearStrategy(1,'lines', $this->sharding->getConfig());

        $this->assertInstanceOf('MysqlShard\Strategy\LinearStrategy', $strategy);

        $this->assertEquals(0,  $strategy->getShardId() );

        unset($strategy);
        $strategy = new LinearStrategy(1000000,'lines', $this->sharding->getConfig());
        $this->assertEquals(0,  $strategy->getShardId() );

        unset($strategy);
        $strategy = new LinearStrategy(10000000,'lines', $this->sharding->getConfig());
        $this->assertEquals(0,  $strategy->getShardId() );

        unset($strategy);
        $strategy = new LinearStrategy(100000000,'lines', $this->sharding->getConfig());
        $this->assertEquals(1,  $strategy->getShardId() );

        unset($strategy);
        $strategy = new LinearStrategy(99999999,'lines', $this->sharding->getConfig());
        $this->assertEquals(0,  $strategy->getShardId() );

        unset($strategy);
        $strategy = new LinearStrategy(LinearStrategy::MAX_RECORD_COUNT,'lines', $this->sharding->getConfig());
        $this->assertEquals(1,  $strategy->getShardId() );

        unset($strategy);
        $strategy = new LinearStrategy(NULL,'lines', $this->sharding->getConfig());
        $strategy->setId(LinearStrategy::MAX_RECORD_COUNT + 1000 );
        $this->assertEquals(1,  $strategy->getShardId() );

        unset($strategy);
        $strategy = new LinearStrategy(LinearStrategy::MAX_RECORD_COUNT * 2 + 10000000,'lines', $this->sharding->getConfig());
        $this->assertEquals(2,  $strategy->getTabId() );


        unset($strategy);
        $strategy = new LinearStrategy(LinearStrategy::MAX_RECORD_COUNT * 7 ,'lines', $this->sharding->getConfig());
        $this->assertEquals(7,  $strategy->getTabId() );

        unset($strategy);
        $strategy = new LinearStrategy(LinearStrategy::MAX_RECORD_COUNT * 2 + 10000000,'lines', $this->sharding->getConfig());
        $res = $strategy->checkNewShard( LinearStrategy::MAX_RECORD_COUNT * 3 - 200 , 2);
        $this->assertFalse($res);


        // как только мы переваливаем за порог осталось в шарде менее чем LinearStrategy::LIMIT_RECORDS,
        // то метод возвращает TRUE
        unset($strategy);
        $strategy = new LinearStrategy(null,'lines', $this->sharding->getConfig());
        $res = $strategy->checkNewShard( LinearStrategy::MAX_RECORD_COUNT * 3 - LinearStrategy::LIMIT_RECORDS + 1 , 2);
        $this->assertTrue($res);


        unset($strategy);
        $id =LinearStrategy::MAX_RECORD_COUNT * 3 - LinearStrategy::LIMIT_RECORDS + 11;
        $strategy = new LinearStrategy(null,'lines', $this->sharding->getConfig());
        $strategy->setId($id);

        // тестируем переключение таблицы в редис
        $redis = Service::redis(1);
        $redis->set(R_KEY, "2");

        unset($strategy);
        $id = LinearStrategy::MAX_RECORD_COUNT * 5 + 100;
        $strategy = new LinearStrategy($id,'lines', $this->sharding->getConfig());
        $this->assertEquals(5, $strategy->getTabId());

        $id = 299999897;
        $strategy->setId($id);
        $this->assertEquals(2, $strategy->getTabId());
        $strategy->checkInserted($id);
        $this->assertEquals("2", $redis->get(R_KEY));


        $id = 299999898;
        $strategy->setId($id);
        $this->assertEquals(2, $strategy->getTabId());
        $strategy->checkInserted($id);
        $this->assertEquals("2", $redis->get(R_KEY));

        $id = 299999899;
        $strategy->setId($id);
        $this->assertEquals(2, $strategy->getTabId());
        $strategy->checkInserted($id);
        $this->assertEquals("2", $redis->get(R_KEY));

        $id = 299999900;
        $strategy->setId($id);
        $this->assertEquals(2, $strategy->getTabId());
        $strategy->checkInserted($id);
        $this->assertEquals("2", $redis->get(R_KEY));


        // тут происходит переключение
        $id = 299999901;;
        $strategy->setId($id);
        $this->assertEquals(2, $strategy->getTabId());
        $strategy->checkInserted($id);
        $this->assertEquals("3", $redis->get(R_KEY));

        $id = 299999902;
        $strategy->setId($id);
        $this->assertEquals(2, $strategy->getTabId());
        $strategy->checkInserted($id);
        $this->assertEquals("3", $redis->get(R_KEY));

    }

   /**
    *  ожидаем исключение, так как не задан tab_id
    *  @expectedException     Exception
    */
   public function testTabIdException() {
       $strategy = new LinearStrategy(null,'lines', $this->sharding->getConfig());
       $strategy->checkInserted(111);
   }

   /**
    * в данном случае не должно возникнуть исключения, так как вызывается метод getTabId();
    * который формирует внутреннее значение номера таблицы
    */
   public function testTabIdWithoutException() {
       $strategy = new LinearStrategy(null,'lines', $this->sharding->getConfig());
       $strategy->setId(111);
       $this->assertEquals(0, $strategy->getTabId());
       $strategy->checkInserted(111);
   }



   public function testQueryNoExec() {

       $strategy = new LinearStrategy(LinearStrategy::MAX_RECORD_COUNT * 5 + 1,'lines', $this->sharding->getConfig());

       // only text of query, without exec
       $this->sharding->setNoExec();

       $this->sharding->query("SELECT * FROM %db.linees2old_%t WHERE id=123", $strategy);
       $sql = $this->sharding->getQuery();
       $this->assertEquals("SELECT * FROM lines_0.linees2old_5 WHERE id=123",$sql);
       $this->assertEquals(0, $this->sharding->getConnectionId());
       $this->assertEquals(0, $strategy->getConnectionId());

       unset($strategy);

       $strategy = new LinearStrategy(LinearStrategy::MAX_RECORD_COUNT * 6 +1,'lines', $this->sharding->getConfig());
       $this->sharding->query("SELECT * FROM %db.linees2old WHERE id=123", $strategy);
       $sql = $this->sharding->getQuery();
       $this->assertEquals("SELECT * FROM lines_1.linees2old WHERE id=123",$sql);
       $this->assertEquals(0, $this->sharding->getConnectionId());
       $this->assertEquals(0, $strategy->getConnectionId());

       unset($strategy);

       $strategy = new LinearStrategy(LinearStrategy::MAX_RECORD_COUNT * 2 + 1,'lines', $this->sharding->getConfig());
       $this->sharding->query("SELECT * FROM %db.linees2old_%t WHERE id=123", $strategy);
       $sql = $this->sharding->getQuery();
       $this->assertEquals("SELECT * FROM lines_2.linees2old_2 WHERE id=123",$sql);
       $this->assertEquals(1, $this->sharding->getConnectionId());
       $this->assertEquals(1, $strategy->getConnectionId());

   }

   /**
    *  данные для теста testCheckQueryForAnyShards()
    *           instance 0 :'lines' => [0,1,4],
    *           instance 1 : 'lines' => [2,3],
    *
    */
   public function sqlResultProvider( ) {
       return  [
           "TRUNCATE TABLE lines_0.lines_0",
           "TRUNCATE TABLE lines_1.lines_1",
           "TRUNCATE TABLE lines_2.lines_2",
           "TRUNCATE TABLE lines_3.lines_3",
           "TRUNCATE TABLE lines_4.lines_4",
           "TRUNCATE TABLE lines_0.lines_5",
           "TRUNCATE TABLE lines_1.lines_6",
           "TRUNCATE TABLE lines_2.lines_7",
           "TRUNCATE TABLE lines_3.lines_8",
           "TRUNCATE TABLE lines_4.lines_9",
       ];
   }


   /**
    *
    */
   public  function testCheckQueryForAnyShards(){
       $strategy = new LinearStrategy(null,'lines', $this->sharding->getConfig());
       $strategy->setId(799999890);
       $this->sharding->setNoExec();
       $this->sharding->query("TRUNCATE TABLE %db.lines_%t", $strategy);
       $sql = $this->sharding->getQuery();
       $this->assertEquals('TRUNCATE TABLE lines_2.lines_7', $sql);


       $sql = $this->sqlResultProvider();

       // проверка на соответствие данным из self::sqlResultProvider()

       for ($i= 0; $i < 10 ; $i++) {
           unset($strategy);
           $strategy = new LinearStrategy(null, 'lines', $this->sharding->getConfig());
           $strategy->setId(LinearStrategy::MAX_RECORD_COUNT * $i);
           $this->sharding->query("TRUNCATE TABLE %db.lines_%t", $strategy);

           $this->assertEquals( $sql[$i], $this->sharding->getQuery());
       }

       // то же но с одним классом стратегии
       for ($i= 0; $i < 10 ; $i++) {
           $id = LinearStrategy::MAX_RECORD_COUNT * $i + 1;
           $strategy->setId($id);
           $this->sharding->query("TRUNCATE TABLE %db.lines_%t", $strategy);
           $this->assertEquals( $sql[$i], $this->sharding->getQuery());
       }
   }



   /**
    *  tests of check Db name
    */
   public function testDbName() {

       $strategy = new LinearStrategy(null,'lines', $this->sharding->getConfig());
       $dbName = $strategy->getDbNameFull(1);
       $this->assertEquals('lines_1', $dbName);

       $dbName = $strategy->getDbName(1);
       $this->assertEquals('lines', $dbName);

       // нужно использовать установку префикса из-за фикса метода getDbNameFull()
       $strategy->setTablePrefix('lines');
       $dbName = $strategy->getDbTable(1,2);
       $this->assertEquals('lines_1.lines_2', $dbName);


       unset($strategy);

       $strategy = new LinearStrategy(null,'lines', $this->sharding->getConfig());

       // нужно использовать установку префикса из-за фикса метода getDbNameFull()
       $strategy->setTablePrefix('lines');
       $dbName = $strategy->getTableNameFull(2);

       $this->assertEquals('lines_2', $dbName);
   }



    /**
     *  данный тест пишет 120 кликов начиная с id = 799999890
     *  клики должны лечь в таблицы lines_7, lines_8
     *  после этого считываются все данные из этих таблиц
     *  и проверяются
     *  по окончанию теста - данные зачищаются
     *
     */
    public function testQueryRealExec()
    {

        // clear tables
        $this->dbInit();


        /**
        *   set shard to seven
        */
        $time = time();
        $expire = $time + 100;
        $id = 899999890;
        $sql = "INSERT INTO %db.lines_%t (id, ts,ua_id) VALUES($id, $time, 1777)";

        $strategy = new LinearStrategy($id,'lines', $this->sharding->getConfig());
        $strategy->setTabIdToCache(8);
        $this->sharding->query($sql, $strategy);


        // test on insert record        

        $sql = "SELECT * FROM %db.lines_%t";
        
        $recordSet = $this->sharding->query($sql, $strategy);

        $this->assertNotNull($recordSet);

        $row = $recordSet->fetch_array();
        $this->assertNotNull($row);
        $this->assertEquals($time, $row[1]);
        $this->assertEquals($id, $row[0]);
        $this->assertEquals(1777, $row[2]);

               // $this->dbInit();

        //------- begin test for change shard  -------


        $strategy = new LinearStrategy(null,'lines', $this->sharding->getConfig());
        $tab_id = 7;
        for ($i = 0; $i < 20; $i++) {
            $sql = "INSERT INTO %db.lines_%t  (ts,ua_id) VALUES($time, $tab_id)";
            $tab_id = $strategy->getTabId();
            $this->sharding->query($sql, $strategy);
            $this->assertEquals(0, $this->sharding->getErrorCode());    
            $this->assertEquals( $tab_id,  (int)($this->sharding->getLastId() / LinearStrategy::MAX_RECORD_COUNT));
                     
        }

 

        // check  data into shard 8

        $id = 899999890;
        $strategy->setId($id);
  
        $sql = "SELECT * FROM %db.lines_%t ORDER BY id";
        $res = $this->sharding->query($sql, $strategy);
        $offer_id = 1;

        $this->assertNotNull($res);

        $counter = 0;
        while(($row = $res->fetch_assoc()) != null ) {
            $this->assertEquals($time, $row['ts'] );
            $this->assertEquals($id, $row['id'] );
            $id ++;
            $counter++;
        }

        // теперь проверяем вставку оставшихся данных на шарде 8
        // сквозной счетчик на поле offer_id
        $id = 9 * LinearStrategy::MAX_RECORD_COUNT;
        $strategy->setId( $id );

        $sql = "SELECT * FROM %db.lines_%t ORDER BY id";
        $res = $this->sharding->query($sql, $strategy);

        while(($row = $res->fetch_array()) != null ) {
            $this->assertEquals($time, $row['ts'] );
            $counter++;
            $id ++;
        }

        $this->assertEquals(21, $counter);

        // clean data
        $this->dbInit();
    }



}