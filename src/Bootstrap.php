<?php

namespace Hsk99\WebmanStatistic;

use Hsk99\WebmanStatistic\Statistic;

class Bootstrap implements \Webman\Bootstrap
{
    /**
     * @var \Workerman\Http\Client
     */
    protected static $_instance = null;

    /**
     * @var string
     */
    public static $process = null;

    /**
     * @author HSK
     * @date 2022-06-17 15:33:53
     *
     * @param \Workerman\Worker $worker
     *
     * @return void
     */
    public static function start($worker)
    {
        if ($worker) {
            static::$process = $worker->name;

            $options = [
                'max_conn_per_addr' => 128, // 每个地址最多维持多少并发连接
                'keepalive_timeout' => 15,  // 连接多长时间不通讯就关闭
                'connect_timeout'   => 30,  // 连接超时时间
                'timeout'           => 30,  // 等待响应的超时时间
            ];
            self::$_instance = new \Workerman\Http\Client($options);

            // 定时上报数据
            \Workerman\Timer::add(config('plugin.hsk99.statistic.app.interval', 30), function () {
                Statistic::report();
            });

            // 执行监听所有进程 SQL、Redis
            if (config('plugin.hsk99.statistic.app.global_monitor', false)) {
                static::listen($worker);
            }
        }
    }

    /**
     * @author HSK
     * @date 2022-06-17 15:34:41
     *
     * @return \Workerman\Http\Client
     */
    public static function instance(): \Workerman\Http\Client
    {
        return self::$_instance;
    }

    /**
     * SQL、Redis 监听
     *
     * @author HSK
     * @date 2022-07-20 17:22:06
     *
     * @param \Workerman\Worker $worker
     * 
     * @return void
     */
    protected static function listen(\Workerman\Worker $worker)
    {
        if (class_exists(\think\facade\Db::class)) {
            \think\facade\Db::listen(function ($sql, $runtime, $master) {
                if ($sql === 'select 1' || !is_numeric($runtime)) {
                    return;
                }
                Statistic::sql(trim($sql), $runtime * 1000, ['master' => $master]);
            });
        }

        if (class_exists(\Illuminate\Database\Events\QueryExecuted::class)) {
            try {
                \support\Db::listen(function (\Illuminate\Database\Events\QueryExecuted $query) {
                    $sql = trim($query->sql);
                    if (strtolower($sql) === 'select 1') {
                        return;
                    }
                    $sql = str_replace("?", "%s", $sql);
                    foreach ($query->bindings as $i => $binding) {
                        if ($binding instanceof \DateTime) {
                            $query->bindings[$i] = $binding->format("'Y-m-d H:i:s'");
                        } else {
                            if (is_string($binding)) {
                                $query->bindings[$i] = "'$binding'";
                            }
                        }
                    }
                    $log = $sql;
                    try {
                        $log = vsprintf($sql, $query->bindings);
                    } catch (\Throwable $e) {
                    }
                    Statistic::sql($log, $query->time, ['connection' => $query->connectionName]);
                });
            } catch (\Throwable $e) {
            }
        }

        if (class_exists(\Illuminate\Redis\Events\CommandExecuted::class)) {
            foreach (config('redis', []) as $key => $config) {
                if (strpos($key, 'redis-queue') !== false) {
                    continue;
                }
                try {
                    \support\Redis::connection($key)->listen(function (\Illuminate\Redis\Events\CommandExecuted $command) {
                        foreach ($command->parameters as &$item) {
                            if (is_array($item)) {
                                $item = implode('\', \'', $item);
                            }
                        }
                        Statistic::redis($command->command, implode('\', \'', $command->parameters), $command->time, ['connection' => $command->connectionName]);
                    });
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
