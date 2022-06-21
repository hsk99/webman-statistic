<?php

namespace Hsk99\WebmanStatistic;

class Bootstrap implements \Webman\Bootstrap
{
    /**
     * @var \Workerman\Http\Client
     */
    protected static $_instance = null;

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
            $options = [
                'max_conn_per_addr' => 128, // 每个地址最多维持多少并发连接
                'keepalive_timeout' => 15,  // 连接多长时间不通讯就关闭
                'connect_timeout'   => 30,  // 连接超时时间
                'timeout'           => 30,  // 等待响应的超时时间
            ];
            self::$_instance = new \Workerman\Http\Client($options);
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
}
