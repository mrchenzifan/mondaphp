<?php

declare(strict_types=1);

namespace herosphp\plugins\event;

use herosphp\GF;

class EventStarter
{
    protected static bool $debug = false;

    /**
     * @var array
     */
    protected static array $events = [];

    /**
     * @throws \ReflectionException
     */
    public static function init(): void
    {
        $config = GF::config('event', []);
        if (! $config) {
            return;
        }
        static::getEvents($config);
    }

    /**
     * @throws \ReflectionException
     */
    protected static function convertCallable($callback)
    {
        if (\is_array($callback)) {
            $callback = \array_values($callback);
            if (isset($callback[1]) && \is_string($callback[0]) && \class_exists($callback[0])) {
                $rm = new \ReflectionMethod($callback[0], $callback[1]);
                if ($rm->isStatic()) {
                    $callback = [$callback[0], $callback[1]];
                } else {
                    $callback = [(new \ReflectionClass($callback[0]))->newInstance(), $callback[1]];
                }
            }
        }

        return $callback;
    }

    /**
     * @param  array  $configs
     * @return void
     *
     * @throws \ReflectionException
     */
    protected static function getEvents(array $configs): void
    {
        $events = [];
        foreach ($configs as $eventName => $callbacks) {
            $callbacks = static::convertCallable($callbacks);
            if (is_callable($callbacks)) {
                $events[$eventName] = [$callbacks];
                Event::on($eventName, $callbacks);
                continue;
            }
            if (! is_array($callbacks)) {
                $msg = "Events: $eventName => ".var_export($callbacks, true)." is not callable\n";
                echo $msg;
                continue;
            }
            foreach ($callbacks as $callback) {
                $callback = static::convertCallable($callback);
                if (is_callable($callback)) {
                    $events[$eventName][] = $callback;
                    Event::on($eventName, $callback);
                    continue;
                }
                $msg = "Events: $eventName => ".var_export($callback, true)." is not callable\n";
                echo $msg;
            }
        }
        static::$events = array_merge_recursive(static::$events, $events);
    }
}
