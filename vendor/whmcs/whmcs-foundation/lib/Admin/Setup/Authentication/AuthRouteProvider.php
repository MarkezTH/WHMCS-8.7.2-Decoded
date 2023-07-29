<?php

namespace WHMCS\Admin\Setup\Authentication;

class AuthRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $authRoutes = ["/admin/setup/auth" => ["attributes" => ["authentication" => "adminConfirmation", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Configure Two-Factor Authentication"]);
        }], ["method" => ["GET", "POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "two-factor-index", "path" => "/two-factor", "handle" => ["\\WHMCS\\Admin\\Setup\\Authentication\\TwoFactorAuthController", "index"]], ["method" => ["GET"], "name" => $this->getDeferredRoutePathNameAttribute() . "two-factor-status", "path" => "/two-factor/status", "handle" => ["\\WHMCS\\Admin\\Setup\\Authentication\\TwoFactorAuthController", "status"]], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "two-factor-settings-save", "path" => "/two-factor/save", "handle" => ["\\WHMCS\\Admin\\Setup\\Authentication\\TwoFactorAuthController", "saveSettings"]], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "two-factor-configure", "path" => "/two-factor/{module}/configure", "handle" => ["\\WHMCS\\Admin\\Setup\\Authentication\\TwoFactorAuthController", "configureModule"]], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "two-factor-configure-save", "path" => "/two-factor/{module}/configure/save", "handle" => ["\\WHMCS\\Admin\\Setup\\Authentication\\TwoFactorAuthController", "saveModule"]]]];
        return $authRoutes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-setup-auth-";
    }
}
