<?php

namespace Hsk99\WebmanStatistic;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use Hsk99\WebmanStatistic\StatisticClient;

class Statistic implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $ip         = $request->getRealIp($safe_mode = true);
        $controller = $request->controller;
        $action     = $request->action;
        $transfer   = $controller . '::' . $action;
        $project    = config('plugin.hsk99.statistic.app.project');

        $unique = StatisticClient::tick($project, $ip, $transfer);

        $response = $next($request);

        $code    = $response->getStatusCode();
        $success = $code < 400;
        $details = [
            'ip'              => $request->getRealIp($safe_mode = true) ?? '',   // 请求客户端IP
            'url'             => $request->fullUrl() ?? '',                      // 请求URL
            'method'          => $request->method() ?? '',                       // 请求方法
            'request_param'   => $request->all() ?? [],                          // 请求参数
            'request_header'  => $request->header() ?? [],                       // 请求头
            'cookie'          => $request->cookie() ?? [],                       // 请求cookie
            'session'         => $request->session()->all() ?? [],               // 请求session
            'response_code'   => $response->getStatusCode() ?? '',               // 响应码
            'response_header' => $response->getHeaders() ?? [],                  // 响应头
            'response_body'   => $success ?: (string)$response->rawBody(),       // 响应数据（发生异常）
        ];
        StatisticClient::report($unique, $project, $ip, $transfer, $success, $code, json_encode($details, 320));

        return $response;
    }
}
