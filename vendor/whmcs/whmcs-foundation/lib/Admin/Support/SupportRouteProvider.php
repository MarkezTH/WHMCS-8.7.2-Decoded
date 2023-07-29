<?php

namespace WHMCS\Admin\Support;

class SupportRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        return ["/admin/support" => [["method" => ["POST"], "name" => "admin-support-ticket-open-additional-data", "path" => "/ticket/open/client/{clientId:\\d+}/additional/data", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Open New Ticket"])->requireCsrfToken();
        }, "handle" => ["WHMCS\\Admin\\Support\\SupportController", "getAdditionalData"]], ["method" => ["POST"], "name" => "admin-support-ticket-related-list", "path" => "/ticket/{ticketId:\\d+}/client/{clientId:\\d+}/services", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["View Support Ticket"])->requireCsrfToken();
        }, "handle" => ["WHMCS\\Admin\\Support\\SupportController", "getClientServices"]], ["method" => ["POST"], "name" => "admin-support-ticket-set-related-service", "path" => "/ticket/{ticketId:\\d+}/client/{clientId:\\d+}/services/save", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["View Support Ticket"])->requireCsrfToken();
        }, "handle" => ["WHMCS\\Admin\\Support\\SupportController", "setRelatedService"]]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-support-";
    }
}
