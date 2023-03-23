<?php

// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// * Copyright 2014 The Herosphp Authors. All rights reserved.
// * Use of this source code is governed by a MIT-style license
// * that can be found in the LICENSE file.
// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

declare(strict_types=1);

namespace herosphp;

use FastRoute\Dispatcher;
use herosphp\annotation\AnnotationParser;
use herosphp\annotation\RequestBody;
use herosphp\annotation\RequestParam;
use herosphp\annotation\RequestPath;
use herosphp\core\BeanContainer;
use herosphp\core\HttpRequest;
use herosphp\core\HttpResponse;
use herosphp\core\Router;
use herosphp\exception\BaseExceptionHandler;
use herosphp\exception\RouterException;
use herosphp\utils\Logger;
use herosphp\utils\StringUtil;
use ReflectionMethod;
use ReflectionParameter;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use Workerman\Worker;

require __DIR__.DIRECTORY_SEPARATOR.'constants.php';

/**
 * WebApp main program
 *
 * @author RockYang<yangjian102621@gmail.com>
 */
class WebApp
{
    public static HttpRequest $request;

    public static TcpConnection $connection;

    // request controller
    public static ?object $targetController = null;

    // request controller method
    public static ?string $targetMethod = null;

    // app config
    protected static array $_config = [
        'listen' => 'http://0.0.0.0:2345',
        'transport' => 'tcp',
        'name' => 'herosphp',
        'context' => [],
        'count' => 4,
        'reloadable' => true,
        'reusePort' => true,
        'user' => '',
        'group' => '',
        'event_loop' => '',
        'pid_file' => RUNTIME_PATH.'herosphp.pid',
        'status_file' => RUNTIME_PATH.'herosphp.status',
        'stdout_file' => RUNTIME_PATH.'logs/herosphp-stdout.log',
        'log_file' => RUNTIME_PATH.'logs/herosphp.log',
        'max_package_size' => 10 * 1024 * 1024,
    ];

    private static Dispatcher $_dispatcher;

    public static function run(): void
    {
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            GF::printWarning('herosphp require PHP > 8.1.0 !');
            exit(0);
        }

        // loading app configs
        $config = GF::config('app');
        if (isset($config['server'])) {
            static::$_config = array_merge(static::$_config, $config['server']);
        }
        // set timezone
        date_default_timezone_set($config['timezone'] ?? 'Asia/Shanghai');
        // set error report level
        error_reporting($config['error_reporting'] ?? E_ALL);
        static::startServer();
    }

    /** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */
    public static function startServer(): void
    {
        Worker::$pidFile = static::$_config['pid_file'];
        Worker::$stdoutFile = static::$_config['stdout_file'];
        Worker::$logFile = static::$_config['log_file'];
        Worker::$eventLoopClass = static::$_config['event_loop'] ?? '';
        TcpConnection::$defaultMaxPackageSize = $config['max_package_size'] ?? 10 * 1024 * 1024;
        if (property_exists(Worker::class, 'statusFile')) {
            Worker::$statusFile = $config['status_file'] ?? '';
        }
        Worker::$onMasterReload = static function () {
            if (function_exists('opcache_get_status') && function_exists('opcache_invalidate')) {
                if ($status = opcache_get_status()) {
                    if (isset($status['scripts']) && $scripts = $status['scripts']) {
                        foreach (array_keys($scripts) as $file) {
                            opcache_invalidate($file, true);
                        }
                    }
                }
            }
        };
        // create a http worker
        $worker = new Worker(static::$_config['listen'], static::$_config['context']);
        $propertyMap = [
            'name',
            'count',
            'user',
            'group',
            'reusePort',
            'transport',
        ];
        foreach ($propertyMap as $property) {
            if (isset(static::$_config[$property])) {
                $worker->$property = static::$_config[$property];
            }
        }

        $worker->onWorkerStart = static function ($w) {
            static::onWorkerStart();
        };

        // http request
        Http::requestClass(HttpRequest::class);
        $worker->onMessage = static function (TcpConnection $connection, HttpRequest $request) {
            static::onMessage($connection, $request);
        };
    }

    /**
     * @throws \ReflectionException
     */
    public static function onWorkerStart(): void
    {
        // scan the class file and init the router info
        AnnotationParser::run(APP_PATH, 'app\\');
        static::$_dispatcher = Router::getDispatcher();
    }

    public static function onMessage(TcpConnection $connection, HttpRequest $request): void
    {
        try {
            $routeInfo = static::$_dispatcher->dispatch($request->method(), $request->path());
            static::$request = $request;
            static::$connection = $connection;
            switch ($routeInfo[0]) {
                // find public file
                case Dispatcher::NOT_FOUND:
                    $file = static::getPublicFile($request->path());
                    if ($file === '') {
                        $connection->send(GF::response(code: 404, body: 'Page not found.'));
                    } else {
                        if (static::notModifiedSince($file)) {
                            $connection->send((new HttpResponse(304)));
                        } else {
                            $connection->send((new HttpResponse())->withFile($file));
                        }
                    }
                    break;
                case Dispatcher::METHOD_NOT_ALLOWED:
                    $connection->send(GF::response(code: 405, body: 'Method not allowed.'));
                    break;
                case Dispatcher::FOUND:
                    $handler = $routeInfo[1];
                    $vars = $routeInfo[2];

                    // sort middlewares
                    $middlewares = static::sortMiddlewares($handler['obj']);
                    // target class
                    static::$targetController = $handler['obj'];
                    static::$targetMethod = $handler['method'];
                    $callback = GF::pipeline($middlewares, static function ($request) use ($handler, $vars) {
                        $params = static::matchRequestParams($vars);
                        if (method_exists($handler['obj'], '__init')) {
                            $handler['obj']->__init();
                        }

                        return call_user_func_array([$handler['obj'], $handler['method']], $params);
                    });

                    $connection->send(GF::response(body: $callback($request)));
                    break;
                default:
                    throw new RouterException("router parse error for {$request->path()}");
            }

            // catch and handle the exception
        } catch (Throwable $e) {
            $connection->send(GF::response(body: static::exceptionResponse($e, $request)));
        }
    }

    /**
     * @param  array  $pathParams
     * @return array
     *
     * @throws \ReflectionException
     */
    public static function matchRequestParams(array $pathParams): array
    {
        $params = [];
        $reflectionMethod = new ReflectionMethod(static::$targetController, static::$targetMethod);
        /** @var ReflectionParameter $parameter */
        foreach ($reflectionMethod->getParameters() ?? [] as $parameter) {
            // find param has annotation
            $attributes = $parameter->getAttributes();
            if ($attributes) {
                $attr = $attributes[0];
                $params[] = match ($attr->getName()) {
                    //name required
                    RequestPath::class => $pathParams[$attr->getArguments()['name']],
                    RequestParam::class => static::$request->getParameter($attr->getArguments()['name'], $attr->getArguments()['default'] ?? ''),
                    RequestBody::class => StringUtil::jsonEncode(static::$request->post()),
                    default => null,
                };
            } else {
                $params[] = match ($parameter->getType()?->getName()) {
                    HttpRequest::class => static::$request,
                    TcpConnection::class => static::$connection,
                    HttpResponse::class => new HttpResponse(),
                    Http\Session::class => static::$request->session(),
                    default => null,
                };
            }
        }

        return $params;
    }

    // get the path for public static files
    public static function getPublicFile(string $path): string
    {
        $file = \realpath(PUBLIC_PATH.$path);
        if (! $file) {
            return '';
        }
        if (! str_starts_with($file, PUBLIC_PATH)) {
            return '';
        }
        if (false === \is_file($file)) {
            return '';
        }

        return $file;
    }

    /**
     * @param  string  $file
     * @return bool
     */
    public static function notModifiedSince(string $file): bool
    {
        $ifModifiedSince = self::$request->header('if-modified-since');
        if ($ifModifiedSince === null || ! ($mtime = \filemtime($file))) {
            return false;
        }

        return $ifModifiedSince === \gmdate('D, d M Y H:i:s', $mtime).' GMT';
    }

    /**
     * @param $controllerObj
     * @return array
     */
    protected static function sortMiddlewares($controllerObj): array
    {
        $middlewares = GF::config('middleware', []);
        if (property_exists($controllerObj, 'middlewares')) {
            $middlewares = array_merge($middlewares, $controllerObj->middlewares);
        }

        return $middlewares;
    }

    /**
     * 统一异常处理
     *
     * @param  Throwable  $e
     * @param  HttpRequest  $request
     * @return HttpResponse
     */
    protected static function exceptionResponse(Throwable $e, HttpRequest $request): mixed
    {
        try {
            //check exception exist
            if (BeanContainer::exist('app\\exception\\ExceptionHandler')) {
                $exceptionHandler = BeanContainer::get('app\\exception\\ExceptionHandler');
            } else {
                $exceptionHandler = BeanContainer::make(BaseExceptionHandler::class);
            }
            $exceptionHandler->report($e);

            return $exceptionHandler->render($request, $e);
        } catch (Throwable $e) {
            if (GF::getAppConfig('debug')) {
                Logger::error($e->getMessage());
            }

            return GF::response(code: 500, body: 'Oops, it seems something went wrong.');
        }
    }
}
