<?php

namespace WHMCS\Admin\Service;

class ServiceRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/services" => ["attributes" => ["authentication" => "admin"], ["method" => ["GET", "POST"], "name" => "admin-services-index", "path" => "", "handle" => ["WHMCS\\Admin\\Service\\ServiceController", "index"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Services"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-services-shared", "path" => "/shared", "handle" => ["WHMCS\\Admin\\Service\\ServiceController", "shared"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Services"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-services-reseller", "path" => "/reseller", "handle" => ["WHMCS\\Admin\\Service\\ServiceController", "reseller"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Services"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-services-server", "path" => "/server", "handle" => ["WHMCS\\Admin\\Service\\ServiceController", "server"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Services"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-services-other", "path" => "/other", "handle" => ["WHMCS\\Admin\\Service\\ServiceController", "other"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Services"]);
        }], ["method" => ["GET"], "name" => "admin-services-detail", "path" => "/detail/{serviceid:\\d+}", "handle" => ["WHMCS\\Admin\\Service\\ServiceController", "serviceDetail"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Services", "View Clients Products/Services"]);
        }], ["method" => ["POST"], "name" => "admin-services-subscription-info", "path" => "/{id:\\d+}/subscription/info", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAnyPermission(["View Clients Products/Services"]);
        }, "handle" => ["WHMCS\\Admin\\Service\\ServiceController", "subscriptionInfo"]], ["method" => ["POST"], "name" => "admin-services-cancel-subscription", "path" => "/{id:\\d+}/subscription/cancel", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAnyPermission(["Edit Clients Products/Services"]);
        }, "handle" => ["WHMCS\\Admin\\Service\\ServiceController", "subscriptionCancel"]]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-services-";
    }
}
