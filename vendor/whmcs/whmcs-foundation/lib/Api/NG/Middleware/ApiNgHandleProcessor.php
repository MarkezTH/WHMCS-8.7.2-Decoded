<?php

namespace WHMCS\Api\NG\Middleware;

class ApiNgHandleProcessor implements \WHMCS\Route\Contracts\Middleware\StrategyInterface
{
    use \WHMCS\Route\Middleware\Strategy\AssumingMiddlewareTrait;

    public function _process(\WHMCS\Http\Message\ServerRequest $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate)
    {
        $router = \DI::make("Route\\Router");
        $request->parseJson();
        if (!isset($_SESSION)) {
            $_SESSION["cart"] = [];
        }
        return $router->process($request, $delegate);
    }
}
