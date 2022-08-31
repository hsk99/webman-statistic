<?php

return [
    'enable'         => true,
    'project'        => 'webman',                                            // 应用名
    'interval'       => 30,                                                  // 上报间隔
    'address'        => 'http://127.0.0.1:8788/report/statistic/transfer',   // 上报地址
    'authorization'  => null,                                                // 上报认证key，默认 null
    'global_monitor' => true,                                                // 是否单独监听上报所有进程SQL、Redis
];
