<?php

declare(strict_types=1);

namespace herosphp\plugins\validate;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Valid
{
    public function __construct(public string $class, public string $scene)
    {
    }
}
