<?php

declare(strict_types=1);

namespace herosphp\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class RequestVo
{
    public function __construct()
    {
    }
}
