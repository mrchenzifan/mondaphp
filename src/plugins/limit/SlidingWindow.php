<?php

declare(strict_types=1);

namespace herosphp\plugins\limit;

use herosphp\utils\Redis;

/**
 * 轻量级
 */
class SlidingWindow
{
    /**
     * @param  string  $userId  用户id
     * @param  string  $action  行为
     * @param  int  $period  滑动窗口宽度(秒)
     * @param  int  $maxCount  限制次数
     * @return bool true 表示未限制 false 表示限制
     */
    public static function limit(string $userId, string $action, int $period = 1, int $maxCount = 2): bool
    {
        $key = "hist:{$userId}:{$action}";
        $time = time();
        // 记录行为
        Redis::zadd($key, $time, md5(microtime()));
        // 移除掉窗口外的
        Redis::zremrangebyscore($key, 0, $time - $period);
        // 设置过期时间，防止冷用户持续占用内存
        // 过期时间应该是时间窗口长度+1s
        Redis::expire($key, $period + 1);
        // 取窗口行为数
        return Redis::zcard($key) <= $maxCount;
    }
}
