<?php

namespace WHMCS\Admin\Setup\Tld;

class TldRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        return ["/admin/tld" => [["method" => ["POST"], "name" => "admin-tld-mass-configuration", "path" => "/mass-configuration", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Configure Domain Pricing"]);
        }, "handle" => ["WHMCS\\Admin\\Setup\\Tld\\TldController", "massConfiguration"]], ["method" => ["POST"], "name" => "admin-tld-spotlight", "path" => "/spotlight", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Configure Domain Pricing"]);
        }, "handle" => ["WHMCS\\Admin\\Setup\\Tld\\TldController", "manageSpotlight"]]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-tld-";
    }
}
