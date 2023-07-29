<?php

namespace WHMCS\Admin\Setup\General;

class MailRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        return ["/admin/setup/mail" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Configure General Settings"]);
        }], ["method" => ["POST"], "name" => "admin-setup-mail-providers", "path" => "", "handle" => ["WHMCS\\Admin\\Setup\\General\\MailController", "mailProviders"]], ["method" => ["POST"], "name" => "admin-setup-mail-provider-configuration", "path" => "/configuration", "handle" => ["WHMCS\\Admin\\Setup\\General\\MailController", "mailProviderConfiguration"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-mail-provider-save", "path" => "/save", "handle" => ["WHMCS\\Admin\\Setup\\General\\MailController", "mailProviderSave"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "admin-setup-mail-provider-configuration-test", "path" => "/test", "handle" => ["WHMCS\\Admin\\Setup\\General\\MailController", "mailProviderConfigurationTest"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["GET"], "name" => "admin-setup-mail-provider-oauth2-callback", "path" => "/oauth2/callback", "handle" => ["WHMCS\\Admin\\Setup\\General\\MailController", "oauth2Callback"]], ["method" => ["POST"], "name" => "admin-setup-mail-provider-oauth2-get-auth-url", "path" => "/oauth2/get_auth_url", "handle" => ["WHMCS\\Admin\\Setup\\General\\MailController", "oauth2GetRedirectUrl"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-setup-mail-";
    }
}
