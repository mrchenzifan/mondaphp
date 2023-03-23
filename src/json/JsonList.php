<?php

// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// * Copyright 2014 The Herosphp Authors. All rights reserved.
// * Use of this source code is governed by a MIT-style license
// * that can be found in the LICENSE file.
// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

declare(strict_types=1);

namespace herosphp\json;

use herosphp\utils\StringUtil;

/**
 * json data list
 * ----------------------------------------------------
 *
 * @author RockYang<yangjian102621@gmail.com>
 */
class JsonList implements Jsonable
{
    public int $total = 0;

    public int $page = 1;

    public int $pageSize = 10;

    public int $totalPage = 1;

    public array $extraData = [];

    public function __construct(public  int $code, public  string $message, public array $data)
    {
    }

    public static function create(int $code, string $message = '', array $data = []): self
    {
        return new self($code, $message, $data);
    }

    // page
    public function page(int $page, int $pageSize, int $total): self
    {
        $this->page = $page;
        $this->pageSize = $pageSize;
        $this->total = $total;
        $this->totalPage = 0 != $pageSize ? (int) ceil($total / $pageSize) : 0;

        return $this;
    }

    // array extra
    public function extra(array $extraData): self
    {
        $this->extraData = $extraData;

        return $this;
    }

    public function toJson(): string
    {
        return StringUtil::jsonEncode([
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
            'total' => $this->total,
            'page' => $this->page,
            'pageSize' => $this->pageSize,
            'extraData' => $this->extraData,
        ]);
    }
}
