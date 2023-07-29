<?php

namespace WHMCS\ClientArea\User;

class UserRouteProvider implements \WHMCS\Route\Contracts\ProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    public function getRoutes()
    {
        $userRoutes = ["/user" => ["attributes" => ["authorization" => function () {
            return new \WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization();
        }], ["method" => ["GET"], "name" => "user-profile", "path" => "/profile", "handle" => ["WHMCS\\ClientArea\\User\\UserController", "profile"]], ["method" => ["POST"], "name" => "user-profile-save", "path" => "/profile/save", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\User\\UserController", "saveProfile"]], ["method" => ["POST"], "name" => "user-profile-email-save", "path" => "/profile/email/save", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\User\\UserController", "saveProfileEmail"]], ["method" => ["GET"], "name" => "user-accounts", "path" => "/accounts", "handle" => ["WHMCS\\ClientArea\\User\\UserController", "accounts"]], ["method" => ["GET"], "name" => "user-account-switch-forced", "path" => "/accounts/forced", "handle" => ["WHMCS\\ClientArea\\User\\UserController", "accountsSwitchForced"]], ["method" => ["POST"], "name" => "user-accounts-switch", "path" => "/accounts", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\User\\UserController", "accountSwitch"]], ["method" => ["GET"], "name" => "user-password", "path" => "/password", "handle" => ["WHMCS\\ClientArea\\User\\UserController", "password"]], ["method" => ["POST"], "name" => "user-password", "path" => "/password", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\User\\UserController", "savePassword"]], ["method" => ["GET"], "name" => "user-security", "path" => "/security", "handle" => ["WHMCS\\ClientArea\\User\\UserController", "security"]], ["method" => ["POST"], "name" => "user-security-question", "path" => "/security/question", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\User\\UserController", "saveSecurityQuestion"]], ["method" => ["GET"], "name" => "user-email-verification", "path" => "/verify/{token}", "handle" => ["WHMCS\\ClientArea\\User\\UserController", "verification"]], ["method" => ["POST"], "name" => "user-email-verification-resend", "path" => "/verification/resend", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\User\\UserController", "verificationResend"]], ["method" => ["GET"], "name" => "user-confirm-email", "path" => "/confirm/{token}", "handle" => ["WHMCS\\ClientArea\\User\\UserController", "confirmEmail"]], ["method" => ["GET"], "name" => "user-permission-denied", "path" => "/access-denied", "handle" => ["WHMCS\\ClientArea\\User\\UserController", "accessDenied"]]]];
        return $userRoutes;
    }

    public function registerRoutes(\FastRoute\RouteCollector $routeCollector)
    {
        $this->addRouteGroups($routeCollector, $this->getRoutes());
    }
}
