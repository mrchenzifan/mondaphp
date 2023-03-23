<?php

declare(strict_types=1);

namespace herosphp\exception;

use herosphp\core\HttpRequest;
use Throwable;

interface ExceptionHandlerInterface
{
    /**
     * @param  Throwable  $e
     * @return void
     */
    public function report(Throwable $e): void;

    /**
     * @param  HttpRequest  $request
     * @param  Throwable  $e
     * @return mixed
     */
    public function render(HttpRequest $request, Throwable $e): mixed;
}
