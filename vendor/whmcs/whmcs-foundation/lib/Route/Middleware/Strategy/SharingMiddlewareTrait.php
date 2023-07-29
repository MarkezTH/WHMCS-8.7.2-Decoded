<?php

namespace WHMCS\Route\Middleware\Strategy;

trait SharingMiddlewareTrait
{
    public abstract function _process(\WHMCS\Http\Message\ServerRequest $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate);
    public abstract function process(\Psr\Http\Message\ServerRequestInterface $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate);
}
