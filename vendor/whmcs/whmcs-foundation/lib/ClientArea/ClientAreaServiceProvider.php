<?php

namespace WHMCS\ClientArea;

class ClientAreaServiceProvider extends \WHMCS\Application\Support\ServiceProvider\AbstractServiceProvider implements \WHMCS\Route\Contracts\ProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    public function getFirstLevelDirectoryNames()
    {
        $firstLevels = [];
        foreach ($this->getRoutes() as $dir => $definitions) {
            $firstLevels[] = trim($dir, "/");
        }
        return $firstLevels;
    }

    protected function getRoutes()
    {
        $routes = ["/clientarea" => [["method" => ["POST"], "path" => "/service/{serviceId:\\d+}/custom-action/{identifier:\\w+}", "authentication" => "clientarea", "authorization" => function () {
            return (new ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["productsso"])->requireClientService("serviceId");
        }, "handle" => ["\\WHMCS\\ClientArea\\ClientAreaController", "performCustomAction"]], ["method" => ["GET", "POST"], "path" => "/module/{module}", "handle" => ["\\WHMCS\\Module\\ClientAreaController", "index"]], ["method" => ["GET", "POST"], "name" => "clientarea-home", "path" => "", "authentication" => "clientarea", "handle" => ["\\WHMCS\\ClientArea\\ClientAreaController", "clientHome"]], ["name" => "clientarea-ssl-certificates-manage", "path" => "/ssl-certificates/manage", "handle" => ["WHMCS\\MarketConnect\\SslController", "manage"], "method" => ["GET"]], ["name" => "clientarea-ssl-certificates-resend-approver-email", "path" => "/ssl-certificates/resend-approver-email", "handle" => ["WHMCS\\MarketConnect\\SslController", "resendApproverEmail"], "method" => ["POST"]], ["name" => "module-custom-action", "path" => "/service/{serviceId:\\d+}/action/{method:\\w+}", "handle" => ["WHMCS\\ClientArea\\ClientAreaController", "runCustomModuleAction"], "method" => ["GET"], "authentication" => "clientarea", "authorization" => function () {
            return (new ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["products", "manageproducts"]);
        }], ["name" => "module-custom-action-addon", "path" => "/service/{serviceId:\\d+}/addon/{addonId:\\d+}/action/{method:\\w+}", "handle" => ["WHMCS\\ClientArea\\ClientAreaController", "runCustomModuleAction"], "method" => ["GET"], "authentication" => "clientarea", "authorization" => function () {
            return (new ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["products", "manageproducts"]);
        }], ["name" => "module-custom-post-action", "path" => "/service/{serviceId:\\d+}/action/{method:\\w+}", "handle" => ["WHMCS\\ClientArea\\ClientAreaController", "runCustomModuleAction"], "method" => ["POST"], "authentication" => "clientarea", "authorization" => function () {
            return (new ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["products", "manageproducts"]);
        }], ["name" => "module-custom-post-action-addon", "path" => "/service/{serviceId:\\d+}/addon/{addonId:\\d+}/action/{method:\\w+}", "handle" => ["WHMCS\\ClientArea\\ClientAreaController", "runCustomModuleAction"], "method" => ["POST"], "authentication" => "clientarea", "authorization" => function () {
            return (new ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["products", "manageproducts"]);
        }], ["name" => "clientarea-parse-markdown", "path" => "/message/preview", "method" => ["GET"], "handle" => ["WHMCS\\ClientArea\\ClientAreaController", "parseMarkdown"], "authorization" => function () {
            return (new ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken();
        }], ["name" => "clientarea-threesixtymonitoring-get-dashboard-data", "path" => "/threesixtymonitoring/service/dashboard", "handle" => ["WHMCS\\MarketConnect\\ThreeSixtyMonitoringController", "getServiceDashboardData"], "method" => ["POST"]]], "/login" => new Login\LoginRouteProvider(), "/user" => new User\UserRouteProvider(), "/account" => new Account\AccountRouteProvider(), "/invite" => new User\InviteRouteProvider(), "/download" => [["name" => "download-index", "method" => "GET", "path" => "", "handle" => ["\\WHMCS\\Download\\Controller\\DownloadController", "index"]], ["name" => "download-by-cat", "method" => "GET", "path" => "/category/{catid:\\d+}[/{slug}.html]", "handle" => ["\\WHMCS\\Download\\Controller\\DownloadController", "viewCategory"]], ["name" => "download-search", "method" => ["GET", "POST"], "path" => "/search[/{search:.*}]", "handle" => ["\\WHMCS\\Download\\Controller\\DownloadController", "search"]]], "/downloads" => [["name" => "download-by-cat-legacy", "method" => "GET", "path" => "/{catid:\\d+}[/{slug}.html]", "handle" => ["\\WHMCS\\Download\\Controller\\DownloadController", "viewCategory"]]], "/knowledgebase" => new \WHMCS\Knowledgebase\KnowledgebaseServiceProvider(), "/announcements" => [["name" => "announcement-index", "method" => "GET", "path" => "[/view/{view:[^/]+}]", "handle" => ["\\WHMCS\\Announcement\\Controller\\AnnouncementController", "index"]], ["name" => "announcement-index-paged", "method" => "GET", "path" => "/page/{page:\\d+}[/view/{view:[^/]+}]", "handle" => ["\\WHMCS\\Announcement\\Controller\\AnnouncementController", "index"]], ["name" => "announcement-twitterfeed", "method" => "POST", "path" => "/twitterfeed", "handle" => ["\\WHMCS\\Announcement\\Controller\\AnnouncementController", "twitterFeed"]], ["name" => "announcement-view", "method" => "GET", "path" => "/{id:\\d+}[/{slug}.html]", "handle" => ["\\WHMCS\\Announcement\\Controller\\AnnouncementController", "view"]], ["name" => "announcement-rss", "method" => "GET", "path" => "/rss", "handle" => ["\\WHMCS\\Announcement\\Rss", "toXml"]]], "/domain" => [["name" => "domain-check", "method" => "POST", "path" => "/check", "handle" => ["\\WHMCS\\Domain\\Checker", "ajaxCheck"]], ["name" => "domain-pricing", "method" => ["GET", "POST"], "path" => "/pricing", "handle" => ["WHMCS\\Domains\\Controller\\DomainController", "pricing"]], ["name" => "domain-renewal", "method" => ["GET"], "path" => "/{domain}/renew", "handle" => ["WHMCS\\Cart\\Controller\\DomainController", "singleRenew"]], ["name" => "domain-ssl-check", "method" => ["POST"], "path" => "/ssl-check", "handle" => ["WHMCS\\Domains\\Controller\\DomainController", "sslCheck"]]], "/ssl-purchase" => [["name" => "ssl-purchase", "method" => ["GET"], "path" => "", "handle" => ["WHMCS\\ClientArea\\ClientAreaController", "sslPurchase"]]], "/upgrade" => [["name" => "upgrade", "method" => ["POST"], "path" => "", "handle" => ["WHMCS\\ClientArea\\UpgradeController", "index"]], ["name" => "upgrade-redirect", "method" => ["GET"], "path" => "/service/{serviceid:\\d+}[/{isproduct:\\d}]", "handle" => ["WHMCS\\ClientArea\\UpgradeController", "index"]], ["name" => "upgrade-add-to-cart", "method" => "POST", "path" => "/validate", "handle" => ["WHMCS\\ClientArea\\UpgradeController", "addToCart"], "authentication" => "clientarea", "authorization" => function () {
            return (new ApplicationSupport\Route\Middleware\Authorization())->setRequireAnyPermission(["orders"])->requireCsrfToken();
        }]], "/subscription" => [["name" => "subscription-manage", "method" => "GET", "path" => "", "handle" => ["\\WHMCS\\Marketing\\SubscriptionController", "manage"]]], "/password/reset" => [["name" => "password-reset-begin", "method" => "GET", "path" => "", "handle" => ["WHMCS\\ClientArea\\PasswordResetController", "emailPrompt"]], ["name" => "password-reset-validate-email", "method" => "POST", "path" => "", "handle" => ["WHMCS\\ClientArea\\PasswordResetController", "validateEmail"]], ["name" => "password-reset-use-key", "method" => "GET", "path" => "/redeem/{key:[a-z\\d]+}", "handle" => ["WHMCS\\ClientArea\\PasswordResetController", "useKey"]], ["name" => "password-reset-security-prompt", "method" => ["GET"], "path" => "/verify", "handle" => ["WHMCS\\ClientArea\\PasswordResetController", "securityPrompt"]], ["name" => "password-reset-security-verify", "method" => ["POST"], "path" => "/verify", "handle" => ["WHMCS\\ClientArea\\PasswordResetController", "securityValidate"]], ["name" => "password-reset-change-prompt", "method" => ["GET"], "path" => "/change", "handle" => ["WHMCS\\ClientArea\\PasswordResetController", "changePrompt"]], ["name" => "password-reset-change-perform", "method" => ["POST"], "path" => "/change", "handle" => ["WHMCS\\ClientArea\\PasswordResetController", "changePerform"]]], "/payment" => new \WHMCS\Payment\PaymentRouteProvider(), "/invoice" => new Invoice\InvoiceRouteProvider(), "/images" => [["name" => "image-display", "method" => ["GET"], "path" => "/{type:\\w+}/{id:\\d+}_{file}", "handle" => ["WHMCS\\ClientArea\\ClientAreaController", "displayImage"]]], "/dismiss" => [["name" => "dismiss-email-verification", "method" => ["POST"], "path" => "/email-verification", "handle" => ["WHMCS\\ClientArea\\ClientAreaController", "dismissEmailVerification"]], ["name" => "dismiss-user-validation", "method" => ["POST"], "path" => "/user-validation", "handle" => ["WHMCS\\ClientArea\\ClientAreaController", "dismissUserValidation"]]], "" => [["name" => "announcement-rss-legacy", "method" => "GET", "path" => "/announcementsrss.php", "handle" => ["\\WHMCS\\Announcement\\Rss", "toXml"]]], "/modules/gateways/callback/validation_com" => new \WHMCS\User\Validation\ValidationCom\Provider\ValidationComClientRouteProvider()];
        if (class_exists("\\WHMCS\\Module\\Gateway\\Stripe\\StripeRouteProvider")) {
            $class = "WHMCS\\Module\\Gateway\\Stripe\\StripeRouteProvider";
            $routes["/stripe"] = new $class();
        }
        if (class_exists("\\WHMCS\\Module\\Gateway\\StripeAch\\StripeAchRouteProvider")) {
            $class = "WHMCS\\Module\\Gateway\\StripeAch\\StripeAchRouteProvider";
            $routes["/stripe_ach"] = new $class();
        }
        if (class_exists("\\WHMCS\\Module\\Gateway\\Paypalcheckout\\PaypalRouteProvider")) {
            $class = "WHMCS\\Module\\Gateway\\Paypalcheckout\\PaypalRouteProvider";
            $routes["/paypal"] = new $class();
        }
        return $routes;
    }

    public function registerRoutes(\FastRoute\RouteCollector $routeCollector)
    {
        $this->addRouteGroups($routeCollector, $this->getRoutes());
    }

    public function register()
    {
    }
}
