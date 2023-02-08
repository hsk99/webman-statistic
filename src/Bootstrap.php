<?php

namespace Hsk99\WebmanStatistic;

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Http\Client;
use think\facade\Db as ThinkDb;
use support\Db as LaravelDb;
use Illuminate\Database\Events\QueryExecuted;
use support\Redis;
use Illuminate\Redis\Events\CommandExecuted;
use Hsk99\WebmanStatistic\Statistic;

class Bootstrap implements \Webman\Bootstrap
{
    /**
     * @var Client
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
     * @param Worker $worker
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
            self::$_instance = new Client(config('plugin.hsk99.statistic.app.http_options', $options));

            // 定时上报数据
            Timer::add(config('plugin.hsk99.statistic.app.interval', 30), function () {
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
     * @return Client
     */
    public static function instance(): Client
    {
        return self::$_instance;
    }

    /**
     * SQL、Redis 监听
     *
     * @author HSK
     * @date 2022-07-20 17:22:06
     *
     * @param Worker $worker
     * 
     * @return void
     */
    protected static function listen(Worker $worker)
    {
        if (class_exists(ThinkDb::class)) {
            ThinkDb::listen(function ($sql, $runtime, $master) {
                if ($sql === 'select 1' || !is_numeric($runtime)) {
                    return;
                }
                Statistic::sql(trim($sql), $runtime * 1000, ['master' => $master]);
            });
        }

        if (class_exists(QueryExecuted::class)) {
            try {
                LaravelDb::listen(function (QueryExecuted $query) {
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

        if (class_exists(CommandExecuted::class)) {
            foreach (config('redis', []) as $key => $config) {
                if (strpos($key, 'redis-queue') !== false) {
                    continue;
                }
                try {
                    Redis::connection($key)->listen(function (CommandExecuted $command) {
                        foreach ($command->parameters as &$item) {
                            if (is_array($item)) {
                                $item = implode('\', \'', $item);
                            }
                        }
                        if ("Redis::get('ping')" === "Redis::{$command->command}('" . implode('\', \'', $command->parameters) . "')") {
                            return;
                        }
                        Statistic::redis($command->command, implode('\', \'', $command->parameters), $command->time, ['connection' => $command->connectionName]);
                    });
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
