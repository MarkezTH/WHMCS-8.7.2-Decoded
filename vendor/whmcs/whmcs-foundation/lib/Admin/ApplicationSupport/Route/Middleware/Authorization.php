<?php

namespace WHMCS\Admin\ApplicationSupport\Route\Middleware;

class Authorization extends \WHMCS\Security\Middleware\Authorization
{
    public function getDefaultCsrfNamespace()
    {
        return "WHMCS.admin.default";
    }

    protected function responseMissingMultiplePermissions($permissionNames = [])
    {
        return (new \WHMCS\Admin\ApplicationSupport\Http\Message\ResponseFactory())->missingPermission($this->getRequest(), $permissionNames, true);
    }

    protected function responseMissingPermission($permissionNames = [])
    {
        return (new \WHMCS\Admin\ApplicationSupport\Http\Message\ResponseFactory())->missingPermission($this->getRequest(), $permissionNames, false);
    }

    protected function responseInvalidCsrfToken()
    {
        return (new \WHMCS\Admin\ApplicationSupport\Http\Message\ResponseFactory())->invalidCsrfToken($this->getRequest());
    }
}
