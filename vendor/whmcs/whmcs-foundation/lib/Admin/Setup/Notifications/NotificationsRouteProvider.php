<?php

namespace WHMCS\Admin\Setup\Notifications;

class NotificationsRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/setup/notifications" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Notifications"])->requireCsrfToken();
        }], ["method" => ["GET", "POST"], "name" => "admin-setup-notifications-overview", "path" => "/overview", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "index"]], ["method" => ["GET"], "name" => "admin-setup-notifications-list", "path" => "/list", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "listNotifications"]], ["method" => ["POST"], "name" => "admin-setup-notifications-rule-create", "path" => "/rule", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "manageRule"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Notifications"]);
        }], ["method" => ["POST"], "name" => "admin-setup-notifications-rule-delete", "path" => "/rule/delete", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "deleteRule"]], ["method" => ["POST"], "name" => "admin-setup-notifications-rule-duplicate", "path" => "/rule/duplicate/{rule_id}", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "duplicateRule"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Notifications"]);
        }], ["method" => ["POST"], "name" => "admin-setup-notifications-rule-status", "path" => "/rule/status", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "setRuleStatus"]], ["method" => ["POST"], "name" => "admin-setup-notifications-rule-save", "path" => "/rule/save", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "saveRule"]], ["method" => ["POST"], "name" => "admin-setup-notifications-rule-edit", "path" => "/rule/{rule_id}", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "manageRule"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Notifications"]);
        }], ["method" => ["POST"], "name" => "admin-setup-notifications-provider-dynamic-field", "path" => "/provider/field", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "getDynamicField"]], ["method" => ["POST"], "name" => "admin-setup-notifications-provider-disable", "path" => "/provider/disable", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "disableProvider"]], ["method" => ["POST"], "name" => "admin-setup-notifications-provider", "path" => "/provider/{provider}", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "manageProvider"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Notifications"]);
        }], ["method" => ["POST"], "name" => "admin-setup-notifications-provider-save", "path" => "/provider/{provider}/save", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "saveProvider"]], ["method" => ["GET"], "name" => "admin-setup-notifications-providers-status", "path" => "/providers/status", "handle" => ["WHMCS\\Admin\\Setup\\Notifications\\NotificationsController", "getProvidersStatus"]]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-setup-notifications-";
    }
}
