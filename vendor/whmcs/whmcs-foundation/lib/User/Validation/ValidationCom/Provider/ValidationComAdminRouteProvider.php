<?php

namespace WHMCS\User\Validation\ValidationCom\Provider;

class ValidationComAdminRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    protected function getRoutes()
    {
        return ["/admin/validation_com" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization();
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "token-status", "path" => "/token/status", "handle" => ["WHMCS\\User\\Validation\\ValidationCom\\ValidationComController", "tokenStatus"], "authentication" => "admin", "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["View Order Details"]);
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "token-generate", "path" => "/token/generate", "handle" => ["WHMCS\\User\\Validation\\ValidationCom\\ValidationComController", "tokenGenerate"], "authentication" => "admin", "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["View Order Details"]);
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "token-delete", "path" => "/token/delete", "handle" => ["WHMCS\\User\\Validation\\ValidationCom\\ValidationComController", "tokenDelete"], "authentication" => "admin", "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["View Order Details"]);
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "deactivate", "path" => "/deactivate", "handle" => ["WHMCS\\User\\Validation\\ValidationCom\\ValidationComController", "deactivateService"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["Configure Fraud Protection"]);
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "configure", "path" => "/configure", "handle" => ["WHMCS\\User\\Validation\\ValidationCom\\ValidationComController", "configureModal"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["Configure Fraud Protection"]);
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "configure-save", "path" => "/configure/save", "handle" => ["WHMCS\\User\\Validation\\ValidationCom\\ValidationComController", "configureSave"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["Configure Fraud Protection"]);
        }], ["method" => ["POST"], "name" => $this->getDeferredRoutePathNameAttribute() . "signup", "path" => "/signup", "handle" => ["WHMCS\\User\\Validation\\ValidationCom\\ValidationComController", "signup"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["Configure Fraud Protection"]);
        }]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-validation_com-";
    }
}
