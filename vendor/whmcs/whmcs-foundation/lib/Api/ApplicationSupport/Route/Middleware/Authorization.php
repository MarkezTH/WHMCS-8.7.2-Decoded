<?php

namespace WHMCS\Api\ApplicationSupport\Route\Middleware;

class Authorization extends \WHMCS\Security\Middleware\Authorization
{
    public function assertAuthorization(\WHMCS\Http\Message\ServerRequest $request, $user = NULL)
    {
        if (!$request instanceof \WHMCS\Api\ApplicationSupport\Http\ServerRequest) {
            throw new \WHMCS\Exception\HttpCodeException("Invalid server request instance", 500);
        }
        $baseCheck = parent::assertAuthorization($request, $user);
        if ($baseCheck instanceof \Psr\Http\Message\ResponseInterface) {
            return $baseCheck;
        }
        $action = $request->getAction();
        if (!$action) {
            throw new \WHMCS\Exception\HttpCodeException("Empty action request", 400);
        }
        $device = $request->getAttribute("authenticatedDevice", NULL);
        if ($device) {
            if (!$device->permissions()->isAllowed($action)) {
                if (!array_key_exists($action, \WHMCS\Api\V1\Catalog::get()->getActions())) {
                    return $this->responseActionInvalid($action);
                }
                return $this->responseActionNotAllowed($action);
            }
        } else {
            $admin = $request->getAttribute("authenticatedUser", NULL);
            if (!$admin || !$admin->hasPermission("API Access")) {
                throw new \WHMCS\Exception\Api\AuthException("Access Denied");
            }
        }
        return $request;
    }

    public function hasValidCsrfToken()
    {
        return true;
    }

    protected function responseActionNotAllowed($action)
    {
        throw new \WHMCS\Exception\Authorization\AccessDenied("Invalid Permissions: API action \"" . $action . "\" is not allowed");
    }

    protected function responseActionInvalid($action)
    {
        throw new \WHMCS\Exception\Authorization\AccessDenied("Invalid API Action: \"" . $action . "\" is not a valid API action");
    }
}
