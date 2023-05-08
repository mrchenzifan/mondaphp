<?php

namespace herosphp\plugins\validate;

use herosphp\core\HttpRequest;
use herosphp\core\MiddlewareInterface;
use herosphp\WebApp;

/**
 * 参数校验器
 */
class ValidateMiddleware implements MiddlewareInterface
{
    /**
     * @throws \ReflectionException
     */
    public function process(HttpRequest $request, callable $handler): mixed
    {

        $reflectionMethod = new \ReflectionMethod(WebApp::$targetController, WebApp::$targetMethod);
        $reflectionAttributes = $reflectionMethod->getAttributes(Valid::class);
        if ($reflectionAttributes) {
            foreach ($reflectionAttributes as $validAttribute) {
                /** @var Valid $methodValidInstance */
                $methodValidInstance = $validAttribute->newInstance();
                $methodVInstance = new ($methodValidInstance->class);
                if (! $methodVInstance instanceof Validate) {
                    throw new ValidateException("{$methodVInstance->class} must extend \\herosphp\\plugin\\validate\Validate");
                }
                $methodVInstance->scene($methodValidInstance->scene)->check([...$request->get(), ...$request->post()]);
            }
        }

        return $handler($request);
    }
}
