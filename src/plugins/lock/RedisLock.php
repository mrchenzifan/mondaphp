<?php

declare(strict_types=1);

namespace herosphp\plugins\lock;

use herosphp\utils\Redis;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\RedisStore;

class RedisLock
{
    protected array $config = [
        'ttl' => 300, // 默认锁超时时间
        'auto_release' => true, // 是否自动释放，建议设置为 true
        'prefix' => 'lock_', // 锁前缀
    ];

    protected string $key;

    /**
     * @param  string  $key
     * @param  array  $options
     */
    public function __construct(string $key, array $options = [])
    {
        $this->key = $key;
        $this->config = array_merge($this->config, $options);
    }

    /**
     * 创建锁
     *
     * @return LockInterface
     */
    public function createLock(): LockInterface
    {
        $redis = Redis::connection()->client();

        return LockFactory::getLockFactory(new RedisStore($redis))
            ->createLock(
                $this->config['prefix'].$this->key,
                $this->config['ttl'],
                $this->config['auto_release']
            );
    }
}
