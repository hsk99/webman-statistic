<?php

namespace Hsk99\WebmanStatistic;

use Hsk99\WebmanStatistic\Bootstrap;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Utils\Query;

class Statistic
{
    /**
     * @var string
     */
    protected static $_transferCache = null;

    /**
     * 设置上报数据
     *
     * @author HSK
     * @date 2023-02-08 10:43:27
     *
     * @param string $transfer
     *
     * @return void
     */
    public static function setTransfer(string $transfer)
    {
        if (!config('plugin.hsk99.statistic.app.enable')) {
            return;
        }

        static::$_transferCache .= $transfer . "\n";

        if (strlen(static::$_transferCache) > 1024 * 1024) {
            static::report();
        }
    }

    /**
     * 数据上报
     * 
     * @author HSK
     * @date 2022-06-23 15:10:07
     *
     * @return void
     */
    public static function report()
    {
        try {
            if (!config('plugin.hsk99.statistic.app.enable')) {
                static::$_transferCache = null;
                return;
            }

            if (0 === strlen(static::$_transferCache)) {
                return;
            }

            Bootstrap::instance()->request(
                config('plugin.hsk99.statistic.app.address'),
                [
                    'method' => 'POST',
                    'version' => '1.1',
                    'headers' => [
                        'Connection'    => 'keep-alive',
                        'authorization' => config('plugin.hsk99.statistic.app.authorization', md5(date('Y')))
                    ],
                    'data' => [
                        'transfer' => static::$_transferCache
                    ],
                    'success' => function ($response) {
                    },
                    'error' => function ($exception) {
                    }
                ]
            );

            static::$_transferCache = null;
        } catch (\Throwable $th) {
            static::$_transferCache = null;
        }
    }

    /**
     * 异常信息
     *
     * @author HSK
     * @date 2022-07-21 16:23:09
     *
     * @param \Throwable|\Exception|\Error|string $exception
     * @param array $extra
     *
     * @return void
     */
    public static function exception($exception, $extra = [])
    {
        try {
            if (!config('plugin.hsk99.statistic.app.enable')) {
                return;
            }

            $time    = microtime(true);
            $details = [
                'time'      => date('Y-m-d H:i:s.', (int)$time) . substr($time, 11),
                'process'   => Bootstrap::$process,
                'exception' => (string)$exception
            ] + $extra;

            if (
                $exception instanceof \Throwable ||
                $exception instanceof \Exception ||
                $exception instanceof \Error
            ) {
                $code     = $exception->getCode();
                $transfer = Bootstrap::$process . ' ' . $exception->getMessage();
            } else {
                $code     = 500;
                $transfer = Bootstrap::$process . ' ' . md5($exception);
            }

            static::setTransfer(json_encode([
                'time'     => date('Y-m-d H:i:s.', (int)$time) . substr($time, 11),
                'project'  => config('plugin.hsk99.statistic.app.project') . "-Exception",
                'ip'       => '127.0.0.1',
                'transfer' => $transfer,
                'costTime' => 0,
                'success'  => 0,
                'code'     => $code,
                'details'  => json_encode($details, 320),
            ], 320));
        } catch (\Throwable $th) {
        }
    }

    /**
     * SQL信息
     *
     * @author HSK
     * @date 2022-07-22 15:43:51
     *
     * @param string $sql
     * @param float $runtime
     * @param array $extra
     *
     * @return void
     */
    public static function sql(string $sql, float $runtime, $extra = [])
    {
        try {
            if (!config('plugin.hsk99.statistic.app.enable')) {
                return;
            }

            $time    = microtime(true);
            $details = [
                'time'     => date('Y-m-d H:i:s.', (int)$time) . substr($time, 11),
                'process'  => Bootstrap::$process,
                'sql'      => $sql,
                'run_time' => $runtime  . " ms"
            ] + $extra;

            try {
                $parser = new Parser($sql);
                $flags = Query::getFlags($parser->statements[0]);
                $tables = Query::getTables($parser->statements[0]);

                if ('SHOW' === $flags['querytype']) {
                    $transfer = Bootstrap::$process . ' ' . $sql;
                } else {
                    $transfer = Bootstrap::$process . ' ' . $flags['querytype'] . " " . implode(",", $tables);
                }
            } catch (\Throwable $th) {
                $transfer = Bootstrap::$process . ' ' . md5($sql);
            }

            static::setTransfer(json_encode([
                'time'     => date('Y-m-d H:i:s.', (int)$time) . substr($time, 11),
                'project'  => config('plugin.hsk99.statistic.app.project') . "-SQL",
                'ip'       => '127.0.0.1',
                'transfer' => $transfer,
                'costTime' => $runtime / 1000,
                'success'  => 1,
                'code'     => 3306,
                'details'  => json_encode($details, 320),
            ], 320));
        } catch (\Throwable $th) {
        }
    }

    /**
     * Redis信息
     *
     * @author HSK
     * @date 2022-07-22 15:51:14
     *
     * @param string $command
     * @param string $parameters
     * @param float $runtime
     * @param array $extra
     *
     * @return void
     */
    public static function redis(string $command, string $parameters, float $runtime, $extra = [])
    {
        try {
            if (!config('plugin.hsk99.statistic.app.enable')) {
                return;
            }

            $time    = microtime(true);
            $details = [
                'time'     => date('Y-m-d H:i:s.', (int)$time) . substr($time, 11),
                'process'  => Bootstrap::$process,
                'command'  => "Redis::{$command}('" . $parameters . "')",
                'run_time' => $runtime . " ms"
            ] + $extra;

            static::setTransfer(json_encode([
                'time'     => date('Y-m-d H:i:s.', (int)$time) . substr($time, 11),
                'project'  => config('plugin.hsk99.statistic.app.project') . "-Redis",
                'ip'       => '127.0.0.1',
                'transfer' => Bootstrap::$process . ' ' . "Redis::{$command}",
                'costTime' => $runtime / 1000,
                'success'  => 1,
                'code'     => 6379,
                'details'  => json_encode($details, 320),
            ], 320));
        } catch (\Throwable $th) {
        }
    }
}
