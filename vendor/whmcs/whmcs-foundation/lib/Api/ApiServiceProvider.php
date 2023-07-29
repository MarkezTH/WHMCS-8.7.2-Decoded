<?php

namespace WHMCS\Api;

class ApiServiceProvider extends \WHMCS\Application\Support\ServiceProvider\AbstractServiceProvider implements \WHMCS\Route\Contracts\ProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    public function register()
    {
    }

    public function getRoutes()
    {
        return ["/api/v1" => ["attributes" => ["authentication" => "api", "authorization" => "api"], "0" => ["method" => ["GET", "POST"], "name" => "api-v1-action", "path" => "/{action}", "handle" => ["WHMCS\\Api\\ApplicationSupport\\Route\\Middleware\\HandleProcessor", "process"]]], "/includes" => ["attributes" => ["authentication" => "api", "authorization" => "api"], "0" => ["method" => ["GET", "POST"], "name" => "api-legacy", "path" => "/api.php", "handle" => ["WHMCS\\Api\\ApplicationSupport\\Route\\Middleware\\HandleProcessor", "process"]]]];
    }

    public function registerRoutes(\FastRoute\RouteCollector $routeCollector)
    {
        $this->addRouteGroups($routeCollector, $this->getRoutes());
    }
}
