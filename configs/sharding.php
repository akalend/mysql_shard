<?php
/**
 * User: kalendarev.aleksandr
 * Date: 09/11/15
 * Time: 14:20
 */

/*   локальный конфиг    */

return [
    'profile' => 'local',   // this parameter for check testing
    'user' => 'akalend',
    'pass' => '12345', // password for db: instance 1 or 2
    'main' =>
        [
            'host' => '127.0.0.1',
            'pass' => '12345',    // password for db main
            'port' => 3307,
            'db' => 'main',
        ],
    'instance' => [
        [
            'host' => '127.0.0.1',
            'port' => 3306,
            'db' => [
                'lines' => [0,1,4],
                'months' => [0,1],
            ],
        ],
        [
            'host' => '127.0.0.1',
            'port' => 3307,
            'db' => [
                'lines' => [2,3],
                'months' => [2,3],
            ],

        ],
    ]
];

