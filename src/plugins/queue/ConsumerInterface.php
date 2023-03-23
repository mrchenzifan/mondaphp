<?php

declare(strict_types=1);

namespace herosphp\plugins\queue;

interface ConsumerInterface
{
    public function consume(array $data): void;
}
