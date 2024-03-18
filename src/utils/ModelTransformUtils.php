<?php

namespace herosphp\utils;

use herosphp\exception\HeroException;
use ReflectionClass;

/**
 * 模型转换工具
 * -----------------------------------------------
 *
 * @author RockYang<yangjian102621@gmail.com>
 */
class ModelTransformUtils
{
    /**
     * convert map(key => value array) to entity object
     *
     * @throws \ReflectionException
     */
    public static function map2model(string|object $class, array $map): object|null
    {
        if (is_string($class)) { // create object
            $refClass = new ReflectionClass($class);
            $obj = $refClass->newInstance();
        } else {
            $obj = $class;
            $refClass = new ReflectionClass($obj);
        }

        foreach ($map as $key => $val) {
            $methodName = 'set'.ucwords(StringUtil::ul2hump($key));
            if ($refClass->hasMethod($methodName)) {
                $method = $refClass->getMethod($methodName);
                try {
                    $method->invoke($obj, $val);
                } catch (\Exception $e) {
                    throw new HeroException($e->getMessage());
                }
            }
        }

        return $obj;
    }

    // convert model entity to map
    public static function model2map(object $model): array
    {
        $refClass = new ReflectionClass($model);
        $properties = $refClass->getProperties();
        $map = [];
        foreach ($properties as $value) {
            // 过滤尚未init var
            if (!$value->isInitialized($model)) {
                continue;
            }
            $property = $value->getName();
            if (strpos($property, '_')) {
                $property = StringUtil::ul2hump($property); //转换成驼锋格式
            }
            $method = 'get'.ucfirst($property);
            $map[$property] = $model->$method();
        }

        return $map;
    }

    /**
     * 多维数组过滤value 为 null
     *
     * @param  array  $arr
     * @return array
     */
    public static function filterNull(array $arr): array
    {
        $resultArr = [];
        if (count($arr) <= 0) {
            return [];
        }
        foreach ($arr as $k => $item) {
            $result = $item;
            if (is_array($result)) {
                $result = static::filterNull($item);
            }
            if (!is_null($result)) {
                $resultArr[$k] = $result;
            }
        }

        return $resultArr;
    }
}
