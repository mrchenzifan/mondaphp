<?php

namespace herosphp\core;

use Workerman\Worker;

interface Bootstrap
{
    /**
     * onWorkerStart
     *
     * @param  Worker|null  $worker
     * @return void
     */
    public static function start(?Worker $worker): void;
}
