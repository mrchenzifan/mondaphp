<?php

// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// * Copyright 2014 The Herosphp Authors. All rights reserved.
// * Use of this source code is governed by a MIT-style license
// * that can be found in the LICENSE file.
// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
declare(strict_types=1);

namespace herosphp\core;

use herosphp\GF;
use Workerman\Protocols\Http\Request;

/**
 * web http request wrapper class
 *
 * @author RockYang<yangjian102621@gmail.com>
 */
class HttpRequest extends Request
{
    /**
     * @return bool
     */
    public function expectsJson(): bool
    {
        return ($this->isAjax() && ! $this->isPjax()) || $this->acceptJson();
    }

    /**
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * @return bool
     */
    public function isPjax(): bool
    {
        return (bool) $this->header('X-PJAX');
    }

    /**
     * @return bool
     */
    public function acceptJson(): bool
    {
        return str_contains($this->header('accept', ''), 'json');
    }

    /**
     * get real ip
     *
     * @param  bool  $safeMode
     * @return string
     */
    public function getRealIp(bool $safeMode = true): string
    {
        $remoteIp = $this->connection->getRemoteIp();
        if ($safeMode && ! GF::isIntranetIp($remoteIp)) {
            return $remoteIp;
        }

        return $this->header('client-ip', $this->header(
            'x-forwarded-for',
            $this->header('x-real-ip', $this->header(
                'x-client-ip',
                $this->header('via', $remoteIp)
            ))
        ));
    }

    // support XSS过滤
    public function getParameter(string $name, $default = null)
    {
        $params = array_merge($this->get(), $this->post());

        return isset($params[$name]) ? $this->filter($params[$name]) : $default;
    }

    // get all params
    public function all(): array
    {
        return array_merge($this->get(), $this->post());
    }

    // get only params by keys
    public function only(array $keys): array
    {
        $all = $this->all();
        $result = [];
        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $result[$key] = $all[$key];
            }
        }

        return $result;
    }

    /**
     * File
     *
     * @param  string|null  $name
     * @return null|UploadFile[]|UploadFile
     */
    public function file($name = null): array|UploadFile|null
    {
        $files = parent::file($name);
        if (null === $files) {
            return $name === null ? [] : null;
        }
        if ($name !== null) {
            // Multi files
            if (is_array(current($files))) {
                return $this->parseFiles($files);
            }

            return $this->parseFile($files);
        }
        $uploadFiles = [];
        foreach ($files as $name => $file) {
            // Multi files
            if (is_array(current($file))) {
                $uploadFiles[$name] = $this->parseFiles($file);
            } else {
                $uploadFiles[$name] = $this->parseFile($file);
            }
        }

        return $uploadFiles;
    }

    /**
     * ParseFile
     *
     * @param  array  $file
     * @return UploadFile
     */
    protected function parseFile(array $file): UploadFile
    {
        return new UploadFile($file['tmp_name'], $file['name'], $file['type'], $file['error']);
    }

    /**
     * ParseFiles
     *
     * @param  array  $files
     * @return array
     */
    protected function parseFiles(array $files): array
    {
        $uploadFiles = [];
        foreach ($files as $key => $file) {
            if (is_array(current($file))) {
                $uploadFiles[$key] = $this->parseFiles($file);
            } else {
                $uploadFiles[$key] = $this->parseFile($file);
            }
        }

        return $uploadFiles;
    }

    // filter value
    private function filter(mixed $value)
    {
        if (is_array($value)) {
            array_walk_recursive($value, function (&$item) {
                if (is_string($item)) {
                    $item = htmlspecialchars($item);
                }
            });
        } else if (is_string($value)) {
            $value = htmlspecialchars($value);
        }

        return $value;
    }
}
