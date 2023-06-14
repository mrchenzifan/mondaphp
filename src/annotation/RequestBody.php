<?php

namespace herosphp\annotation;

use Attribute;

/**
 * An annotation parser for post requests
 *
 * @Note: class is default ,parameter type is string
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class RequestBody
{
    public function __construct(public string $class = '')
    {

    }
}
