<?php

namespace herosphp\plugins\crontab;

interface ICronTask
{
    /**
     * 任务调度
     *
     * @return string
     */
    public function run(): string;
}
