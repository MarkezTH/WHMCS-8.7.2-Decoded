<?php

namespace WHMCS\Cart;

class CartServiceProvider extends \WHMCS\Application\Support\ServiceProvider\AbstractServiceProvider implements \WHMCS\Route\Contracts\ProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    protected function getRoutes()
    {
        return ["/cart" => ["attributes" => ["authorization" => function () {
            return new \WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization();
        }], ["name" => "cart-domain-renewals-add", "method" => ["POST"], "path" => "/domain/renew/add", "handle" => ["WHMCS\\Cart\\Controller\\DomainController", "addRenewal"], "authentication" => "clientarea", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["domains", "orders"]);
        }], ["name" => "cart-domain-renewals", "method" => ["GET", "POST"], "path" => "/domain/renew", "handle" => ["WHMCS\\Cart\\Controller\\DomainController", "massRenew"], "authentication" => "clientarea", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["domains", "orders"]);
        }], ["name" => "cart-domain-renew-calculate", "method" => ["GET"], "path" => "/domain/renew/calculate", "handle" => ["WHMCS\\Cart\\Controller\\DomainController", "calcRenewalCartTotals"], "authentication" => "clientarea", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["domains", "orders"]);
        }], ["name" => "cart-invoice-pay-process", "method" => ["GET"], "path" => "/invoice/{id:\\d+}/pay", "handle" => ["WHMCS\\ClientArea\\Invoice\\InvoiceController", "processCardFromCart"], "authentication" => "clientarea"], ["name" => "cart-account-select", "method" => ["POST"], "path" => "/account/select", "handle" => ["WHMCS\\Cart\\CartCalculationController", "selectAccount"], "authentication" => "clientarea", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authorization) {
            return $authorization->requireCsrfToken();
        }], ["name" => "cart-order-addtocart", "path" => "/order/add", "handle" => ["WHMCS\\MarketConnect\\StoreController", "addToCart"], "method" => ["POST"]], ["name" => "cart-order-login", "path" => "/order/login", "handle" => ["WHMCS\\MarketConnect\\StoreController", "login"], "method" => ["GET", "POST"]], ["name" => "cart-order-validate", "path" => "/order/validate", "handle" => ["WHMCS\\MarketConnect\\StoreController", "validate"], "method" => ["POST"]], ["name" => "cart-order", "path" => "/order", "handle" => ["WHMCS\\MarketConnect\\StoreController", "order"], "method" => ["POST", "GET"]], ["name" => "cart-weebly-upgrade", "path" => "/weebly/upgrade", "handle" => ["WHMCS\\MarketConnect\\WeeblyController", "upgrade"], "method" => ["GET", "POST"], "authentication" => "clientarea", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["manageproducts", "orders"]);
        }], ["name" => "cart-weebly-upgrade-order", "path" => "/weebly/upgrade/order", "handle" => ["WHMCS\\MarketConnect\\WeeblyController", "orderUpgrade"], "method" => ["POST"], "authentication" => "clientarea", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["manageproducts", "orders"]);
        }], ["name" => "cart-site-builder-upgrade", "path" => "/site-builder/upgrade", "handle" => ["WHMCS\\MarketConnect\\SiteBuilderController", "upgrade"], "method" => ["GET", "POST"], "authentication" => "clientarea", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["manageproducts", "orders"]);
        }], ["name" => "cart-site-builder-upgrade-order", "path" => "/site-builder/upgrade/order", "handle" => ["WHMCS\\MarketConnect\\SiteBuilderController", "orderUpgrade"], "method" => ["POST"], "authentication" => "clientarea", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["manageproducts", "orders"]);
        }], ["name" => "cart-threesixtymonitoring-site-check", "path" => "/threesixtymonitoring/sitecheck", "handle" => ["WHMCS\\MarketConnect\\ThreeSixtyMonitoringController", "performSiteCheck"], "method" => ["POST"]], ["name" => "cart-index", "method" => ["GET", "POST"], "path" => "", "handle" => ["WHMCS\\Cart\\CartCalculationController", "index"]]]];
    }

    public function registerRoutes(\FastRoute\RouteCollector $routeCollector)
    {
        $this->addRouteGroups($routeCollector, $this->getRoutes());
    }

    public function register()
    {
    }
}
