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
            static::listen($worker);

            // 延迟接管进程业务回调，执行监控
            \Workerman\Timer::add(1, function () use (&$worker) {
                static::monitor($worker);
            }, '', false);
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
                    $sql = vsprintf($sql, $query->bindings);
                    Statistic::sql($sql, $query->time, ['connection' => $query->connectionName]);
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
                        $parameters = array_map(function ($item) {
                            if (is_array($item)) {
                                return json_encode($item, 320);
                            }
                            return $item;
                        }, $command->parameters);
                        $parameters = implode('\', \'', $parameters);
                        if ('get' === $command->command && 'ping' === $parameters) {
                            return;
                        }
                        Statistic::redis($command->command, $parameters, $command->time, ['connection' => $command->connectionName]);
                    });
                } catch (\Throwable $e) {
                }
            }
        }
    }

    /**
     * 监控进程
     *
     * @author HSK
     * @date 2022-07-21 13:17:14
     *
     * @param \Workerman\Worker $worker
     *
     * @return void
     */
    protected static function monitor(\Workerman\Worker $worker)
    {
        // 接管所有进程 onWorkerStop 回调，上报缓存数据
        $oldWorkerStop = $worker->onWorkerStop;
        $worker->onWorkerStop = function ($worker) use (&$oldWorkerStop) {
            Statistic::report();

            try {
                if (is_callable($oldWorkerStop)) {
                    call_user_func($oldWorkerStop, $worker);
                }
            } catch (\Throwable $exception) {
            } catch (\Exception $exception) {
            } catch (\Error $exception) {
            } finally {
                if (isset($exception)) {
                    echo $exception . PHP_EOL;
                }
            }
        };

        // 接管自定义进程 onMessage 回调，监控其异常抛出
        if (config('server.listen') !== $worker->getSocketName()) {
            $oldMessage = $worker->onMessage;
            $worker->onMessage = function ($connection, $message) use (&$oldMessage) {
                try {
                    if (is_callable($oldMessage)) {
                        call_user_func($oldMessage, $connection, $message);
                    }
                } catch (\Throwable $exception) {
                } catch (\Exception $exception) {
                } catch (\Error $exception) {
                } finally {
                    if (isset($exception)) {
                        Statistic::exception($exception);
                        echo $exception . PHP_EOL;
                    }
                }
            };
        }
    }
}
