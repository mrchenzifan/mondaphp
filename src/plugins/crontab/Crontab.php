<?php

namespace herosphp\plugins\crontab;

use Workerman\Timer;

/**
 * Class Crontab
 */
class Crontab
{
    /**
     * @var string
     */
    protected string $_rule;

    /**
     * @var callable
     */
    protected $_callback;

    /**
     * @var string
     */
    protected string $_name;

    /**
     * @var int
     */
    protected int $_id;

    /**
     * @var array
     */
    protected static array $_instances = [];

    /**
     * Crontab constructor.
     *
     * @param  string  $rule
     * @param  callable  $callback
     * @param  string  $name
     */
    public function __construct(string $rule, callable $callback, string $name = '')
    {
        $this->_rule = $rule;
        $this->_callback = $callback;
        $this->_name = $name;
        $this->_id = static::createId();
        static::$_instances[$this->_id] = $this;
        static::tryInit();
    }

    /**
     * @return string
     */
    public function getRule(): string
    {
        return $this->_rule;
    }

    /**
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->_callback;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->_name;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->_id;
    }

    /**
     * @return bool
     */
    public function destroy(): bool
    {
        return static::remove($this->_id);
    }

    /**
     * @return array
     */
    public static function getAll(): array
    {
        return static::$_instances;
    }

    /**
     * @param $id
     * @return bool
     */
    public static function remove($id): bool
    {
        if ($id instanceof self) {
            $id = $id->getId();
        }
        if (! isset(static::$_instances[$id])) {
            return false;
        }
        unset(static::$_instances[$id]);

        return true;
    }

    /**
     * @return int
     */
    protected static function createId(): int
    {
        static $id = 0;

        return ++$id;
    }

    /**
     * tryInit
     */
    protected static function tryInit(): void
    {
        static $init = false;
        if ($init) {
            return;
        }
        $init = true;
        $callback = function () use (&$callback) {
            $parser = new Parser;
            foreach (static::$_instances as $crontab) {
                $rule = $crontab->getRule();
                $cb = $crontab->getCallback();
                if (! $cb || ! $rule) {
                    continue;
                }
                $times = $parser->parse($rule);
                $now = time();
                foreach ($times as $time) {
                    $t = $time - $now;
                    if ($t <= 0) {
                        $t = 0.000001;
                    }
                    Timer::add($t, $cb, null, false);
                }
            }
            Timer::add(60 - time() % 60, $callback, null, false);
        };

        $nextTime = time() % 60;
        if ($nextTime == 0) {
            $nextTime = 0.00001;
        } else {
            $nextTime = 60 - $nextTime;
        }
        Timer::add($nextTime, $callback, null, false);
    }
}
