<?php

namespace WHMCS\Table;

class TableRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/table" => ["attributes" => ["authentication" => "admin"], ["method" => ["GET", "POST"], "name" => "admin-table-client-services", "path" => "/client/{clientId:\\d+}/services", "handle" => ["WHMCS\\Table\\ServiceTable", "list"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["List Services"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-table-client-addons", "path" => "/client/{clientId:\\d+}/addons", "handle" => ["WHMCS\\Table\\AddonTable", "list"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["List Addons"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-table-client-domains", "path" => "/client/{clientId:\\d+}/domains", "handle" => ["WHMCS\\Table\\DomainTable", "list"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["List Domains"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-table-client-quotes", "path" => "/client/{clientId:\\d+}/quotes", "handle" => ["WHMCS\\Table\\QuoteTable", "list"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Quotes"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-table-affiliate-accounts", "path" => "/affiliate/{affiliateId:\\d+}/accounts", "handle" => ["WHMCS\\Table\\AffiliatesReferredSignupsTable", "list"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken();
        }], ["method" => ["GET", "POST"], "name" => "admin-table-affiliate-pending", "path" => "/affiliate/{affiliateId:\\d+}/pending", "handle" => ["WHMCS\\Table\\AffiliatesPendingCommissionTable", "list"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken();
        }], ["method" => ["GET", "POST"], "name" => "admin-table-affiliate-history", "path" => "/affiliate/{affiliateId:\\d+}/history", "handle" => ["WHMCS\\Table\\AffiliatesCommissionHistoryTable", "list"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken();
        }], ["method" => ["GET", "POST"], "name" => "admin-table-affiliate-withdrawals", "path" => "/affiliate/{affiliateId:\\d+}/withdrawals", "handle" => ["WHMCS\\Table\\AffiliatesWithdrawalHistoryTable", "list"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken();
        }]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-table-";
    }
}
