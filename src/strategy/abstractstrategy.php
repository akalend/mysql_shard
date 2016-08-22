<?php namespace MysqlShard\Strategy;

use Exception;
use MysqlShard\Tools\Service;

/**
 * Класс абстрактной стратегии шардинга
 *
 * стратегия определяет поведение
 *
 *
 * User: kalendarev.aleksandr
 * Date: 10/11/15
 * Time: 12:22
 */
abstract class AbstractStrategy
{

    const CACHE_KEY = 'current_tab_';

    const UNDEF = -1;
    /** @var null|array  */
    protected $_params;
    /** @var int  */
    protected $_id;
    /** @var array  */
    protected $_conf;
    /** @var string  */
    protected $_db;
    /** @var bool  */
    protected $tab_id = false;

    /** @var null|\mysqli  */
    private static $mysql = null;

    protected $_table_prefix = '***';

    protected $_shard_id = null;


    /**
     * @param int    $id          - идентификатор по которому осуществляется шардирование
     *                            для INSERT c autoincrement - должен быть null
     * @param string $db          - имя группы БД (для баз stats_1, stats_2 указывается 'stats' )
     * @param array  $conf        - массив конфига, полученного из MySQLShard->getConfig()
     * @param array  $params      - дополнительные параметры шардирования
     */
    public function __construct($id, $db, $conf, $params = null)
    {
        $this->_id = $id;
        $this->_db = $db;
        $this->_conf = $conf;
        $this->_params = $params;
    }

    /**
     * проверяет последнюю запись
     * на предмет перехода на следующую шарду
     *
     * @param $last_id
     * @return mixed
     */
    abstract function checkInserted($last_id);

    /**
     *
     * @return int
     */
    abstract function getShardId();

    /**
     * @return mixed
     */
    abstract function getTabId();

    /**
     * возвращает ключ, по которому искать в конфиге
     * номер соединения
     *
     * @return mixed
     */
    abstract function getKey();


    /**
     * устанавливает значение параметров
     *
     * @param $param
     */
    public function setParam($param)
    {
        $this->_params = $param;
    }


    /**
     * устанавливает префикс таблицы
     * требует рефакторинга
     * @param $prefix
     */
    public function setTablePrefix($prefix)
    {
        $this->_table_prefix = $prefix;
    }


    /**
     * устанавливает строго номер шардиры
     * используется при итерациях по шардам
     *
     * @param $id  номер шарды
     */
    public function setShardId($id) {
        $this->_shard_id = $id;
    }

    /**
     * @deprecated
     * @param $shardId
     *
     * @return string
     */
    public function getDbNameFull($shardId)
    {
        $this->setShardId($shardId);
        return $this->getDbName() . '_' . $shardId;
    }

    /**
     * @param $tableId
     *
     * @return string
     */
    public function getTableNameFull($tableId)
    {
        return $this->_table_prefix . '_' . $tableId;
    }

    /**
     *
     *  этот метод отдает полное имя таблицы: <имя БД>.<имя Таблицы>
     *
     * @param $shardId
     * @param $tableId
     *
     * @return string
     */
    public function getDbTable($shardId, $tableId)
    {
        return $this->getDbNameFull($shardId) . '.' . $this->getTableNameFull($tableId);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @return string
     */
    public function getDbName()
    {
        return $this->_db;
    }

    /**
     * @todo переделать на pdo, сделать нормальную струкутру
     */
    private function connectMysql()
    {
        if (self::$mysql === null) {
            $conf = $this->_conf[2];
            self::$mysql = new \mysqli($conf['host'], $conf['user'], $conf['pass'], $conf['db'], $conf['port']);
        }
    }

    /**
     * в редис хранится счетчик таблиц, который дублируется в MySQL
     * если нет данных в редисе то они извлекаются из БД main таблица state поля tab_id_[груповое имя  баз]
     *
     * @return bool|string   данные из кеша для данной группы баз
     * @throws Exception
     */
    public function getTabIdFromCache()
    {
        $redis = Service::redis();
        $tab = $redis->get(self::CACHE_KEY . $this->_db);

        if ($tab === false) {

            $this->connectMysql();

            $sql = 'SELECT  tab_id_' . $this->_db . ' FROM state';
            $res = self::$mysql->query($sql);
            if (!$res) {
                throw new Exception('Error query ' . $sql);
            }
            $row = $res->fetch_array();

            unset($res);
            $res = $row[0];
            $redis->set(self::CACHE_KEY . $this->_db, $res);

            return $res;
        }

        return $tab;
    }

    /**
     * @param $tab_id
     *
     * @return void
     */
    public function setTabIdToCache($tab_id)
    {
        $this->connectMysql();

        $sql = 'UPDATE state  SET tab_id_' . $this->_db . '=' . $tab_id;
        self::$mysql->query($sql);

        $redis = Service::redis(1);
        $redis->set(self::CACHE_KEY . $this->_db, $tab_id);
    }

    /**
     *  возвращает кол-во шард
     *
     * @return int
     */
    public function getShardCount()
    {
        return $this->_conf[1][$this->_db];
    }

    /**
     * устанавливает новый параметр шардирования
     *
     * @param int $id  параметр шардирования
     */
    public function setId($id)
    {
        $this->_id = $id;
        $this->tab_id = false;
        $this->_shard_id = null;
    }
}