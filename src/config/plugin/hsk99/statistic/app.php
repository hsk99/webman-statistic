<?php

return [
    'enable'         => true,
    'project'        => 'webman',                                            // 应用名
    'interval'       => 30,                                                  // 上报间隔
    'address'        => 'http://127.0.0.1:8788/report/statistic/transfer',   // 上报地址
    'authorization'  => null,                                                // 上报认证key，默认 null
    'global_monitor' => true,                                                // 是否单独监听上报所有进程SQL、Redis
    'http_options'   => [
        'max_conn_per_addr' => 128,   // 每个地址最多维持多少并发连接
        'keepalive_timeout' => 15,    // 连接多长时间不通讯就关闭
        'connect_timeout'   => 1,    // 连接超时时间
        'timeout'           => 1,    // 等待响应的超时时间
    ]
];
