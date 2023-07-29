<?php

namespace WHMCS\Admin\Logs;

class LogsRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/logs" => ["attributes" => ["authorization" => function () {
            return new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization();
        }], ["method" => ["GET", "POST"], "name" => "admin-logs-module-log", "path" => "/module-log", "handle" => ["WHMCS\\Admin\\Logs\\ModuleLog\\Controller", "index"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $adminAuthz) {
            return $adminAuthz->setRequireAllPermission(["View Module Debug Log"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-logs-module-log-paged", "path" => "/module-log/page/{page:\\d+}", "handle" => ["WHMCS\\Admin\\Logs\\ModuleLog\\Controller", "index"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $adminAuthz) {
            return $adminAuthz->setRequireAllPermission(["View Module Debug Log"]);
        }], ["method" => ["POST"], "name" => "admin-logs-module-log-enable-disable", "path" => "/module-log/toggle", "handle" => ["WHMCS\\Admin\\Logs\\ModuleLog\\Controller", "toggleLogging"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $adminAuthz) {
            return $adminAuthz->setRequireAllPermission(["View Module Debug Log"])->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-logs-module-log-clear", "path" => "/module-log/clear", "handle" => ["WHMCS\\Admin\\Logs\\ModuleLog\\Controller", "clearLog"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $adminAuthz) {
            return $adminAuthz->setRequireAllPermission(["View Module Debug Log"])->requireCsrfToken();
        }], ["method" => ["GET"], "name" => "admin-logs-module-log-single-view", "path" => "/module-log/view/{logId:\\d+}", "handle" => ["WHMCS\\Admin\\Logs\\ModuleLog\\Controller", "viewSingleEntry"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $adminAuthz) {
            return $adminAuthz->setRequireAllPermission(["View Module Debug Log"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-logs-mail-import-log", "path" => "/system-mail-import-log", "handle" => ["WHMCS\\Admin\\Logs\\MailImport\\Controller", "index"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $adminAuthz) {
            return $adminAuthz->setRequireAllPermission(["View Ticket Mail Import Log"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-logs-mail-import-paged", "path" => "/system-mail-import-log/page/{page:\\d+}", "handle" => ["WHMCS\\Admin\\Logs\\MailImport\\Controller", "index"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $adminAuthz) {
            return $adminAuthz->setRequireAllPermission(["View Ticket Mail Import Log"])->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-logs-mail-import-view", "path" => "/system-mail-import-log/record/{id:\\d+}", "handle" => ["WHMCS\\Admin\\Logs\\MailImport\\Controller", "viewMessage"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $adminAuthz) {
            return $adminAuthz->setRequireAllPermission(["View Ticket Mail Import Log"]);
        }], ["method" => ["POST"], "name" => "admin-logs-mail-import-importnow", "path" => "/system-mail-import-log/import/{id:\\d+}", "handle" => ["WHMCS\\Admin\\Logs\\MailImport\\Controller", "importNow"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $adminAuthz) {
            return $adminAuthz->setRequireAllPermission(["View Ticket Mail Import Log"])->requireCsrfToken();
        }]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-logs-";
    }
}
