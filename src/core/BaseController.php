<?php

// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// * Copyright 2014 The Herosphp Authors. All rights reserved.
// * Use of this source code is governed by a MIT-style license
// * that can be found in the LICENSE file.
// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

declare(strict_types=1);

/**
 * 控制器抽象基类, 所有的控制器类都必须继承此类。
 * 每个操作对应一个 public 方法。
 * ---------------------------------------------------------------------
 *
 * @author yangjian<yangjian102621@gmail.com>
 */

namespace herosphp\core;

use herosphp\GF;
use herosphp\json\Jsonable;

abstract class BaseController
{
    // controller middlewares
    public array $middlewares = [];

    protected Template $template;

    public function __init(): void
    {
        $this->template = new Template;
    }

    // assign vars to template
    public function assign(string $key, mixed $value): void
    {
        $this->template->assign($key, $value);
    }

    // return a html response
    public function html(string $template, array $data = []): HttpResponse
    {
        $html = $this->template->getExecutedHtml($template, $data);

        return GF::response(headers: ['Content-Type' => 'text/html'], body: $html);
    }

    // return a JSON content type response
    public function json(array|Jsonable $data): HttpResponse
    {
        return GF::response(body: $data);
    }

    // file stream output
    public function file(string $filename): HttpResponse
    {
        return GF::response()->file($filename);
    }

    // file download
    public function download(string $filename, $downloadName): HttpResponse
    {
        return GF::response()->download($filename, $downloadName);
    }
}
