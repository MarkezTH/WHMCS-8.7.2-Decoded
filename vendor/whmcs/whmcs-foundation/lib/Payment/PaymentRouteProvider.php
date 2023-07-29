<?php

namespace WHMCS\Payment;

class PaymentRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    protected function getRoutes()
    {
        return ["/payment" => [["name" => $this->getDeferredRoutePathNameAttribute() . "remote-confirm", "method" => ["POST"], "path" => "/remote/confirm", "authorization" => function () {
            return (new \WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken();
        }, "handle" => ["WHMCS\\Payment\\PaymentController", "confirm"]], ["name" => $this->getDeferredRoutePathNameAttribute() . "remote-confirm-update", "method" => ["POST"], "path" => "/remote/confirm/update", "authorization" => function () {
            return (new \WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken();
        }, "handle" => ["WHMCS\\Payment\\PaymentController", "update"]], ["name" => $this->getDeferredRoutePathNameAttribute() . "get-existing-token", "method" => ["POST"], "path" => "/{module}/token/get", "authorization" => function () {
            return (new \WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken();
        }, "handle" => ["WHMCS\\Payment\\PaymentController", "getRemoteToken"]]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "payment-";
    }
}
