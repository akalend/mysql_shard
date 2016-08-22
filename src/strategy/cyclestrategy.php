<?php namespace MysqlShard\Strategy;
use \MysqlShard\Tools;

/**
 * Стратегия заполнения равномерного распределения по шардам
 * каждая новая шарда расчитывается исходя из id % кол-во шард
 *
 * в данная статегия используется для хранения некоторых временных данных
 *
 *
 * обращение:
 *   $shard->query( $sql,  new MysqlShard\Db\Strategy\CycleStrategy($click_id,  'clicks', $shard->getConfig() ));
 *              $webmaster_id       - id вебмастера - по которому шардится таблицы
 *             'stats',            - имя группы БД
 *              $shard->getConfig() - конфиг в специально подготовленном формате
 *
 */
class CycleStrategy extends AbstractStrategy
{

    protected $_tab_id;


    public function __construct($id, $db, $conf, $params = null)
    {
        if ($id !== null ) {
            $this->_shard_id = $id % $conf[1][$db];
        }
        parent::__construct($id, $db, $conf, $params);
    }


        /**
     * @param null|int $id  получаем номер шарды по $id, если он задан
     *                      или ранее вычисленный номер шарды
     * @return int
     */
    public function getShardId($id = null)
    {
        if ( $id !== null ) {
//            $this->_id = $id; // ???? надо тестить
            $this->_shard_id = (int)($id % $this->_conf[1][$this->_db]);
            return $this->_shard_id;
        }


        if ($this->_id === null && $this->_shard_id !== null) {
            return $this->_shard_id;
        }

        $this->_shard_id = $this->_id % $this->_conf[1][$this->_db];

        return $this->_shard_id;
    }

    /**
     * возвращает ключ, по которому искать в конфиге
     * номер соединения
     *
     * @param null|int $shardId
     *
     * @return string
     */
    public function getKey($shardId = null)
    {
        if ( $shardId !== null ) {
            return "{$this->_db}{$shardId}";
        }

        if ( $this->_shard_id !== null ) {
            return "{$this->_db}{$this->_shard_id}";
        }

        return "{$this->_db}" .  $this->getShardId();
    }

    /**
     * возвращает последнюю часть таблицы
     * используется только в MySqlShard
     *
     * @deprecated
     *
     * @return string
     * @throws \Exception
     */
    public function getTabId()
    {
        return '';
    }

    /**
     * возвращает номер коннекции,
     * если заданы внутренние данные  $id
     *
     * @return int номер коннекции или -1 при неудачи
     *         если вернуло -1, значить какие-то данные не верны
     */
    public function getConnectionId() {

        if ($this->_shard_id !== null) {
            return $this->_conf[0][$this->getKey($this->_shard_id)];
        }

        if ($this->_id != null) {
            $this->_shard_id = $this->getShardId();
            return $this->_conf[0][$this->getKey($this->_shard_id)];
        }

        return -1;
    }


    /**
     *
     * данная функция в этой стратегии не используется
     * оставлена для совместимости
     * шарды заполняются по кругу и делятся по $id (click_id)
     *
     * @param null
     */
    public function checkInserted($last_id) { }


    /**
     * сбрасывает данные $id $shard_id
     */
    public function reset()
    {
        $this->_id = null;
        $this->_shard_id = null;
    }


}