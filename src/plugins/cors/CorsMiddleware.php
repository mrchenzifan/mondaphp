<?php

namespace herosphp\plugins\cors;

use herosphp\core\HttpRequest;
use herosphp\core\MiddlewareInterface;
use herosphp\GF;
use Workerman\Protocols\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    // 处理跨域访问问题
    public function process(HttpRequest $request, callable $handler): mixed
    {
        $response = $request->method() == 'OPTIONS' ? GF::response() : $handler($request);
        if ($response instanceof Response) {
            $response->withHeaders([
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Allow-Origin' => $request->header('origin', '*'),
                'Access-Control-Allow-Methods' => '*',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Length, Content-Type, SESSION-TOKEN',
                'Access-Control-Max-Age' => 3600,
            ]);

        }
        return $response;
    }
}
