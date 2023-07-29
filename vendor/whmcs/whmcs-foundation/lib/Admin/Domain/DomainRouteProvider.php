<?php

namespace WHMCS\Admin\Domain;

class DomainRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/domains" => ["attributes" => ["authentication" => "admin"], ["method" => ["GET", "POST"], "name" => "admin-domains-index", "path" => "", "handle" => ["WHMCS\\Admin\\Domain\\DomainController", "index"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Domains"]);
        }], ["name" => "admin-domains-ssl-check", "method" => ["POST"], "path" => "/ssl-check", "handle" => ["WHMCS\\Admin\\Domain\\DomainController", "sslCheck"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken();
        }], ["method" => ["GET"], "name" => "admin-domains-detail", "path" => "/detail/{domainid:\\d+}", "handle" => ["WHMCS\\Admin\\Domain\\DomainController", "domainDetail"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Domains", "View Clients Domains"]);
        }], ["method" => ["POST"], "name" => "admin-domains-subscription-info", "path" => "/{id:\\d+}/subscription/info", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAnyPermission(["View Clients Domains"]);
        }, "handle" => ["WHMCS\\Admin\\Domain\\DomainController", "subscriptionInfo"]], ["method" => ["POST"], "name" => "admin-domains-cancel-subscription", "path" => "/{id:\\d+}/subscription/cancel", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAnyPermission(["Edit Clients Domains"]);
        }, "handle" => ["WHMCS\\Admin\\Domain\\DomainController", "subscriptionCancel"]]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-domains-";
    }
}
