<?php

namespace WHMCS\Admin\Setup\Payments;

class GatewaysRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/setup/payments/gateways" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Configure Payment Gateways"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-setup-payments-gateways-onboarding-return", "path" => "/onboarding/return", "handle" => ["WHMCS\\Admin\\Setup\\Payments\\GatewaysController", "handleOnboardingReturn"]], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "action", "path" => "/{gateway:\\w+}/action/{method:\\w+}", "handle" => ["WHMCS\\Admin\\Setup\\Payments\\GatewaysController", "callAdditionalFunction"]]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-setup-payments-gateways-";
    }
}
