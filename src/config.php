<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 名称
    |--------------------------------------------------------------------------
    */
    'name'               => 'BTSpider',
    /*
    |--------------------------------------------------------------------------
    | 数据路径
    |--------------------------------------------------------------------------
    */
    'data_file'          => BTSPIDER_ROOT.'/storage/data/btspider.txt',
    /*
    |--------------------------------------------------------------------------
    | 状态文件路径
    |--------------------------------------------------------------------------
    */
    'stats_file'         => BTSPIDER_ROOT.'/storage/logs/_btstats.log',
    /*
    |--------------------------------------------------------------------------
    | 日志路径
    |--------------------------------------------------------------------------
    */
    'log_file'           => BTSPIDER_ROOT.'/storage/logs/btspider.log',
    /*
    |--------------------------------------------------------------------------
    | 日志等级
    |--------------------------------------------------------------------------
    */
    'log_level'          => \Monolog\Logger::DEBUG,
    /*
    |--------------------------------------------------------------------------
    | 日志最大保存天数
    |--------------------------------------------------------------------------
    */
    'log_max'            => 7,
    /*
    |--------------------------------------------------------------------------
    | 查找节点时间间隔，单位为毫秒
    |--------------------------------------------------------------------------
    */
    'find_node_interval' => 10000,
    /*
    |--------------------------------------------------------------------------
    | 用于查找节点的初始节点
    |--------------------------------------------------------------------------
    | 格式： 
    | [
    |   [ip, port],
    |   [ip, port],
    | ]
    */
    'bootstrap_nodes'    => [
        ['router.utorrent.com', 6881],
        ['router.bittorrent.com', 6881],
        ['dht.transmissionbt.com', 6881],
        ['router.bitcomet.com', 6881],
        ['dht.aelitis.com', 6881],
    ],

    /*
    |--------------------------------------------------------------------------
    | 任务进程设置
    |--------------------------------------------------------------------------
    */
    'worker'             => [
        /*
        |--------------------------------------------------------------------------
        | 进程数量
        |--------------------------------------------------------------------------
        */
        'worker_num'         => 4,
        /*
        |--------------------------------------------------------------------------
        | 进程最大运行的任务数量
        |--------------------------------------------------------------------------
        */
        'running_max'        => 300,
        /*
        |--------------------------------------------------------------------------
        | 进程最大队列存储数量
        |--------------------------------------------------------------------------
        */
        'task_queue_max'     => 10000,
        /*
        |--------------------------------------------------------------------------
        | 进程空闲时等待时间
        |--------------------------------------------------------------------------
        */
        'free_wait_time'     => 0.001,
        /*
        |--------------------------------------------------------------------------
        | 进程退出时等待时间
        |--------------------------------------------------------------------------
        */
        'max_exit_wait_time' => 0.001,
    ],

    /*
    |--------------------------------------------------------------------------
    | Swoole 设置
    |--------------------------------------------------------------------------
    */
    'server'             => [
        /*
        |--------------------------------------------------------------------------
        | 监听地址
        |--------------------------------------------------------------------------
        */
        'host'     => '0.0.0.0',
        /*
        |--------------------------------------------------------------------------
        | 监听端口
        |--------------------------------------------------------------------------
        */
        'port'     => 6882,
        /*
        |--------------------------------------------------------------------------
        | 监听多个端口
        |--------------------------------------------------------------------------
        */
        'ports'    => [
            // 6883
        ],
        /*
        |--------------------------------------------------------------------------
        | Swoole Server 设置
        |--------------------------------------------------------------------------
        | 具体设置可以查看 Swoole 官方文档 https://wiki.swoole.com/#/
        */
        'settings' => [
            'worker_num'    => 4,
            'dispatch_mode' => 1,
            'reload_async'  => true,
            'log_level'     => SWOOLE_LOG_INFO,
            'log_file'      => BTSPIDER_ROOT.'/storage/logs/swoole.log',
            'stats_file'    => BTSPIDER_ROOT.'/storage/logs/_stats.log',
            'pid_file'      => BTSPIDER_ROOT.'/storage/tmp/swoole.pid',
        ],
    ]
];
