<?php

namespace WHMCS\Admin\Apps;

class AppsRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $remoteAuthRoutes = ["/admin/apps" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Apps and Integrations"]);
        }], ["method" => ["GET"], "name" => $this->getDeferredRoutePathNameAttribute() . "index", "path" => "", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "index"]], ["method" => ["GET"], "name" => $this->getDeferredRoutePathNameAttribute() . "browse", "path" => "/browse", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "jumpBrowse"]], ["method" => ["GET"], "name" => $this->getDeferredRoutePathNameAttribute() . "browse-category", "path" => "/browse/{category}", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "jumpBrowse"]], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "featured", "path" => "/featured", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "featured"]], ["method" => ["GET"], "name" => $this->getDeferredRoutePathNameAttribute() . "active", "path" => "/active", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "jumpActive"]], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "active", "path" => "/active", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "active"]], ["method" => ["GET"], "name" => $this->getDeferredRoutePathNameAttribute() . "search", "path" => "/search", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "jumpSearch"]], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "search", "path" => "/search", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "search"]], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "category", "path" => "/browse/{category}", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "category"]], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "info", "path" => "/app/{moduleSlug}", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "infoModal"]], ["method" => ["GET"], "name" => $this->getDeferredRoutePathNameAttribute() . "logo", "path" => "/logo/{moduleSlug}", "handle" => ["WHMCS\\Admin\\Apps\\AppsController", "logo"]]]];
        return $remoteAuthRoutes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-apps-";
    }
}
