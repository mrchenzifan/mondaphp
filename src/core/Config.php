<?php

// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// * Copyright 2014 The Herosphp Authors. All rights reserved.
// * Use of this source code is governed by a MIT-style license
// * that can be found in the LICENSE file.
// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

declare(strict_types=1);

namespace herosphp\core;

/**
 * Config file parser tool class
 * ----------------------------------------------
 *
 * @author RockYang<yangjian102621@gmail.com>
 */
class Config
{
    /**
     * @var array 配置文件
     */
    private static array $config = [];

    /**
     * 加载配置文件.
     */
    public static function load(string $configPath, array $excludeFile = []): void
    {
        $handler = opendir($configPath);
        while (($filename = readdir($handler)) !== false) {
            if ('.' != $filename && '..' != $filename) {
                $basename = basename($filename, '.config.php');
                if (in_array($basename, $excludeFile)) {
                    continue;
                }
                self::$config[$basename] = require_once $configPath.'/'.$filename;
            }
        }
        closedir($handler);
    }

    /**
     * 获取配置文件.
     *
     * @param  null  $key     键
     * @param  null  $default 默认值
     * @return mixed
     */
    public static function get($key = null, $default = null): mixed
    {
        if (null === $key) {
            return self::$config;
        }
        $key_array = explode('.', $key);
        $value = self::$config;
        foreach ($key_array as $index) {
            if (! isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }

        return $value;
    }

    /**
     * 重新加载.
     *
     * @param $configPath
     * @param  array  $excludeFile
     */
    public static function reload($configPath, array $excludeFile = []): void
    {
        self::$config = [];
        self::load($configPath, $excludeFile);
    }
}
