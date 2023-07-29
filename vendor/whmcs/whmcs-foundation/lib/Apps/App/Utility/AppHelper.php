<?php

namespace WHMCS\Apps\App\Utility;

class AppHelper
{
    protected $excludedFromActiveApps = ["servers.marketconnect"];

    public function isExcludedFromActiveList($appKey)
    {
        return in_array($appKey, $this->excludedFromActiveApps);
    }

    public function getNonModuleActivationForms($moduleType, $moduleName)
    {
        switch ($moduleType) {
            case "marketconnect":
                return [(new \WHMCS\View\Form())->setUriPrefixAdminBaseUrl("marketconnect.php")->setMethod(\WHMCS\View\Form::METHOD_GET)->setParameters(["activate" => $moduleName])->setSubmitLabel(\AdminLang::trans("global.activate"))];
                break;
            case "signin":
                return [(new \WHMCS\View\Form())->setUriByRoutePath("admin-setup-authn-view")->setMethod(\WHMCS\View\Form::METHOD_POST)->setParameters(["activate" => $moduleName])->setSubmitLabel(\AdminLang::trans("global.activate"))];
                break;
            default:
                throw new \WHMCS\Exception\Module\NotImplemented();
        }
    }

    public function getNonModuleManagementForms($moduleType, $moduleName)
    {
        switch ($moduleType) {
            case "marketconnect":
                return [(new \WHMCS\View\Form())->setUriPrefixAdminBaseUrl("marketconnect.php")->setMethod(\WHMCS\View\Form::METHOD_GET)->setParameters(["manage" => $moduleName])->setSubmitLabel(\AdminLang::trans("global.manage"))];
                break;
            case "signin":
                return [(new \WHMCS\View\Form())->setUriByRoutePath("admin-setup-authn-view")->setMethod(\WHMCS\View\Form::METHOD_GET)->setParameters(["rp" => "/admin/setup/authn/view"])->setSubmitLabel(\AdminLang::trans("global.manage"))];
                break;
            default:
                throw new \WHMCS\Exception\Module\NotImplemented();
        }
    }

    public function isNonModuleActive($moduleType, $moduleName)
    {
        switch ($moduleType) {
            case "marketconnect":
                $moduleService = \WHMCS\MarketConnect\Service::where("name", $moduleName)->first();
                return is_null($moduleService) ? false : (bool) $moduleService->status;
                break;
            case "signin":
                $appMap = ["google" => \WHMCS\Authentication\Remote\Providers\Google\GoogleSignin::NAME, "facebook" => \WHMCS\Authentication\Remote\Providers\Facebook\FacebookSignin::NAME, "twitter" => \WHMCS\Authentication\Remote\Providers\Twitter\TwitterOauth::NAME];
                if (array_key_exists($moduleName, $appMap)) {
                    $appName = $appMap[$moduleName];
                } else {
                    $appName = $appMap[$moduleName];
                }
                $enabledProviders = (new \WHMCS\Authentication\Remote\RemoteAuth())->getEnabledProviders();
                return (bool) array_key_exists($appName, $enabledProviders);
                break;
            default:
                return false;
        }
    }
}
