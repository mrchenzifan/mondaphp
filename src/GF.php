<?php

// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// * Copyright 2014 The Herosphp Authors. All rights reserved.
// * Use of this source code is governed by a MIT-style license
// * that can be found in the LICENSE file.
// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

declare(strict_types=1);

namespace herosphp;

/**
 * 框架公共的常用的全局函数
 * ---------------------------------------------------------------------
 *
 * @author RockYang<yangjian102621@gmail.com>
 */

use function filter_var;
use herosphp\core\BeanContainer;
use herosphp\core\Config;
use herosphp\core\HttpResponse;
use herosphp\json\Jsonable;
use herosphp\utils\StringUtil;
use Workerman\Protocols\Http\Session;
use Workerman\Worker;

class GF
{
    // get var_dump output and return it as a string
    public static function exportVar(): string
    {
        $_args = func_get_args();
        if (count($_args) === 0) {
            return '';
        }

        $output = [];
        foreach ($_args as $val) {
            $output[] = print_r($val, true);
        }

        return implode(',', $output);
    }

    // 终端高亮打印青色
    public static function printInfo(string $message): void
    {
        printf("\033[36m\033[1m%s\033[0m\n", $message);
    }

    // 终端高亮打印绿色
    public static function printSuccess(string $message): void
    {
        printf("\033[32m\033[1m%s\033[0m\n", $message);
    }

    // 终端高亮打印红色
    public static function printError(string $message): void
    {
        printf("\033[31m\033[1m%s\033[0m\n", $message);
    }

    // 终端高亮打印黄色
    public static function printWarning(string $message): void
    {
        printf("\033[33m\033[1m%s\033[0m\n", $message);
    }

    // get current time
    public static function timer(): float
    {
        [$msec, $sec] = explode(' ', microtime());

        return (float) $msec + (float) $sec;
    }

    // get app config
    public static function getAppConfig(string $key)
    {
        return static::config("app.{$key}");
    }

    public static function config($key = null, $default = null)
    {
        return Config::get($key, $default);
    }

    // process run

    /** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */
    public static function processRun(string $processName, array $config = []): void
    {
        $listen = $config['listen'] ?? null;
        $content = $config['content'] ?? [];
        $worker = new Worker($listen, $content);
        $propertyMap = [
            'count',
            'user',
            'group',
            'reloadable',
            'reusePort',
            'transport',
            'protocol',
        ];
        $worker->name = $processName;
        foreach ($propertyMap as $property) {
            if (isset($config[$property])) {
                $worker->$property = $config[$property];
            }
        }
        $worker->onWorkerStart = function ($worker) use ($config) {
            if (isset($config['handler'])) {
                if (! class_exists($config['handler'])) {
                    echo "process error: class {$config['handler']} not exists\r\n";

                    return;
                }
                $instance = BeanContainer::make($config['handler'], $config['constructor'] ?? []);
                static::workerBind($worker, $instance);
            }
        };
    }

    // process bind callback
    public static function workerBind(Worker $worker, $class): void
    {
        $callbackMap = [
            'onConnect',
            'onMessage',
            'onClose',
            'onError',
            'onBufferFull',
            'onBufferDrain',
            'onWorkerStop',
            'onWebSocketConnect',
        ];
        foreach ($callbackMap as $name) {
            if (method_exists($class, $name)) {
                $worker->$name = [$class, $name];
            }
        }
        if (method_exists($class, 'onWorkerStart')) {
            call_user_func([$class, 'onWorkerStart'], $worker);
        }
    }

    /**
     * middlewares pipeline
     */
    public static function pipeline(array $classes, callable $initial): callable
    {
        return array_reduce(array_reverse($classes), function ($res, $currClass) {
            return function ($request) use ($res, $currClass) {
                return (new $currClass)->process($request, $res);
            };
        }, $initial);
    }

    /**
     * @param  string  $ip
     * @return bool
     */
    public static function isIntranetIp(string $ip): bool
    {
        // Not validate ip .
        if (! filter_var($ip, \FILTER_VALIDATE_IP)) {
            return false;
        }
        // Is intranet ip ? For IPv4, the result of false may not be accurate, so we need to check it manually later .
        if (! filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
        // Manual check only for IPv4 .
        if (! filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return false;
        }
        // Manual check .
        $reserved_ips = [
            1681915904 => 1686110207, // 100.64.0.0 -  100.127.255.255
            3221225472 => 3221225727, // 192.0.0.0 - 192.0.0.255
            3221225984 => 3221226239, // 192.0.2.0 - 192.0.2.255
            3227017984 => 3227018239, // 192.88.99.0 - 192.88.99.255
            3323068416 => 3323199487, // 198.18.0.0 - 198.19.255.255
            3325256704 => 3325256959, // 198.51.100.0 - 198.51.100.255
            3405803776 => 3405804031, // 203.0.113.0 - 203.0.113.255
            3758096384 => 4026531839, // 224.0.0.0 - 239.255.255.255
        ];
        $ip_long = \ip2long($ip);
        foreach ($reserved_ips as $ip_start => $ip_end) {
            if (($ip_long >= $ip_start) && ($ip_long <= $ip_end)) {
                return true;
            }
        }

        return false;
    }

    // create a new HttpResponse
    public static function response(int $code = 200, array $headers = [], mixed $body = ''): HttpResponse
    {
        if ($body instanceof HttpResponse) {
            return $body;
        }
        // jsonAble object
        if ($body instanceof Jsonable) {
            $headers['Content-Type'] = 'application/json';
            $body = $body->toJson();
        } elseif (is_array($body) || is_object($body)) {
            $headers['Content-Type'] = 'application/json';
            $body = StringUtil::jsonEncode($body);
        }

        return new HttpResponse($code, $headers, $body);
    }

    // create redirect
    public static function redirect(string $url, int $code = 301): HttpResponse
    {
        $headers = [];
        $headers['Location'] = $url;

        return new HttpResponse($code, $headers);
    }

    // cpu count
    public static function cpuCount(): int
    {
        // Windows does not support the number of processes setting.
        if (str_contains(PHP_OS, 'WINNT') === false) {
            return 1;
        }
        $count = 4;
        if (\is_callable('shell_exec')) {
            if (\strtolower(PHP_OS) === 'darwin') {
                $count = (int) \shell_exec('sysctl -n machdep.cpu.core_count');
            } else {
                $count = (int) \shell_exec('nproc');
            }
        }

        return $count > 0 ? $count : 4;
    }

    /**
     * 获取当前请求
     *
     * @return core\HttpRequest
     */
    public static function getRequest(): core\HttpRequest
    {
        return WebApp::$request;
    }

    /**
     * @return bool|Session
     */
    public static function session(): bool|Session
    {
        return WebApp::$request->session();
    }
}
