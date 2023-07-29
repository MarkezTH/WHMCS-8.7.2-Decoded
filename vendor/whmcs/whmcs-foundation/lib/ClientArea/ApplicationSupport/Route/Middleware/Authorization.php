<?php

namespace WHMCS\ClientArea\ApplicationSupport\Route\Middleware;

class Authorization extends \WHMCS\Security\Middleware\Authorization
{
    protected function responseMissingMultiplePermissions($permissionNames = [])
    {
        return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
    }

    protected function responseMissingPermission($permissionNames = [])
    {
        return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
    }

    protected function assertUserInterface($user = NULL)
    {
        if (!$user instanceof \WHMCS\User\User) {
            throw new \WHMCS\Exception\Authorization\AccessDenied("Authentication Required");
        }
    }

    protected function assertPermissions($user)
    {
        $anyPermission = array_filter($this->getRequireAnyPermission());
        $allPermission = array_filter($this->getRequireAllPermission());
        try {
            foreach ($allPermission as $permissionName) {
                if (!\Auth::hasPermission($permissionName)) {
                    throw new \WHMCS\Exception\Security\MissingAllPermission();
                }
            }
        } catch (\Exception $e) {
            throw new \WHMCS\Exception\Security\MissingAllPermission();
        }
        if (empty($anyPermission)) {
            return NULL;
        }
        $isAllowed = false;
        try {
            foreach ($anyPermission as $permissionName) {
                if (\Auth::hasPermission($permissionName)) {
                    $isAllowed = true;
                }
            }
        } catch (\Exception $e) {
        }
        if (!$isAllowed) {
            throw new \WHMCS\Exception\Security\MissingPermission();
        }
    }
}
