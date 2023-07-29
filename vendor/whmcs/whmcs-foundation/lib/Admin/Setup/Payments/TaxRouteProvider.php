<?php

namespace WHMCS\Admin\Setup\Payments;

class TaxRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $helpRoutes = ["/admin/setup/payments/tax" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Tax Configuration"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-setup-payments-tax-index", "path" => "", "handle" => ["WHMCS\\Admin\\Setup\\Payments\\TaxController", "index"], "authentication" => "adminConfirmation"], ["method" => ["POST"], "name" => "admin-setup-payments-tax-settings", "path" => "/settings", "handle" => ["WHMCS\\Admin\\Setup\\Payments\\TaxController", "saveSettings"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-payments-tax-create", "path" => "/create", "handle" => ["WHMCS\\Admin\\Setup\\Payments\\TaxController", "create"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-payments-tax-delete", "path" => "/delete", "handle" => ["WHMCS\\Admin\\Setup\\Payments\\TaxController", "delete"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "authentication" => "adminConfirmation"], ["method" => ["POST"], "name" => "admin-setup-payments-tax-eu-rates", "path" => "/eu-rates", "handle" => ["WHMCS\\Admin\\Setup\\Payments\\TaxController", "setupEuRates"], "authentication" => "adminConfirmation"], ["method" => ["POST"], "name" => "admin-setup-payments-tax-migrate", "path" => "/migrate", "handle" => ["WHMCS\\Admin\\Setup\\Payments\\TaxController", "migrateCustomField"], "authentication" => "adminConfirmation"]]];
        return $helpRoutes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-setup-payments-tax-";
    }
}
