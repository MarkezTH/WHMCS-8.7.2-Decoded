<?php

namespace WHMCS\Api\NG\Middleware;

class ApiNgVersionSpecificMiddleware implements \WHMCS\Route\Contracts\Middleware\StrategyInterface
{
    use \WHMCS\Route\Middleware\Strategy\AssumingMiddlewareTrait;
    protected $stack = [];
    private $delegate = NULL;

    public function _process(\WHMCS\Http\Message\ServerRequest $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate)
    {
        $requestApiVersion = $request->getAttribute(\WHMCS\Route\Middleware\RoutableApiRequestUri::ATTRIBUTE_API_NG_VERSION);
        if (empty($requestApiVersion)) {
            throw new \WHMCS\Exception\Api\NG\ApiNgException("Invalid API version for version-specific middleware");
        }
        $collector = new \WHMCS\Api\NG\ApiNgImplementationCollector();
        $routeProvider = NULL;
        foreach ($collector->getApiNgRouteProviders() as $providerClass) {
            if ($providerClass::API_VERSION === $requestApiVersion) {
                $routeProvider = new $providerClass();
                if (!$routeProvider) {
                    throw new \WHMCS\Exception\Api\NG\ApiNgException("No matching version-specific middleware for API " . $requestApiVersion);
                }
                $this->stack = $routeProvider->getMiddlewareStack();
                $this->delegate = $delegate;
                return $this->resolve(0)->process($request);
            }
        }
    }

    private function resolve($index)
    {
        return new \Middlewares\Utils\Delegate(function (\Psr\Http\Message\ServerRequestInterface $request) use($index) {
            $middleware = $this->stack[$index] ?? new \Middlewares\Utils\CallableMiddleware(function ($request) {
                return $this->delegate->process($request);
            });
            if ($middleware instanceof \Closure) {
                $middleware = new \Middlewares\Utils\CallableMiddleware($middleware);
            }
            assert($middleware instanceof \Interop\Http\ServerMiddleware\MiddlewareInterface, "assert(\$middleware instanceof MiddlewareInterface)");
            $result = $middleware->process($request, $this->resolve($index + 1));
            assert($result instanceof \Psr\Http\Message\ResponseInterface, "assert(\$result instanceof ResponseInterface)");
            return $result;
        });
    }
}
