<?php

namespace Hsk99\WebmanStatistic;

class Statistic
{
    /**
     * @var string
     */
    public static $transfer = '';

    /**
     * @author HSK
     * @date 2022-06-23 15:10:07
     *
     * @return void
     */
    public static function report()
    {
        try {
            if (0 === strlen(static::$transfer)) {
                return;
            }

            \Hsk99\WebmanStatistic\Bootstrap::instance()->request(
                config('plugin.hsk99.statistic.app.address'),
                [
                    'method' => 'POST',
                    'version' => '1.1',
                    'headers' => [
                        'Connection'    => 'keep-alive',
                        'authorization' => config('plugin.hsk99.statistic.app.authorization', md5(date('Y')))
                    ],
                    'data' => [
                        'transfer' => static::$transfer
                    ],
                    'success' => function ($response) {
                    },
                    'error' => function ($exception) {
                    }
                ]
            );

            static::$transfer = '';
        } catch (\Throwable $th) {
        }
    }
}
