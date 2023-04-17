<?php

// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// * Copyright 2014 The Herosphp Authors. All rights reserved.
// * Use of this source code is governed by a MIT-style license
// * that can be found in the LICENSE file.
// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
declare(strict_types=1);

namespace herosphp\core;

use herosphp\WebApp;
use Throwable;
use Workerman\Protocols\Http\Response;

/**
 * web http response wrapper class
 *
 * @author RockYang<yangjian102621@gmail.com>
 */
class HttpResponse extends Response
{
    // output file stream
    public function file(string $file): self
    {
        if (WebApp::notModifiedSince($file)) {
            return $this->withStatus(304);
        }

        return $this->withFile($file);
    }

    // file download
    public function download(string $file, string $downloadName = ''): self
    {
        $this->withFile($file);
        if ($downloadName) {
            $this->header('Content-Disposition', "attachment; filename=\"$downloadName\"");
        }

        return $this;
    }

}
