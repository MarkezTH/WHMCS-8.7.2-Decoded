<?php

namespace WHMCS\Admin\Setup\Storage;

class StorageRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $storageRoutes = ["/admin/setup/storage" => ["attributes" => ["authentication" => "adminConfirmation", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Storage Settings"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-setup-storage-index", "path" => "/index[/{action}]", "handle" => ["WHMCS\\Admin\\Setup\\Storage\\StorageController", "index"]], ["method" => ["GET", "POST"], "name" => "admin-setup-storage-edit-configuration", "path" => "/config/{id}/edit", "handle" => ["WHMCS\\Admin\\Setup\\Storage\\StorageController", "editConfiguration"]], ["method" => ["POST"], "name" => "admin-setup-storage-save-configuration", "path" => "/config/{id}/save", "handle" => ["WHMCS\\Admin\\Setup\\Storage\\StorageController", "saveConfiguration"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-storage-duplicate-configuration", "path" => "/config/{id}/duplicate", "handle" => ["WHMCS\\Admin\\Setup\\Storage\\StorageController", "duplicateConfiguration"]], ["method" => ["POST"], "name" => "admin-setup-storage-test-configuration", "path" => "/config/{id}/test", "handle" => ["WHMCS\\Admin\\Setup\\Storage\\StorageController", "testConfiguration"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-storage-delete-configuration", "path" => "/config/{id}/delete", "handle" => ["WHMCS\\Admin\\Setup\\Storage\\StorageController", "deleteConfiguration"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-storage-dismiss-error", "path" => "/config/{id}/dismiss_error", "handle" => ["WHMCS\\Admin\\Setup\\Storage\\StorageController", "dismissError"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-storage-migration-start", "path" => "/migration/{asset_type}/start", "handle" => ["WHMCS\\Admin\\Setup\\Storage\\StorageController", "startMigration"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-storage-migration-switch", "path" => "/migration/{asset_type}/switch", "handle" => ["WHMCS\\Admin\\Setup\\Storage\\StorageController", "switchAssetStorage"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-storage-migration-cancel", "path" => "/migration/{asset_type}/cancel", "handle" => ["WHMCS\\Admin\\Setup\\Storage\\StorageController", "cancelMigration"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }]]];
        return $storageRoutes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-setup-storage-";
    }
}
