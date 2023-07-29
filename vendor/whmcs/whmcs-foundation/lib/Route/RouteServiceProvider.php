<?php

namespace WHMCS\Route;

class RouteServiceProvider extends \WHMCS\Application\Support\ServiceProvider\AbstractServiceProvider
{
    use ProviderTrait;

    public function register()
    {
        $container = $this->app;
        $container->singleton("Frontend\\Dispatcher", function () use($container) {
            return new \Middlewares\Utils\Dispatcher([new Middleware\RoutableRequestQueryUri(), new Middleware\RoutableRequestUri(), new Middleware\RoutableAdminRequestUri(), new Middleware\RoutableClientModuleRequest(), new Middleware\RoutableApiRequestUri(false), new Middleware\RoutePathMatch(), new Middleware\BackendDispatch()]);
        });
        $container->singleton("Backend\\Dispatcher\\Api\\NG", function () use($container) {
            return new \Middlewares\Utils\Dispatcher([new \WHMCS\Api\NG\Middleware\ApiNgLogger(), new \WHMCS\Api\NG\Middleware\ApiNgAccessControlHandler(), new \WHMCS\Api\NG\Middleware\ApiNgSpecValidator(), new \WHMCS\Api\NG\Middleware\ApiNgVersionSpecificMiddleware(), new \WHMCS\Api\NG\Middleware\ApiNgHandleProcessor()]);
        });
        $container->singleton("Backend\\Dispatcher\\Api\\V1", function () use($container) {
            return new \Middlewares\Utils\Dispatcher([new \WHMCS\Api\ApplicationSupport\Route\Middleware\ApiLog(), new \WHMCS\Api\ApplicationSupport\Route\Middleware\BackendPsr7Response(), new \WHMCS\Api\ApplicationSupport\Route\Middleware\SystemAccessControl(), new \WHMCS\Api\ApplicationSupport\Route\Middleware\ActionFilter(), $container->make("Route\\Authentication"), $container->make("Route\\Authorization"), new \WHMCS\Api\ApplicationSupport\Route\Middleware\ActionResponseFormat(), new \WHMCS\Api\ApplicationSupport\Route\Middleware\HandleProcessor()]);
        });
        $container->singleton("Backend\\Dispatcher\\Admin", function () use($container) {
            return new \Middlewares\Utils\Dispatcher([new Middleware\BackendPsr7Response(), new \WHMCS\Admin\ApplicationSupport\Route\Middleware\DirectoryValidation(), $container->make("Route\\Authentication"), $container->make("Route\\Authorization"), new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Assent(), new Middleware\HandleProcessor()]);
        });
        $container->singleton("Backend\\Dispatcher\\Client", function () use($container) {
            return new \Middlewares\Utils\Dispatcher([new Middleware\BackendPsr7Response(), $container->make("Route\\Authentication"), $container->make("Route\\Authorization"), new Middleware\HandleProcessor()]);
        });
        $container->singleton("Route\\Router", function () use($container) {
            $router = new \Middlewares\FastRoute($this->app->make("Route\\Dispatch"));
            $router->resolver($container);
            return $router;
        });
        $container->singleton("Route\\RouteCollector", function () {
            $parser = new \FastRoute\RouteParser\Std();
            $generator = new \FastRoute\DataGenerator\GroupCountBased();
            return new \FastRoute\RouteCollector($parser, $generator);
        });
        $container->singleton("Route\\Dispatch", function () use($container) {
            $routeCollector = $container->make("Route\\RouteCollector");
            $this->addRouteGroups($routeCollector, $this->standardRoutes());
            $this->addRouteGroups($routeCollector, $this->apiNgRoutes());
            return new Dispatcher\DeferrableGroup($routeCollector);
        });
        $container->singleton("Route\\UriPath", function () {
            return new UriPath();
        });
        $container->singleton("Route\\Authorization", function () {
            return new Middleware\AuthorizationProxy();
        });
        $container->singleton("Route\\ResponseType", function () {
            return new \WHMCS\Http\Message\ResponseFactory();
        });
        $container->singleton("Route\\Authentication", function () {
            return new Middleware\AuthenticationProxy();
        });
    }

    protected function standardRoutes()
    {
        return ["/resources/test" => [["method" => ["GET", "POST"], "path" => "/detect-route-environment", "handle" => function ($request) {
            $controller = new \WHMCS\Admin\Setup\General\UriManagement\ConfigurationController(\WHMCS\Admin\Setup\General\UriManagement\ConfigurationController::PATH_COMPARISON_TEST);
            return $controller->detectRouteEnvironment($request);
        }], ["method" => ["GET", "POST"], "path" => "/index.php[/detect-route-environment]", "handle" => function ($request) {
            $controller = new \WHMCS\Admin\Setup\General\UriManagement\ConfigurationController(\WHMCS\Admin\Setup\General\UriManagement\ConfigurationController::PATH_COMPARISON_TEST);
            return $controller->detectRouteEnvironment($request);
        }]], "/job" => new \WHMCS\Scheduling\Jobs\AsyncJobRouteProvider(), "/admin" => new \WHMCS\Admin\AdminRouteProvider(), "" => [["method" => ["GET", "POST"], "path" => "/detect-route-environment", "handle" => function ($request) {
            $controller = new \WHMCS\Admin\Setup\General\UriManagement\ConfigurationController(\WHMCS\Admin\Setup\General\UriManagement\ConfigurationController::PATH_COMPARISON_INDEX);
            return $controller->detectRouteEnvironment($request);
        }], ["method" => ["GET", "POST"], "path" => "/index.php/detect-route-environment", "handle" => function ($request) {
            $controller = new \WHMCS\Admin\Setup\General\UriManagement\ConfigurationController(\WHMCS\Admin\Setup\General\UriManagement\ConfigurationController::PATH_COMPARISON_INDEX);
            return $controller->detectRouteEnvironment($request);
        }], ["name" => "route-not-defined", "path" => "/route-not-defined", "method" => ["GET", "POST"], "handle" => function (\Psr\Http\Message\ServerRequestInterface $request) {
            $response = new \WHMCS\ClientArea();
            $response->setPageTitle("404 - Unknown Route Path");
            $response->setTemplate("error/unknown-routepath");
            $referrer = "";
            if (!empty($request->getServerParams()["HTTP_REFERER"])) {
                $referrer = $request->getServerParams()["HTTP_REFERER"];
            }
            $response->assign("referrer", $referrer);
            return $response->withStatus(404);
        }], ["name" => "clientarea-homepage", "method" => ["GET", "POST"], "path" => "/", "handle" => ["\\WHMCS\\ClientArea\\ClientAreaController", "homePage"]], ["name" => "clientarea-index", "method" => ["GET", "POST"], "path" => "/index.php", "handle" => ["\\WHMCS\\ClientArea\\ClientAreaController", "homePage"]]]];
    }

    protected function apiNgRoutes()
    {
        $collector = new \WHMCS\Api\NG\ApiNgImplementationCollector();
        $routes = [];
        foreach ($collector->getApiNgRouteProviders() as $providerClass) {
            $routeProvider = new $providerClass();
            $pathPrefix = $routeProvider->getRoutePathPrefix();
            $routes[$pathPrefix] = $routeProvider;
        }
        return $routes;
    }
}
