<?php

// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// * Copyright 2014 The Herosphp Authors. All rights reserved.
// * Use of this source code is governed by a MIT-style license
// * that can be found in the LICENSE file.
// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

declare(strict_types=1);

namespace herosphp\core;

use herosphp\annotation\Inject;
use herosphp\annotation\Value;
use herosphp\exception\HeroException;
use herosphp\GF;
use ReflectionClass;
use ReflectionException;

/**
 * BeanContainer class
 *
 * @author RockYang<yangjian102621@gmail.com>
 */
class BeanContainer
{
    protected static array $_instances = [];

    // get beans
    public static function get(string $name): mixed
    {
        if (isset(static::$_instances[$name])) {
            return static::$_instances[$name];
        }

        return null;
    }

    // check bean is exist
    public static function exist(string $name): bool
    {
        return isset(static::$_instances[$name]);
    }

    // register a new instance
    public static function register(string $name, object $value): void
    {
        if (isset(static::$_instances[$name])) {
            return;
        }

        static::$_instances[$name] = $value;
    }

    // add or update a new object
    public static function put(string $name, object $value): void
    {
        static::$_instances[$name] = $value;
    }

    // create an instance with specified constructor args
    public static function make(string $name, array $constructor = []): mixed
    {
        if (! class_exists($name)) {
            throw new HeroException("Class '$name' not found");
        }
        if (isset(static::$_instances[$name])) {
            return static::$_instances[$name];
        }
        $value = new $name(...array_values($constructor));
        static::put($name, $value);

        return $value;
    }

    /**
     * build a instance with specified class path
     * auto-inject the properties and put it to bean container.
     *
     * @throws ReflectionException
     */
    public static function build(string $class): object
    {
        $obj = static::get($class);
        if ($obj != null) {
            return $obj;
        }

        $clazz = new ReflectionClass($class);
        $obj = $clazz->newInstance();

        // handler class properties
        static::handlerInjectAnnotation($obj, $clazz->getProperties());
        static::handlerValueAnnotation($obj, $clazz->getProperties());

        // register object to bean pool
        static::register($clazz->getName(), $obj);

        return $obj;
    }

    // handler class Inject Annotation properties

    /**
     * @throws ReflectionException
     */
    protected static function handlerInjectAnnotation(object $obj, array $reflectionProperties = []): void
    {
        // scan Inject getProperties
        foreach ($reflectionProperties as $property) {
            $_attrs = $property->getAttributes(Inject::class);
            if (! $_attrs) {
                continue;
            }
            // find property class name
            $_attr = $_attrs[0];
            $name = $property->getType()?->getName();

            // unSuggest use strict_types
            if (is_null($name)) {
                $_args = $_attr->getArguments();
                if (! empty($_args)) {
                    $name = array_shift($_args);
                }
            }
            if (empty($name)) {
                throw new HeroException('Inject annotation must have a name or type');
            }
            // set property accessibility
            $property->setValue($obj, static::build($name));
        }
    }

    // handler class Value Annotation properties
    protected static function handlerValueAnnotation(object $obj, array $reflectionProperties = []): void
    {
        // scan Config getProperties
        foreach ($reflectionProperties as $property) {
            $_attrs = $property->getAttributes(Value::class);
            if (! $_attrs) {
                continue;
            }
            // find property config name
            $_attr = $_attrs[0];
            if (! isset($_attr->getArguments()['name']) || ! $_attr->getArguments()['name']) {
                continue;
            }
            $value = GF::config($_attr->getArguments()['name'], null);
            if ($property->hasType()) {
                $propertyType = $property->getType()->getName();
                if ($propertyType === 'int' && ! is_int($value)) {
                    continue;
                }
                if ($propertyType === 'string' && ! is_string($value)) {
                    continue;
                }
                if ($propertyType === 'double' && ! is_float($value)) {
                    continue;
                }
                if ($propertyType === 'float' && ! is_float($value)) {
                    continue;
                }
                if ($propertyType === 'array' && ! is_array($value)) {
                    continue;
                }
                if ($propertyType === 'boolean' && ! is_bool($value)) {
                    continue;
                }
            }
            $property->setValue($obj, $value);
        }
    }
}
