<?php namespace MysqlShard\Strategy;

/**
 * реализует стратегию линейного (инкрементного) заполнения шард:
 * по окончании заполнения шарды - переходит к следующей шарде
 * номер записи постоянно увеличивается (автоинкрементен) и один для всех шард bigint
 *
 * используется для хранения (логгирования) данных
 *
 *
 * User: kalendarev.aleksandr
 * Date: 10/11/15
 * Time: 12:30
 */
class LinearStrategy extends AbstractStrategy
{

    /**
     * кол-во записей в одной таблице,
     * 100 M - это где-то заполнение на неделю
     *
     */
    const MAX_RECORD_COUNT = 100000000;

    /**
     * приблизительно за сколько записей перед окончанием переходить на следующую таблицу
     * исключая конкуренцию. Хватит и 25-50, 100 выбрано из соображения максимальной надежности,
     * в дальнейшем  цифру можно изменить, без какой либо потери консистентности данных,
     * значение подбирается исходя из текущей загрузки.
     */
    const LIMIT_RECORDS = 100;

    /** @var null|int  */
    protected $_tab_id = null;
    /** @var null|int  */
    protected $_shard_id = null;

    /**
     * возвращает номер шарды исходя из параметров конфигурации
     *
     * @return int
     */
    public function getShardId()
    {

        if ($this->_tab_id === null) {
            $this->getTabId();
        }

        $this->_shard_id = ((int)$this->_tab_id) % $this->_conf[1][$this->_db];

        return $this->_shard_id;
    }


    /**
     *
     *
     * получение номера текущей таблицы
     * исходя из номера записи
     *
     * @return int
     */
    public function getTabId()
    {

        if ($this->_id === null) {
            return $this->_tab_id = (int)$this->getTabIdFromCache();
        }

        return $this->_tab_id = (int)$this->calculateTableByClickId($this->_id);
    }


    /**
     * проверяет последнюю запись
     * на предмет перехода на следующую шарду
     *
     * @param $last_id
     *
     * @return mixed
     */
    public function checkInserted($last_id)
    {

        if ($this->_tab_id === null) {
            throw new \Exception('do not set the tab_id');
        }
        $limit = ($this->_tab_id + 1) * self::MAX_RECORD_COUNT - self::LIMIT_RECORDS;

        if ($limit < $last_id) {
            // переходим на новую шарду
            $this->_tab_id++;
            $this->setTabIdToCache($this->_tab_id);
        }
    }


    /**
     * возвращает имя ключа, по которому искать
     * в конфиге номер соединения
     *
     * @return string
     */
    public function getKey() {

        if ($this->_shard_id === null)
           return $this->_db . $this->_shard_id;

        $this->getShardId();
        return $this->_db . $this->_shard_id;
    }

    /**
     * @return mixed
     */
    public function getConnectionId()
    {
        return $this->_conf[0][$this->getKey()];
    }

    /**
     * @param int$clickId
     *
     * @return int
     */
    public function calculateTableByClickId($clickId)
    {
        return (int)($clickId / self::MAX_RECORD_COUNT);
    }

    /**
     * распределение таблиц равномерно по всем шардам
     *
     * @param int $tableId
     *
     * @return int
     */
    public function calculateShardByClickId($tableId)
    {
        return ((int)$tableId) % $this->_conf[1][$this->_db];
    }

    /**
     * устанавливает новый параметр шардирования
     *
     * @param $id  параметр шардирования
     */
    public function setId($id) {
        $this->_id = $id;
        // осуществляем пересчет $tab_id
        $this->getTabId();
    }

    public function setShardId($id) {
        $this->_shard_id = null;

    }


    /**
     * @param int $shardId
     *
     * @return mixed
     */
    public function calculateConnectionId($shardId)
    {
        return $this->_conf[0][$this->_db . $shardId];
    }

    /**
     * @deprecated
     *    LEGACY
     *
     *
     * возвращает признак заканчиваемости шарды
     * определяется константой LIMIT_RECORDS кол-во оставшихся записей до полного заполнения
     * необходимо для запаса от переполнения шарды
     *
     * @param int $current_id
     * @param int $tab_id
     *
     * @return bool - true -шарда заканчивается, необходимо переключение
     */
    public function checkNewShard($current_id, $tab_id)
    {
        $limit = ($tab_id + 1) * self::MAX_RECORD_COUNT - self::LIMIT_RECORDS;

        return ($limit < $current_id);
    }

    /**
     * @deprecated
     * @return int   LEGACY
     */
    public function getNextShard()
    {
        if (isset($this->_tab_id)) {
            return $this->getShardId($this->_tab_id + 1);
        }

        return self::UNDEF;
    }
}