<?php

namespace WHMCS\Admin\Billing;

class BillingRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/billing" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization();
        }], ["method" => ["POST"], "name" => "admin-billing-invoice-new", "path" => "/invoice/new", "handle" => ["WHMCS\\Admin\\Billing\\BillingController", "newInvoice"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["Create Invoice"]);
        }], ["method" => ["POST"], "name" => "admin-billing-invoice-create", "path" => "/invoice/create", "handle" => ["WHMCS\\Admin\\Billing\\BillingController", "createInvoice"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["Create Invoice"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-billing-offline-cc-form", "path" => "/offline-cc/invoice/{invoice_id:\\d+}", "handle" => ["WHMCS\\Admin\\Billing\\OfflineCcController", "getForm"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["Offline Credit Card Processing"]);
        }], ["method" => ["POST"], "name" => "admin-billing-offline-cc-decrypt", "path" => "/offline-cc/invoice/{invoice_id:\\d+}/decrypt_card", "handle" => ["WHMCS\\Admin\\Billing\\OfflineCcController", "decryptCardData"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["Offline Credit Card Processing"]);
        }], ["method" => ["POST"], "name" => "admin-billing-offline-cc-apply-transaction", "path" => "/offline-cc/invoice/{invoice_id:\\d+}/apply_transaction", "handle" => ["WHMCS\\Admin\\Billing\\OfflineCcController", "applyTransaction"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["Offline Credit Card Processing"]);
        }], ["method" => ["POST"], "name" => "admin-billing-gateway-balance-totals", "path" => "/gateway/balance/totals", "handle" => ["WHMCS\\Admin\\Billing\\BillingController", "gatewayBalancesTotals"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["View Gateway Balances"]);
        }], ["method" => ["POST"], "name" => "admin-billing-transaction-information", "path" => "/transaction/{id:\\d+}/information", "handle" => ["WHMCS\\Admin\\Billing\\BillingController", "transactionInformation"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["List Transactions"]);
        }]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-billing-";
    }
}
