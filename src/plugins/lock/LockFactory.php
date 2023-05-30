<?php

namespace herosphp\plugins\lock;

use Symfony\Component\Lock\LockFactory as Factory;
use Symfony\Component\Lock\PersistingStoreInterface;

class LockFactory
{
    private static ?Factory $factory = null;

    /**
     * @param  PersistingStoreInterface  $store
     * @return Factory|null
     */
    public static function getLockFactory(PersistingStoreInterface $store): ?Factory
    {
        if (static::$factory === null) {
            static::$factory = new Factory($store);
        }

        return static::$factory;
    }
}
