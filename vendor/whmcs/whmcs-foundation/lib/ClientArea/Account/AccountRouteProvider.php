<?php

namespace WHMCS\ClientArea\Account;

class AccountRouteProvider implements \WHMCS\Route\Contracts\ProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    public function getRoutes()
    {
        $helpRoutes = ["/account" => ["attributes" => ["authentication" => "clientarea", "authorization" => function () {
            return new \WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization();
        }], ["method" => ["GET"], "name" => "account-index", "path" => "", "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "index"]], ["method" => ["GET"], "name" => "account-users", "path" => "/users", "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "users"]], ["method" => ["GET"], "name" => "account-users-permissions", "path" => "/users/{userid}/permissions", "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "userPermissions"]], ["method" => ["POST"], "name" => "account-users-permissions-save", "path" => "/users/{userid}/permissions", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "saveUserPermissions"]], ["method" => ["POST"], "name" => "account-users-remove", "path" => "/users/remove", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "removeUser"]], ["method" => ["POST"], "name" => "account-users-invite", "path" => "/users/invite", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "invite"]], ["method" => ["POST"], "name" => "account-users-invite-resend", "path" => "/users/invite/resend", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "inviteResend"]], ["method" => ["POST"], "name" => "account-users-invite-cancel", "path" => "/users/invite/cancel", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }, "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "inviteCancel"]], ["method" => ["GET", "POST"], "name" => "account-contacts", "path" => "/contacts", "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "contacts"], "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["contacts"]);
        }], ["method" => ["POST"], "name" => "account-contacts-save", "path" => "/contacts/save", "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "contactSave"], "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["contacts"])->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "account-contacts-new", "path" => "/contacts/new", "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "contactNew"], "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["contacts"])->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "account-contacts-delete", "path" => "/contacts/delete", "handle" => ["WHMCS\\ClientArea\\Account\\AccountController", "contactDelete"], "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["contacts"])->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "account-security-two-factor-enable", "path" => "/security/two-factor/enable", "handle" => ["WHMCS\\Authentication\\TwoFactor\\TwoFactorController", "enable"]], ["method" => ["GET", "POST"], "name" => "account-security-two-factor-enable-configure", "path" => "/security/two-factor/enable/configure", "handle" => ["WHMCS\\Authentication\\TwoFactor\\TwoFactorController", "configure"], "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "account-security-two-factor-enable-verify", "path" => "/security/two-factor/enable/verify", "handle" => ["WHMCS\\Authentication\\TwoFactor\\TwoFactorController", "verify"], "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["POST"], "name" => "account-security-two-factor-disable", "path" => "/security/two-factor/disable", "handle" => ["WHMCS\\Authentication\\TwoFactor\\TwoFactorController", "disable"]], ["method" => ["POST"], "name" => "account-security-two-factor-disable-confirm", "path" => "/security/two-factor/disable/confirm", "handle" => ["WHMCS\\Authentication\\TwoFactor\\TwoFactorController", "disableConfirm"], "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken();
        }], ["method" => ["GET"], "name" => "account-paymentmethods", "path" => "/paymentmethods", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["invoices"]);
        }, "handle" => ["WHMCS\\ClientArea\\Account\\PaymentMethodsController", "index"]], ["method" => ["GET"], "name" => "account-paymentmethods-add", "path" => "/paymentmethods/add", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["invoices"]);
        }, "handle" => ["WHMCS\\ClientArea\\Account\\PaymentMethodsController", "add"]], ["method" => ["POST"], "name" => "account-paymentmethods-add", "path" => "/paymentmethods/add", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["invoices"]);
        }, "handle" => ["WHMCS\\ClientArea\\Account\\PaymentMethodsController", "create"]], ["method" => ["POST"], "name" => "account-paymentmethods-inittoken", "path" => "/paymentmethods/inittoken", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["invoices"]);
        }, "handle" => ["WHMCS\\ClientArea\\Account\\PaymentMethodsController", "initToken"]], ["method" => ["GET"], "name" => "account-paymentmethods-view", "path" => "/paymentmethods/{id:\\d+}", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["invoices"]);
        }, "handle" => ["WHMCS\\ClientArea\\Account\\PaymentMethodsController", "manage"]], ["method" => ["POST"], "name" => "account-paymentmethods-setdefault", "path" => "/paymentmethods/{id:\\d+}/default", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["invoices"]);
        }, "handle" => ["WHMCS\\ClientArea\\Account\\PaymentMethodsController", "setDefault"]], ["method" => ["POST"], "name" => "account-paymentmethods-delete", "path" => "/paymentmethods/{id:\\d+}/delete", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["invoices"]);
        }, "handle" => ["WHMCS\\ClientArea\\Account\\PaymentMethodsController", "delete"]], ["method" => ["POST"], "name" => "account-paymentmethods-save", "path" => "/paymentmethods/{id:\\d+}", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["invoices"]);
        }, "handle" => ["WHMCS\\ClientArea\\Account\\PaymentMethodsController", "save"]], ["method" => ["GET"], "name" => "account-paymentmethods-billing-contacts", "path" => "/paymentmethods-billing-contacts[/{id:\\d+}]", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["invoices"]);
        }, "handle" => ["WHMCS\\ClientArea\\Account\\PaymentMethodsController", "getBillingContacts"]], ["method" => ["POST"], "name" => "account-paymentmethods-billing-contacts-create", "path" => "/paymentmethods-billing-contacts/create", "authorization" => function (\WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["contacts", "invoices"]);
        }, "handle" => ["WHMCS\\ClientArea\\Account\\PaymentMethodsController", "createBillingContact"]]]];
        return $helpRoutes;
    }

    public function registerRoutes(\FastRoute\RouteCollector $routeCollector)
    {
        $this->addRouteGroups($routeCollector, $this->getRoutes());
    }
}
