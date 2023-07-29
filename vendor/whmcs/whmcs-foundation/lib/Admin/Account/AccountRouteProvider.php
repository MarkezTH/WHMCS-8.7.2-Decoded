<?php

namespace WHMCS\Admin\Account;

class AccountRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/account" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["My Account"]);
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "security-two-factor-enable", "path" => "/security/two-factor/enable", "handle" => ["WHMCS\\Admin\\Account\\TwoFactorController", "enable"]], ["method" => ["GET", "POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "security-two-factor-enable-configure", "path" => "/security/two-factor/enable/configure", "handle" => ["WHMCS\\Admin\\Account\\TwoFactorController", "configure"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["My Account"])->requireCsrfToken();
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "security-two-factor-enable-verify", "path" => "/security/two-factor/enable/verify", "handle" => ["WHMCS\\Admin\\Account\\TwoFactorController", "verify"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["My Account"])->requireCsrfToken();
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "security-two-factor-disable", "path" => "/security/two-factor/disable", "handle" => ["WHMCS\\Admin\\Account\\TwoFactorController", "disable"]], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "security-two-factor-disable-confirm", "path" => "/security/two-factor/disable/confirm", "handle" => ["WHMCS\\Admin\\Account\\TwoFactorController", "disableConfirm"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["My Account"])->requireCsrfToken();
        }]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-account-";
    }
}
