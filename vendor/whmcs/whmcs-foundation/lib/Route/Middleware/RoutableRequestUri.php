<?php

namespace WHMCS\Route\Middleware;

class RoutableRequestUri implements \WHMCS\Route\Contracts\Middleware\StrategyInterface
{
    use Strategy\AssumingMiddlewareTrait;

    public function _process(\WHMCS\Http\Message\ServerRequest $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate)
    {
        return $delegate->process($this->updateUriFromServerScriptName($request));
    }

    public function updateUriFromServerScriptName(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $serverParams = $request->getServerParams();
        if (is_array($serverParams) && isset($serverParams["SCRIPT_NAME"])) {
            $serverScriptName = $serverParams["SCRIPT_NAME"];
        } else {
            $serverScriptName = NULL;
        }
        if (0 < strlen($path)) {
            $path = \WHMCS\Utility\Environment\WebHelper::getRelativePath($path, $serverScriptName);
        }
        $uri = $uri->withPath($path);
        return $request->withUri($uri);
    }
}
