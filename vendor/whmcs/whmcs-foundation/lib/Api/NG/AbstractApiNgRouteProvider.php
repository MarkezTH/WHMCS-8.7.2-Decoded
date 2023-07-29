<?php

namespace WHMCS\Api\NG;

abstract class AbstractApiNgRouteProvider implements \WHMCS\Route\Contracts\ProviderInterface, \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\ProviderTrait;
    const API_VERSION = NULL;
    public abstract function getMiddlewareStack();

    public function getRoutePathPrefix()
    {
        if (is_null(static::API_VERSION)) {
            throw new \WHMCS\Exception\Api\NG\ApiNgException("Invalid API_VERSION");
        }
        return "/api/" . static::API_VERSION;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "api-ng-" . $this->getRoutePathPrefix() . "-";
    }
}
