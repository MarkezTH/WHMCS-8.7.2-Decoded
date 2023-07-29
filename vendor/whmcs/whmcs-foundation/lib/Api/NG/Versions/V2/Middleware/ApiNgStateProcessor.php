<?php

namespace WHMCS\Api\NG\Versions\V2\Middleware;

class ApiNgStateProcessor implements \WHMCS\Route\Contracts\Middleware\StrategyInterface
{
    use \WHMCS\Route\Middleware\Strategy\AssumingMiddlewareTrait;

    public function _process(\WHMCS\Http\Message\ServerRequest $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate)
    {
        $request = (new \WHMCS\Api\NG\Versions\V2\State\ApiRequestStateHandler())->createForRequest($request);
        $response = $delegate->process($request);
        return $request->getState()->addToResponse($response);
    }
}
