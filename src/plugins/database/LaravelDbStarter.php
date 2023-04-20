<?php

declare(strict_types=1);

namespace herosphp\plugins\database;

use herosphp\GF;
use herosphp\WebApp;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;
use Throwable;
use Workerman\Timer;

/**
 * laravelDB启动器
 */
class LaravelDbStarter
{
    protected static string $pageName = 'page';

    protected static bool $autoPageResolver = true;

    /**
     * debug sql
     *
     * @var bool
     */
    protected static bool $debug = true;

    /**
     * 保持长链接
     *
     * @var bool
     */
    protected static bool $keepAlive = false;

    public static function init(): void
    {
        $connections = GF::config('database', []);
        if (! $connections) {
            return;
        }
        if (! class_exists(Capsule::class)) {
            return;
        }
        $capsule = new Capsule;
        foreach ($connections as $name => $config) {
            $capsule->addConnection($config, $name);

            // Heartbeat KeepAlive
            if (static::$keepAlive && $config['driver'] == 'mysql') {
                Timer::add(55, function () use ($capsule) {
                    try {
                        $capsule->getDatabaseManager()->select('select 1');
                    } catch (Throwable) {
                    }
                });
            }
        }

        if (\class_exists(Dispatcher::class)) {
            $capsule->setEventDispatcher(new Dispatcher(new Container));
        }
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        if (static::$debug) {
            Db::listen(function ($query) {
                $sql = $query->sql;
                $bindings = [];
                if ($query->bindings) {
                    foreach ($query->bindings as $v) {
                        if (is_numeric($v)) {
                            $bindings[] = $v;
                        } else {
                            $bindings[] = '"'.strval($v).'"';
                        }
                    }
                }
                $execute = Str::replaceArray('?', $bindings, $sql);
                printf("%s \033[36m\033[1m[SQL] \033[0m %s\n", date('Y-m-d H:i:s'), $execute);
            });
        }

        // auto page resolver
        if (static::$autoPageResolver && class_exists(Paginator::class)) {
            // Paginator
            if (method_exists(Paginator::class, 'queryStringResolver')) {
                Paginator::queryStringResolver(function () {
                    return WebApp::$request?->queryString();
                });
            }
            Paginator::currentPathResolver(function () {
                $request = WebApp::$request;

                return $request ? $request->path() : '/';
            });
            Paginator::currentPageResolver(function ($pageName = 'page') {
                $request = WebApp::$request;
                if (! $request) {
                    return 1;
                }
                $page = (int) ($request->getParameter($pageName, 1));

                return $page > 0 ? $page : 1;
            });
        }
    }
}
