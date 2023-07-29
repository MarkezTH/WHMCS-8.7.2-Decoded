<?php

namespace WHMCS\Admin\Setup\Authentication\Client;

class RemoteAuthRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $remoteAuthRoutes = ["/admin/setup/authn" => ["attributes" => ["authentication" => "adminConfirmation", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Configure Sign-In Integration"])->requireCsrfToken();
        }], ["method" => ["GET", "POST"], "name" => "admin-setup-authn-view", "path" => "/view", "handle" => ["\\WHMCS\\Admin\\Setup\\Authentication\\Client\\RemoteProviderController", "viewProviderSettings"]], ["method" => ["POST"], "name" => "admin-setup-authn-deactivate", "path" => "/deactivate", "handle" => ["\\WHMCS\\Admin\\Setup\\Authentication\\Client\\RemoteProviderController", "deactivate"]], ["method" => ["POST"], "name" => "admin-setup-authn-activate", "path" => "/activate", "handle" => ["\\WHMCS\\Admin\\Setup\\Authentication\\Client\\RemoteProviderController", "activate"]], ["method" => ["POST"], "name" => "admin-setup-authn-delete_account_link", "path" => "/delete_account_link", "handle" => ["\\WHMCS\\Admin\\Setup\\Authentication\\Client\\RemoteProviderController", "deleteAccountLink"], "authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAnyPermission(["Edit Clients Details"])->requireCsrfToken();
        }]]];
        return $remoteAuthRoutes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-setup-authn-";
    }
}
