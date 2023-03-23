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

/**
 * laravelDB启动器
 */
class LaravelDbStarter
{
    protected static string $pageName = 'page';

    protected static bool $autoPageResolver = false;

    protected static bool $debug = false;

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
            Paginator::queryStringResolver(fn () => WebApp::$request->queryString());
            Paginator::currentPathResolver(fn () => WebApp::$request->path());
            Paginator::currentPageResolver(function () {
                $page = (int) WebApp::$request->get(static::$pageName, 1);

                return $page > 0 ? $page : 1;
            });
        }
    }
}
