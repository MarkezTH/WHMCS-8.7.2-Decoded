<?php

namespace WHMCS\Admin\Addon;

class AddonRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/addons" => ["attributes" => ["authentication" => "admin"], ["method" => ["GET", "POST"], "name" => "admin-addons-index", "path" => "", "handle" => ["WHMCS\\Admin\\Addon\\AddonController", "index"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Addons"]);
        }], ["method" => ["GET"], "name" => "admin-addons-detail", "path" => "/detail/{addonid:\\d+}", "handle" => ["WHMCS\\Admin\\Addon\\AddonController", "addonDetail"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Addons", "View Clients Products/Services"]);
        }], ["method" => ["POST"], "name" => "admin-addons-subscription-info", "path" => "/{id:\\d+}/subscription/info", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAnyPermission(["View Clients Products/Services"]);
        }, "handle" => ["WHMCS\\Admin\\Addon\\AddonController", "subscriptionInfo"]], ["method" => ["POST"], "name" => "admin-addons-cancel-subscription", "path" => "/{id:\\d+}/subscription/cancel", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAnyPermission(["Edit Clients Products/Services"]);
        }, "handle" => ["WHMCS\\Admin\\Addon\\AddonController", "subscriptionCancel"]]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-addons-";
    }
}
