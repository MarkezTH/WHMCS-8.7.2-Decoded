<?php

namespace WHMCS\Module\Gateway\Stripe\Admin;

class StripeRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    protected function getRoutes()
    {
        return ["/admin/stripe" => [["name" => $this->getDeferredRoutePathNameAttribute() . "payment-method-add", "method" => ["POST"], "path" => "/payment/admin/add", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Pay Methods"])->requireCsrfToken();
        }, "handle" => ["WHMCS\\Module\\Gateway\\Stripe\\StripeController", "adminAdd"]]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-stripe-";
    }
}
