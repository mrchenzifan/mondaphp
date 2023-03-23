<?php

// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// * Copyright 2014 The Herosphp Authors. All rights reserved.
// * Use of this source code is governed by a MIT-style license
// * that can be found in the LICENSE file.
// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

declare(strict_types=1);

namespace herosphp\json;

use herosphp\utils\StringUtil;

class JsonOne implements Jsonable
{
    public function __construct(public int $code, public string $message, public array $data = [])
    {
    }

    public static function create(int $code, string $message, array $data = []): self
    {
        return new self($code, $message, $data);
    }

    public function toJson(): string
    {
        if (! $this->data) {
            // only return code and message,like WeChat
            return StringUtil::jsonEncode([
                'code' => $this->code,
                'message' => $this->message,
            ]);
        }

        return StringUtil::jsonEncode([
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
        ]);
    }
}
