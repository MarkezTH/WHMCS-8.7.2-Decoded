<?php

namespace WHMCS\Route;

trait ProviderTrait
{
    public function getRoutes()
    {
        return [];
    }

    public function addRoute(\FastRoute\RouteCollector $routeCollector, $route, $group = "")
    {
        $path = $route["path"];
        $routeCollector->addRoute($route["method"], $path, $route["handle"]);
        if (isset($route["name"])) {
            if ($group) {
                $path = $group . $path;
            }
            $route["canonicalPath"] = $path;
            $this->getUriMap()->mapRoute($route);
        } else {
            if (isset($route["handle"]) && $route["handle"] instanceof Contracts\DeferredProviderInterface) {
                $handle = $route["handle"];
                $this->getUriMap()->mapRoute(["name" => $handle->getDeferredRoutePathNameAttribute(), "canonicalPath" => $handle]);
            }
        }
        if (isset($route["authentication"])) {
            $this->getAuthenticationMap()->mapRoute($route);
        }
        if (isset($route["authorization"])) {
            $this->getAuthorizationMap()->mapRoute($route);
        }
        if (isset($route["responseType"])) {
            $this->getResponseTypeMap()->mapRoute($route);
        }
    }

    public function getUriMap()
    {
        return \DI::make("Route\\UriPath");
    }

    public function getAuthenticationMap()
    {
        return \DI::make("Route\\Authentication");
    }

    public function getAuthorizationMap()
    {
        return \DI::make("Route\\Authorization");
    }

    public function getResponseTypeMap()
    {
        return \DI::make("Route\\ResponseType");
    }

    public function applyGroupLevelAttributes($routes)
    {
        if (is_array($routes) && !empty($routes["attributes"])) {
            $groupAttributes = $routes["attributes"];
            unset($routes["attributes"]);
            array_walk($routes, function (&$routeDefinition) use($groupAttributes) {
                if ($this->hasCallableHandler($routeDefinition)) {
                    if (isset($groupAttributes["authorization"]) && isset($routeDefinition["authorization"]) && is_callable($routeDefinition["authorization"])) {
                        $groupFunc = $groupAttributes["authorization"];
                        $routeFunc = $routeDefinition["authorization"];
                        $wrapperFunc = function () use($groupFunc, $routeFunc) {
                            if (is_callable($groupFunc)) {
                                $groupBaseInstance = $groupFunc();
                            } else {
                                $groupBaseInstance = $groupFunc;
                            }
                            return $routeFunc($groupBaseInstance);
                        };
                        $routeDefinition["authorization"] = $wrapperFunc;
                    }
                    $routeDefinition = array_merge($groupAttributes, $routeDefinition);
                }
            });
        }
        return $routes;
    }

    public function addRouteGroups(\FastRoute\RouteCollector $routeCollector, $routeGroup = [])
    {
        foreach ($routeGroup as $group => $routes) {
            if ($routes instanceof Contracts\DeferredProviderInterface) {
                $this->addDeferredRouteGroup($routeCollector, $routes, $group);
            } else {
                if ($routes instanceof Contracts\ProviderInterface) {
                    $routes->registerRoutes($routeCollector);
                } else {
                    if ($group) {
                        $routes = $this->applyGroupLevelAttributes($routes);
                        $routeCollector->addGroup($group, function (\FastRoute\RouteCollector $routeCollector) use($routes) {
                            foreach ($routes as $route) {
                                $this->addRoute($routeCollector, $route, $group);
                            }
                        });
                    } else {
                        foreach ($routes as $route) {
                            $this->addRoute($routeCollector, $route);
                        }
                    }
                }
            }
        }
    }

    public function addDeferredRouteGroup(\FastRoute\RouteCollector $routeCollector, Contracts\ProviderInterface $provider, $group)
    {
        $this->addRoute($routeCollector, ["path" => "/DEFERRED_GROUP" . $group . "[/{stub_wildcard}]", "handle" => $provider, "method" => ["GET", "POST", "PUT", "DELETE"]], $group);
    }

    public function registerRoutes(\FastRoute\RouteCollector $routeCollector)
    {
        $this->addRouteGroups($routeCollector, $this->getRoutes());
    }

    protected function hasCallableHandler($routeDefinition)
    {
        if (!is_array($routeDefinition) || !isset($routeDefinition["handle"])) {
            return false;
        }
        if (is_callable($routeDefinition["handle"])) {
            return true;
        }
        if (is_array($routeDefinition["handle"])) {
            return method_exists($routeDefinition["handle"][0], $routeDefinition["handle"][1]);
        }
        return false;
    }
}
