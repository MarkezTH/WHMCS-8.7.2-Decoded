<?php

namespace WHMCS\Api\Licensing;

class ApiLicense
{
    private $errorMessage = NULL;
    const PERMITTED_API_GROUPS_BY_LICENSE_STATUS = NULL;

    protected function isLicensePermissibleForAction($licenseStatus, $action)
    {
        $app = \DI::make("app");
        if ($app->isLocalApiRequest() && $app->isClientAreaRequest()) {
            return true;
        }
        if ($licenseStatus === "Active") {
            return true;
        }
        $licensePermissionData = self::PERMITTED_API_GROUPS_BY_LICENSE_STATUS[strtolower($licenseStatus)] ?? [];
        if (empty($licensePermissionData)) {
            return false;
        }
        $apiActions = \WHMCS\Api\V1\Catalog::get()->getActions();
        $actionGroup = $apiActions[$action]["group"] ?? NULL;
        if (!isset($actionGroup)) {
            return false;
        }
        return in_array($actionGroup, $licensePermissionData["groups"], true);
    }

    protected function getLicense()
    {
        return \DI::make("license");
    }

    protected function getErrorMessageForLicenseStatus($status)
    {
        switch ($status) {
            case "suspended":
                return "Your license key has been suspended";
                break;
            case "pending":
                return "Your license key is still pending (payment not received)";
                break;
            case "expired":
                return "Your license key has expired";
                break;
            default:
                return "Your license key is invalid";
        }
    }

    public function isValidForAction($action)
    {
        $licensing = $this->getLicense();
        $this->errorMessage = NULL;
        if ($licensing->isUnlicensed()) {
            $this->errorMessage = "You must enter a license key to activate and begin using WHMCS";
        } else {
            try {
                $licensing->validate();
                $licenseStatus = $licensing->getStatus();
                if (!$this->isLicensePermissibleForAction($licenseStatus, $action)) {
                    $this->errorMessage = $this->getErrorMessageForLicenseStatus($licenseStatus);
                }
            } catch (\WHMCS\Exception\Http\ConnectionError $e) {
                $this->errorMessage = "WHMCS has not been able to verify your license for the last few days";
            } catch (\WHMCS\Exception $e) {
                $this->errorMessage = $e->getMessage() ?: "WHMCS has not been able to verify your license";
            }
        }
        return is_null($this->errorMessage);
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }
}
