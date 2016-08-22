<?php namespace MysqlShard\Strategy;
use \MysqlShard\Tools;

/**
 * Стратегия заполнения помесячно
 * каждая новая шарда расчитывается исходя из номера месяца и webmaster_id
 * является производной от CycleStartegy
 *
 * в данная статегия используется для хранения обобщенной статистической информации
 *
 *
 * обращение:
 *   $shard->query( $sql,  new MysqlShard\Db\Strategy\MonthStrategy($webmaster_id,  'stats', $shard->getConfig(), '2015-10'));
 *              $webmaster_id       - id вебмастера - по которому шардится таблицы
 * ,            'stats',            - имя группы БД
 *              $shard->getConfig() - конфиг? в специально подготовленном формате
 *              '2015-10'           - число, второй параметр шардинга в MySQL формате, можно задать в сокращенной форме (без дня)
 *
 * Created by PhpStorm.
 * User: kalendarev.aleksandr
 * Date: 10/11/15
 * Time: 12:36
 */
class MonthStrategy extends CycleStrategy
{


    /**
     * возвращает последнюю часть таблицы
     *
     * @return string
     * @throws \Exception
     */
    public function getTabId()
    {

        if ($this->_params == null)
            throw new \Exception('must be set data as parameter');

        $day = \MysqlShard\Tools\Day::convertMysqlToArray($this->_params);

        return sprintf( "{$day[0]}_%'02d", $day[1]);
    }

}