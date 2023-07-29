<?php

namespace WHMCS\MarketConnect;

class MarketConnectServiceProvider extends \WHMCS\Application\Support\ServiceProvider\AbstractServiceProvider implements \WHMCS\Route\Contracts\ProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    public function register()
    {
    }

    protected function getRoutes()
    {
        return ["/store" => ["attributes" => ["authorization" => function () {
            return new \WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization();
        }], ["name" => "store-ssl-callback", "path" => "/callback/ssl", "handle" => ["WHMCS\\MarketConnect\\SslController", "handleSslCallback"], "method" => ["POST"]], ["name" => "store-reissued-ssl-callback", "path" => "/callback/reissued/ssl", "handle" => ["WHMCS\\MarketConnect\\SslController", "handleReissuedSslCallback"], "method" => ["POST"]], ["name" => "store-addon", "path" => "/addon/feature/{addonSlug}[/{serviceId:\\d+}]", "handle" => ["WHMCS\\Cart\\Controller\\ProductController", "addon"], "method" => ["GET", "POST"]], ["name" => "store-addon-login", "path" => "/addon/login/{addonSlug}[/{serviceId:\\d+}]", "handle" => ["WHMCS\\Cart\\Controller\\ProductController", "loginAndRedirectToAddonPage"], "method" => ["GET"]], ["name" => "store-add-addons", "path" => "/addon/cart/add", "handle" => ["WHMCS\\Cart\\Controller\\ProductController", "addAddonsToCart"], "method" => ["POST"], "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["name" => "store-stage-addon", "path" => "/addon/{addonId:\\d+}/cart/stage", "handle" => ["WHMCS\\Cart\\Controller\\ProductController", "stageAddonForCart"], "method" => ["POST"], "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["name" => "store-product-group", "path" => "/{product_group_slug}[/go/{sub_page_name}]", "handle" => ["WHMCS\\Cart\\Controller\\ProductController", "showGroup"], "method" => ["GET", "POST"]], ["name" => "store-product-product", "path" => "/{product_group_slug}/{product_slug}", "handle" => ["WHMCS\\Cart\\Controller\\ProductController", "showProduct"], "method" => ["GET", "POST"]], ["name" => "store", "path" => "", "handle" => ["WHMCS\\Cart\\Controller\\ProductController", "index"], "method" => ["GET", "POST"]]]];
    }

    public function registerRoutes(\FastRoute\RouteCollector $routeCollector)
    {
        $this->addRouteGroups($routeCollector, $this->getRoutes());
    }
}
