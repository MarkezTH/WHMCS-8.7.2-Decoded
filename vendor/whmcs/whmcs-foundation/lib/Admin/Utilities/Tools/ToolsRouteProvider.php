<?php

namespace WHMCS\Admin\Utilities\Tools;

class ToolsRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/utilities/tools" => [["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "serversync-analyse", "path" => "/serversync/{serverid}", "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\ServerSync\\Controller", "analyse"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["WHM Import Script"]);
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "serversync-review", "path" => "/serversync/{serverid}/process", "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\ServerSync\\Controller", "process"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["WHM Import Script"]);
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "email-marketer-rule", "path" => "/email-marketer/manage[/{id:\\d+}]", "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailMarketer\\Controller", "manage"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Email Marketer"]);
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "email-marketer-rule-save", "path" => "/email-marketer/save", "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailMarketer\\Controller", "save"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Email Marketer"]);
        }], ["method" => ["GET"], "name" => "admin-utilities-tools-tld-import-step-one", "path" => "/tldsync/import", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Configure Domain Pricing"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\TldSync\\TldSyncController", "importStart"]], ["method" => ["POST"], "name" => "admin-utilities-tools-tld-import-step-two", "path" => "/tldsync/import[/{registrar:\\w+}]", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Configure Domain Pricing"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\TldSync\\TldSyncController", "importLoad"]], ["method" => ["POST"], "name" => "admin-utilities-tools-tld-import-do", "path" => "/tldsync/do-import", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Configure Domain Pricing"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\TldSync\\TldSyncController", "importTlds"]], ["method" => ["GET", "POST"], "name" => "admin-utilities-tools-email-campaigns", "path" => "/email/campaigns", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Mass Mail"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailCampaigns\\Controller", "manager"]], ["method" => ["POST"], "name" => "admin-utilities-tools-email-campaigns-pause", "path" => "/email/campaigns/pause", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Mass Mail"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailCampaigns\\Controller", "pause"]], ["method" => ["POST"], "name" => "admin-utilities-tools-email-campaigns-resume", "path" => "/email/campaigns/resume", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Mass Mail"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailCampaigns\\Controller", "resume"]], ["method" => ["POST"], "name" => "admin-utilities-tools-email-campaigns-delete", "path" => "/email/campaigns/delete", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Mass Mail"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailCampaigns\\Controller", "delete"]], ["method" => ["POST"], "name" => "admin-utilities-tools-email-campaigns-report", "path" => "/email/campaigns/report/{id:\\d+}", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Mass Mail"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailCampaigns\\Controller", "report"]], ["method" => ["POST"], "name" => "admin-utilities-tools-email-campaigns-preview", "path" => "/email/campaigns/preview", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Mass Mail"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailCampaigns\\Controller", "preview"]], ["method" => ["POST"], "name" => "admin-utilities-tools-email-campaigns-preview-show", "path" => "/email/campaigns/preview/show", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Mass Mail"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailCampaigns\\Controller", "showPreview"]], ["method" => ["POST"], "name" => "admin-utilities-tools-email-campaigns-preview-campaign", "path" => "/email/campaigns/preview/{id:\\d+}", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Mass Mail"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailCampaigns\\Controller", "previewCampaign"]], ["method" => ["POST"], "name" => "admin-utilities-tools-email-campaigns-retry-single-email", "path" => "/email/campaigns/retry/email", "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Mass Mail"]);
        }, "handle" => ["WHMCS\\Admin\\Utilities\\Tools\\EmailCampaigns\\Controller", "retry"]]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-utilities-tools-";
    }
}
