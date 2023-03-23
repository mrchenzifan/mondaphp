<?php

namespace herosphp\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class RequestParam
{
    public function __construct(public string $name, public string $default = '')
    {
    }
}
