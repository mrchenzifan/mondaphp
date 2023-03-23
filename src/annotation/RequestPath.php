<?php

namespace herosphp\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class RequestPath
{
    public function __construct(public string $name)
    {
    }
}
