<?php

namespace herosphp\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Value
{
    public function __construct(public string $name, public mixed $default = null)
    {
    }
}
