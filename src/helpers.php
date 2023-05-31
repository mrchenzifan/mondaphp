<?php

declare(strict_types=1);

use herosphp\core\HttpResponse;
use herosphp\GF;
use herosphp\json\Jsonable;

/**
 * read environment file
 */
if (! function_exists('h_env')) {
    function h_env(string $key, $default = null)
    {
        global $env;

        return $env->get($key, $default);
    }
}

/**
 * make password by bcrypt algorithm
 */
if (! function_exists('make_password')) {
    function make_password(string $value): string
    {
        return password_hash($value, PASSWORD_BCRYPT);
    }
}

/**
 * check password
 */
if (! function_exists('check_password')) {
    function check_password(string $value, string $hashedValue): bool
    {
        return password_verify($value, $hashedValue);
    }
}

/**
 * json response
 */
if (! function_exists('json')) {
    function json(array|Jsonable $data): HttpResponse
    {
        return GF::response(body: $data);
    }
}

/**
 * Jsonp response
 *
 * @param $data
 * @param  string  $callbackName
 * @return HttpResponse
 */
if (! function_exists('jsonp')) {
    function jsonp($data, string $callbackName = 'callback'): HttpResponse
    {
        if (! is_scalar($data) && null !== $data) {
            $data = json_encode($data);
        }

        return GF::response(200, [], "$callbackName($data)");
    }
}

/**
 * Redirect response
 *
 * @param  string  $location
 * @param  int  $status
 * @param  array  $headers
 * @return HttpResponse
 */
if (! function_exists('redirect')) {
    function redirect(string $location, int $status = 302, array $headers = []): HttpResponse
    {
        return GF::redirect($location, $status, $headers);
    }
}

/**
 * App path
 *
 * @param  string  $path
 * @return string
 */
if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return path_combine(APP_PATH, $path);
    }
}
/**
 * Public path
 *
 * @param  string  $path
 * @return string
 */
if (! function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return path_combine(PUBLIC_PATH, $path);
    }
}

/**
 * Config path
 *
 * @param  string  $path
 * @return string
 */
if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return path_combine(CONFIG_PATH, $path);
    }
}
/**
 * Runtime path
 *
 * @param  string  $path
 * @return string
 */
if (! function_exists('runtime_path')) {
    function runtime_path(string $path = ''): string
    {
        return path_combine(RUNTIME_PATH, $path);
    }
}

/**
 * Generate paths based on given information
 *
 * @param  string  $front
 * @param  string  $back
 * @return string
 */
if (! function_exists('path_combine')) {
    function path_combine(string $front, string $back): string
    {
        return $front.($back ? (ltrim($back, DIRECTORY_SEPARATOR)) : $back);
    }
}

/**
 * Get config
 */
if (! function_exists('config')) {
    function config(?string $key = null, $default = null)
    {
        return GF::config($key, $default);
    }
}
