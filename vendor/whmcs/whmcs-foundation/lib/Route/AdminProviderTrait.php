<?php

namespace WHMCS\Route;

trait AdminProviderTrait
{
    use ProviderTrait;

    protected function enforceAdminAuthentication($routeCollection)
    {
        $noAuthRoutes = ["dev-test", "admin-login"];
        foreach ($routeCollection as $routeKey => &$route) {
            if ($routeKey === "attributes" && isset($route["authentication"]) && in_array($route["authentication"], ["admin", "adminConfirmation"])) {
                return $routeCollection;
            }
            if (!isset($route["authentication"]) && (!isset($route["name"]) || !in_array($route["name"], $noAuthRoutes))) {
                $route["authentication"] = "admin";
            }
        }
    }

    public function mutateAdminRoutesForCustomDirectory($adminRoutes = [])
    {
        $adminBasePath = \WHMCS\Admin\AdminServiceProvider::getAdminRouteBase();
        $mutatedRoutes = [];
        foreach ($adminRoutes as $key => $value) {
            if (is_array($value)) {
                $value = $this->enforceAdminAuthentication($value);
            }
            if (strpos($key, "/admin") === 0) {
                $mutatedKey = $adminBasePath . substr($key, 6);
                $mutatedRoutes[$mutatedKey] = $value;
            } else {
                $mutatedRoutes[$key] = $value;
            }
        }
        return $mutatedRoutes;
    }

    public function registerRoutes(\FastRoute\RouteCollector $routeCollector)
    {
        $this->addRouteGroups($routeCollector, $this->mutateAdminRoutesForCustomDirectory($this->getRoutes()));
    }
}
