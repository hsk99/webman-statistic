<?php

namespace Hsk99\WebmanStatistic;

use Webman\Bootstrap;
use think\facade\Db;
use Hsk99\WebmanStatistic\StatisticClient;

class ThinkOrm implements Bootstrap
{
    public static function start($worker)
    {
        if (!config('plugin.hsk99.statistic.app.sql_report', 'false')) {
            return;
        }

        if ($worker) {
            Db::listen(function ($sql, $runtime, $master) use ($worker) {
                if ($sql === 'select 1') {
                    return;
                }

                switch (true) {
                    case is_numeric($runtime):
                        $transfer = $sql;
                        $cost     = $runtime;
                        break;
                    case !is_numeric($runtime) && 'CONNECT' === substr($sql, 0, 7):
                        @preg_match("/UseTime:([0-9]+(\\.[0-9]+)?|[0-9]+(\\.[0-9]+))/", $sql, $result);
                        if (count($result) > 1) {
                            $transfer = substr($sql, strpos($sql, "s ] ") + 4);
                            $cost     = $result[1];
                        } else {
                            $transfer = $sql;;
                            $cost     = 0;
                        }
                        break;
                    default:
                        $transfer = $sql;;
                        $cost     = 0;
                        break;
                }
                StatisticClient::report('', config('plugin.hsk99.statistic.app.project') . 'Sql', '127.0.0.1', $transfer, true, 1, json_encode([
                    'sql'     => $sql,
                    'runtime' => $cost . 's',
                    'master'  => $master,
                    'worker'  => $worker->name,
                ], 320), $cost);
            });
        }
    }
}
