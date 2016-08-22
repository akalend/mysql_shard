<?php
/**
 * Created by PhpStorm.
 * User: kalendarev.aleksandr
 * Date: 12/10/15
 *
 * Класс коннвертации дат
 */

namespace MysqlShard\Tools;


class Day {

    /**
     *
     * @param string $date - дата в формате MySQL d.m.Y
     *
     * @return int возвращает данные в формате "Ymd"
     */
    public static function convertJsToCasa($date) {

        $y = substr($date,6);
        $m= substr($date,3,2);
        $d = substr($date,0,2);
        return  (int)$y.$m.$d;
    }

    /**
     *
     * @param string $date - дата в формате MySQL Y-m-d
     *
     * @return int возвращает данные в формате "Ymd"
     */
    public static function convertMysqlToCasa($date) {

        $y = substr($date,0,4);
        $m= substr($date,5,2);
        $d = substr($date,8);
        return  (int)$y.$m.$d;
    }

    /**
     *
     * @param string $date - дата в формате MySQL Y-m-d
     *
     * @return array возвращает данные в массиве [Y,m,d]
     */
    public static function convertMysqlToArray($date) {

        $y = substr($date,0,4);
        $m= substr($date,5,2);
        $d = substr($date,8);
        return  [(int)$y,(int)$m,(int)$d];
    }
}