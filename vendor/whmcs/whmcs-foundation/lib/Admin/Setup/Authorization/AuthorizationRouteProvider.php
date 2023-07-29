<?php

namespace WHMCS\Admin\Setup\Authorization;

class AuthorizationRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/setup/authz" => ["attributes" => ["authentication" => "adminConfirmation", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage API Credentials"])->requireCsrfToken();
        }], ["method" => ["GET", "POST"], "name" => "admin-setup-authz-api-manage", "path" => "/api/manage", "handle" => ["WHMCS\\Authentication\\DeviceConfigurationController", "index"]], ["method" => ["GET", "POST"], "name" => "admin-setup-authz-api-device-new", "path" => "/api/devices/new", "handle" => ["WHMCS\\Authentication\\DeviceConfigurationController", "createNew"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage API Credentials"]);
        }], ["method" => ["POST"], "name" => "admin-setup-authz-api-devices-generate", "path" => "/api/devices/generate", "handle" => ["WHMCS\\Authentication\\DeviceConfigurationController", "generate"]], ["method" => ["GET"], "name" => "admin-setup-authz-api-devices-list", "path" => "/api/devices", "handle" => ["WHMCS\\Authentication\\DeviceConfigurationController", "getDevices"]], ["method" => ["POST"], "name" => "admin-setup-authz-api-devices-delete", "path" => "/api/devices/delete[/{id}]", "handle" => ["WHMCS\\Authentication\\DeviceConfigurationController", "delete"]], ["method" => ["POST"], "name" => "admin-setup-authz-api-devices-update", "path" => "/api/devices/update[/{id}]", "handle" => ["WHMCS\\Authentication\\DeviceConfigurationController", "update"]], ["method" => ["GET", "POST"], "name" => "admin-setup-authz-api-devices-manage", "path" => "/api/devices/manage[/{id}]", "handle" => ["WHMCS\\Authentication\\DeviceConfigurationController", "manage"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage API Credentials"]);
        }], ["method" => ["GET"], "name" => "admin-setup-authz-api-roles-list", "path" => "/api/roles", "handle" => ["WHMCS\\Admin\\Setup\\Authorization\\Api\\RoleController", "listRoles"]], ["method" => ["GET"], "name" => "admin-setup-authz-api-roles-select-options", "path" => "/api/roles/select-options", "handle" => ["WHMCS\\Admin\\Setup\\Authorization\\Api\\RoleController", "selectOptions"]], ["method" => ["GET", "POST"], "name" => "admin-setup-authz-api-roles-manage", "path" => "/api/roles/manage[/{roleId}]", "handle" => ["WHMCS\\Admin\\Setup\\Authorization\\Api\\RoleController", "manage"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage API Credentials"]);
        }], ["method" => ["POST"], "name" => "admin-setup-authz-api-roles-create", "path" => "/api/roles/create", "handle" => ["WHMCS\\Admin\\Setup\\Authorization\\Api\\RoleController", "create"]], ["method" => ["POST"], "name" => "admin-setup-authz-api-roles-delete", "path" => "/api/roles/delete[/{roleId}]", "handle" => ["WHMCS\\Admin\\Setup\\Authorization\\Api\\RoleController", "delete"]], ["method" => ["POST"], "name" => "admin-setup-authz-api-roles-update", "path" => "/api/roles/update", "handle" => ["WHMCS\\Admin\\Setup\\Authorization\\Api\\RoleController", "update"]]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-setup-authz-";
    }
}
