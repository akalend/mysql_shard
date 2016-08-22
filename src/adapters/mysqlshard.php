<?php namespace MysqlShard\Adapters;

use Exception;
use MysqlShard\Strategy;
use MysqlShard\Tools\Service;
use MysqlShard\Tools\Config;
/**
 * Class MysqlShard
 *
 * использование шардинга
 * этот класс неполный, есть доработанная версия в др ветке
 *
 * @package MysqlShard\Db
 */
class MysqlShard implements \Iterator
{
    /** @var [][]  */
    protected $_conf;
    /** @var [][]  */
    protected $_shard_count = [];
    /** @var Mysql[]  */
    protected $_pool = [];
    /** @var   */
    protected $_main;
    /** @var array  */
    protected $_shard2cnn = [];
    /** @var  int|null */
    private $_last_id;
    /** @var   */
    private $_last_query;
    /** @var   */
    private $_affected;
    /** @var   */
    private $_dbName;
    /** @var Strategy\AbstractStrategy  */
    private $_strategy = null;
    /** @var int */
    private $_errorCode = 0;
    /** @var bool */
    private $_noExec = false;
    /** @var int */
    private $_shard_id = null;
    /** @var int */
    private $shardIterator = 0;

    /**
     * Конструктор класса
     * MysqlShard constructor.
     *
     * @param array $conf - конфигурационный файл
     * @throws Exception
     */
    public function __construct($conf = null)
    {

        if (!$conf) {
            $conf = Config::get('sharding');
        }
        if ( !$conf || !is_array($conf) ) {
            throw new Exception("Error config parameter");
        }

        $this->_conf = $conf;

        foreach ( $this->_conf['instance'] as $key => $inst ) {

            $this->_pool[$key] = false;

            foreach ( $inst['db'] as $dbName => $db ) {
                if ( !isset($this->_shard_count[$dbName]) )
                    $this->_shard_count[$dbName] = 0;

                foreach ($db as $shard ) {
                    $this->_shard2cnn[$dbName . $shard] = $key;
                    $this->_shard_count[$dbName]++;
                }
            }
        }
    }


    /**
     *  используется для интерфейса ShardStrategy
     *
     * @return array
     */
    public function getConfig() {

        if (!isset($this->_conf['main']['user'] )) {
            $this->_conf['main']['user'] = $this->_conf['user'];
        }
        if (!isset($this->_conf['main']['pass'] )) {
            $this->_conf['main']['pass'] = $this->_conf['pass'];
        }

        return [
            $this->_shard2cnn,
            $this->_shard_count,
            $this->_conf['main']
        ];
    }

    /**
     * Возвращает кол-во шард
     *
     * @param $db
     *
     * @return int
     * @throws Exception
     */
    public function getShardCount($db) {

        if (!isset($this->_shard_count[$db])) {
            throw new Exception('Unknow db group name '.$db);
        }

        return $this->_shard_count[$db];
    }


    /**
     * Возвращает MySQL соединение по его номеру
     * реализация отложенного коннекта
     * возвращает экземпляр класса коннекции или NULL
     *
     * @param int $connectId
     *
     * @return MySQL
     * @throws Exception
     */
    public function connect($connectId) {

        $shard = $this->_conf['instance'][$connectId];

        if ( ! $this->_pool[$connectId] ) {
            try {

                $this->_pool[$connectId] = new \mysqli(
                    $shard['host'],
                    $this->_conf['user'],
                    $this->_conf['pass'],
                    '',                 // db name
                    $shard['port']
                );

            } catch (Exception $e){
                // сбросить в лог
                throw new Exception('MySQL Shard connect error');
//                Service::logger()->error('MySQL Shard connect error', ['host' => $shard['host']] );
            }

            if ( !$this->_pool[$connectId] ) {
                throw new Exception("check connection for {$shard['host']}:{$shard['port']}");
            }

        }

        return $this->_pool[$connectId];
    }

    /**
     * @return string
     */
    public function getQuery() {
        return $this->_last_query;
    }

    /**
     * @return int
     */
    public function getAffectedRows() {
        return $this->_affected;
    }

    /**
     * @return int
     */
    public function getLastId() {
        return $this->_last_id;
    }

    /**
     * @param $template
     * @param $tab_id
     * @param $shard_id
     *
     * @return mixed
     */
    private function makeSQL($template, $tab_id, $shard_id)
    {
        $sql1 = str_replace('%t', (string)$tab_id, $template);
        $sql = str_replace('%db', $this->_dbName.'_' . $shard_id, $sql1);

        return $this->_last_query = $sql;
    }


    /**
     * данная функция полезна для итераций по шардам
     * так как извлечение имени таблицы и прочее - большая магия
     *
     * алгоритм выполнения запроса - сперва смотрим жесткий номер шарды
     * потом его уже вычисляем из заданой стратегии
     *
     * для защиты - сбрасывается в NULL после выполнения каддого запроса
     *
     * @param $shard_id int - устанавливает номер шарды
     *
     */
    public function setShardId($shard_id) {
        $this->_shard_id = $shard_id;
    }


    public function reset() {
        $this->_shard_id = null;
    }

    /**
     * Основной метод - выполнение запроса
     * запрос в ввиде шаблона: "SELECT * FROM %db.shard_%t WHERE id=%id"
     *
     * класс сам подставляет номер базы и таблицы в базе в соответствии с заданной стратегией
     *
     * @param string $sql      - шаблон  SQL запроса
     * @param \MysqlShard\Strategy\AbstractStrategy $strategy     - класс стратегия шардирования:
     *
     * @return \PDOStatement
     * @throws Exception
     */
    public function query($sql, \MysqlShard\Strategy\AbstractStrategy  $strategy = null) {

        if ($strategy !== null) {
            $this->_strategy = $strategy;
        } else
            $strategy = $this->_strategy;

        if ($this->_strategy === null) {
            throw new \LogicException('The strategy must be set');
        }

        $this->_dbName = $strategy->getDbName();

        if ($this->_shard_id === null) {
            $shard_id = $strategy->getShardId();
        } else {
            $shard_id = $this->_shard_id;
        }

        $tab_id = $strategy->getTabId();

        $sql = $this->makeSQL($sql,  $tab_id, $shard_id);


        if ($this->_noExec) {
            //обнуляем shard_id
            $this->_shard_id = null;
            return null;
        }


        $cnn_id = $this->_shard2cnn[ $strategy->getKey() ];

        $mysql = $this->_pool[$cnn_id];

        if (!$mysql) {
            $mysql = $this->connect($cnn_id);
        }

        $res = $mysql->query($sql);

        //обнуляем shard_id
        $this->_shard_id = null;

        if (!$res) {
            $this->_errorCode = $mysql->errno;            
        }

        if ($strategy->getId() === null ) {
            // была вставка
            $this->_last_id = $mysql->insert_id;
            // проверяется last_id и при необходимости происходит переключение шарды
            if ($mysql->affected_rows ) {
                $strategy->checkInserted($this->_last_id);
            }

        }

         $this->_affected = $mysql->affected_rows;
        return $res;
    }



    /**
     * Основной метод - выполнение запроса
     * запрос в ввиде шаблона: "SELECT * FROM %db.shard_%t WHERE id=%id"
     *
     * класс сам подставляет номер базы и таблицы в базе в соответствии с заданной стратегией
     *
     * @param string $sql      - шаблон  SQL запроса
     * @param \MysqlShard\Strategy\AbstractStrategy $strategy     - класс стратегия шардирования:
     *
     * @return \PDOStatement
     * @throws Exception
     */
    public function async_query($sql, \MysqlShard\Strategy\AbstractStrategy  $strategy = null) {

        if ($strategy !== null) {
            $this->_strategy = $strategy;
        } else
            $strategy = $this->_strategy;

        if ($this->_strategy === null) {
            throw new \LogicException('The strategy must be set');
        }

        $this->_dbName = $strategy->getDbName();

        if ($this->_shard_id === null) {
            $shard_id = $strategy->getShardId();
        } else {
            $shard_id = $this->_shard_id;
        }

        $tab_id = $strategy->getTabId();

        $sql = $this->makeSQL($sql,  $tab_id, $shard_id);


        if ($this->_noExec) {
            //обнуляем shard_id
            $this->_shard_id = null;
            return null;
        }


        $cnn_id = $this->_shard2cnn[ $strategy->getKey() ];


        $mysql = $this->_pool[$cnn_id];

        if (!$mysql) {
            $mysql = $this->connect($cnn_id);
        }

        $mysql->query($sql, MYSQLI_ASYNC);

    }


    /**
    *   Асиннхронный запрос,
    *   первоночально пишем запросы в буфер, 
    *   потом выполняется запрос на MySQL сервере async_query()
    *
    *   @param string $sql      - шаблон  SQL запроса
    *   
    */
    public function push($query) {
    
       if ($this->_strategy == null) {        
            throw new \LogicalException('The strategy must be set'); 
        }

        if (!isset($this->queryCollector[0])) {
            // посмотреть встроенную функцию
           for($i=0; $i < count($this->_pool); $i++){
                $this->queryCollector[] = [];
            } 
        }

        $this->_dbName = $this->_strategy->getDbName();
        $tab_id = $this->_strategy->getTabId();
        $sql = $this->makeSQL($query,  $tab_id, $this->_strategy->getShardId());
        $cnn_id = $this->_shard2cnn[ $this->_strategy->getKey() ];
        $this->queryCollector[$cnn_id][] = $sql;
    }


    public function flush() {
        while (1) {

            foreach ($this->queryCollector as $cnn_id => &$queryes) {
                $sql = array_shift($queryes);

                if (!$sql) {
                    break;
                }
                if ($this->_pool[$cnn_id] == null) {
                    $this->_pool[$cnn_id] = $this->connect($cnn_id);
                }            
                $this->_pool[$cnn_id]->query( $sql, MYSQLI_ASYNC);
            }

            // тут делаем break
            if (!$sql) {
                $megred = [];

                foreach ($this->queryCollector as $cnn_id => $queryes) {
                    if (count($queryes)){
                        foreach ($queryes as $sql) {
                            $mysql = $this->_pool[$cnn_id];
                            $res = $mysql->query($sql);
                            if (!$res) {
                                throw new Exception('MySQL error: '.$mysql->errno);
                                // echo 'error:', $mysql->errno , PHP_EOL;
                            }
                        }
                    }
                    
                }

                return null;
            }
            return $this->wait();
        }

        // тут если что-то и осталось, то выполняем синхронно
    }


    public function wait() {
        // событийный цикл,
        $processed = 0;
        $res = [];
        do {

            $links = $errors = $reject = [];
            foreach ($this->_pool as $mysql) {
                $links[] = $errors[] = $reject[] = $mysql;
            }

            # опрашиваем все коннекции на наличие ответа
            if (!mysqli_poll($links, $errors, $reject, 60)) {
                continue;
            }
            foreach ($links as $k=>$link) {
                # получаем ответ из асинхронного  запроса
                if ($result = $link->reap_async_query()) {
                    if ($result && $result instanceof \mysqli_result) {
                       $res[$k] = $result->fetch_row();
                        # Handle returned result
                        mysqli_free_result($result);
                    }

                } else {
                    throw  new Exception(sprintf("MySQLi Error: %s", mysqli_error($link)));
                }
                $processed++;
            }
        } while ($processed < count($this->_pool));

        return $res;
    }


    /**
     * Получаем Id коненекции
     *
     * @param string $strategyKey
     *
     * @return int  - номер коннекции
     */
    public function getConnectionId($strategyKey = null)
    {
        if ( null !== $strategyKey ) {
            if (!isset($this->_shard2cnn[$strategyKey])) {
                return null;
            }
            return $this->_shard2cnn[$strategyKey];
        }

        $key = $this->_strategy ? $this->_strategy->getKey() : null;

        if ( !$key ) {
            return null;
        }

        return $this->_shard2cnn[$key];
    }

    /**
     * возвращает кол-во инстансов серверов (коннекций)
     *
     * @return int
     */
    public function getInstanceCount() {
        return count($this->_pool);
    }


    public function getErrorCode() {
        return $this->_errorCode;
    }

    /**
     * Возвращает MySQL соединение по его номеру
     * или NULL
     *
     * @param $connection_id  integer    номер соединения
     * @return Mysql соединение
     */
    public function getConnectionById($connection_id)
    {

        if ( $connection_id < 0 ) {
            return null;
        }

        $count = count($this->_pool);
        if ( $count < $connection_id ) {
            return null;
        }

        if (!isset($this->_pool[$connection_id])) {
            return null;
        }


        if ( $this->_pool[$connection_id] === false ) {
            $this->connect($connection_id);
        }

        return $this->_pool[$connection_id];
    }

    /**
     *  Эта функция служит для отладки,
     *  Отменяет выполнение запроса
     */
    public function setNoExec($flag = true){
        $this->_noExec = $flag;
    }


    /**
     * Этот метод добавлен для страховки, проверяет установку
     * конфига для локальных шардингов
     * используется исключительно для отладки
     *
     * @throws Exception если конфиг не установлен не как девелоперский
     */
    public function checkDeveloperConfig() {

        if (!isset($this->_conf['profile'])) {
            throw new \Exception('profile in the config not set');
        }
        if ($this->_conf['profile'] != 'local') {
            throw new \Exception('profile in not local');
        }

    }

    public function setStrategy( \MysqlShard\Strategy\AbstractStrategy $strategy) {
        $this->_strategy = $strategy;
        $this->_dbName = $strategy->getDbName();
    }


    /* Implements Iterator */
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current(){
        return $this;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->shardIterator ++;
        $this->_strategy->setShardId($this->shardIterator);
        $this->setShardId($this->shardIterator);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key(){
        return $this->shardIterator;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->shardIterator <  $this->getShardCount($this->_dbName);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->shardIterator = 0;
        $this->setShardId(0);
        $this->_strategy->setShardId(0);
    }


}