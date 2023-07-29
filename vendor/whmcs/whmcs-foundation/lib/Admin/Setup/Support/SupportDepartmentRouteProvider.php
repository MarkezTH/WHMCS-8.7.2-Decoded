<?php

namespace WHMCS\Admin\Setup\Support;

class SupportDepartmentRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        return ["/admin/setup/support" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Configure Support Departments"]);
        }], ["method" => ["GET"], "name" => "admin-setup-support-oauth2-callback", "path" => "/oauth2/callback", "handle" => ["WHMCS\\Admin\\Setup\\Support\\SupportDepartmentController", "oauth2Callback"]], ["method" => ["POST"], "name" => "admin-setup-support-oauth2-get-auth-url", "path" => "/oauth2/get_auth_url", "handle" => ["WHMCS\\Admin\\Setup\\Support\\SupportDepartmentController", "oauth2GetRedirectUrl"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-support-test-mail-connection", "path" => "/mail/test_connection", "handle" => ["WHMCS\\Admin\\Setup\\Support\\SupportDepartmentController", "testMailConnection"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-setup-support-";
    }
}
