<?php

namespace WHMCS\Admin\User;

class UserRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        return ["/admin/user" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization();
        }], ["method" => ["GET"], "name" => "admin-user-list", "path" => "/list", "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["List Users"]);
        }, "handle" => ["WHMCS\\Admin\\User\\UserController", "list"]], ["method" => ["POST"], "name" => "admin-user-search", "path" => "/list", "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["List Users"]);
        }, "handle" => ["WHMCS\\Admin\\User\\UserController", "search"]], ["method" => ["POST"], "name" => "admin-user-manage", "path" => "/manage/{userId:\\d+}", "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["Manage Users"]);
        }, "handle" => ["WHMCS\\Admin\\User\\UserController", "manage"]], ["method" => ["POST"], "name" => "admin-user-manage-save", "path" => "/manage/{userId:\\d+}/save", "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["Manage Users"]);
        }, "handle" => ["WHMCS\\Admin\\User\\UserController", "save"]], ["method" => ["POST"], "name" => "admin-user-security-question", "path" => "/{userId:\\d+}/security/question", "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["View Account Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\User\\UserController", "securityQuestion"]], ["method" => ["POST"], "name" => "admin-user-password-reset", "path" => "/{userId:\\d+}/password/reset", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\User\\UserController", "passwordReset"]], ["method" => ["POST"], "name" => "admin-user-manage-delete", "path" => "/manage/{userId:\\d+}/delete", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Delete Users"]);
        }, "handle" => ["WHMCS\\Admin\\User\\UserController", "doDelete"]]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-user-";
    }
}
