<?php

namespace WHMCS\Authentication\TwoFactor;

class TwoFactorController
{
    protected $inAdminArea = false;

    protected function isAdmin()
    {
        return $this->inAdminArea;
    }

    protected function initTwoFactorObject()
    {
        $twofa = new \WHMCS\TwoFactorAuthentication();
        if ($this->isAdmin()) {
            if (!$twofa->isActiveAdmins()) {
                throw new \WHMCS\Exception("Two-Factor Authentication is not enabled.");
            }
            $twofa->setUser(\WHMCS\User\Admin::getAuthenticatedUser());
        } else {
            if (!$twofa->isActiveClients()) {
                throw new \WHMCS\Exception("Two-Factor Authentication is not enabled.");
            }
            $twofa->setUser(\Auth::user());
        }
        return $twofa;
    }

    public function enable(\WHMCS\Http\Message\ServerRequest $request)
    {
        $langClass = "Lang";
        if ($this->isAdmin()) {
            $langClass = "AdminLang";
        }
        $twofa = $this->initTwoFactorObject();
        $modules = [];
        $descriptions = [];
        $moduleInterface = new \WHMCS\Module\Security();
        foreach ($twofa->getAvailableModules() as $module) {
            $moduleInterface->load($module);
            $configuration = $moduleInterface->call("config");
            $friendlyName = $langClass::trans("twoFactor." . $module . ".friendlyName");
            if ($friendlyName == "twoFactor." . $module . ".friendlyName") {
                $friendlyName = isset($configuration["FriendlyName"]["Value"]) ? $configuration["FriendlyName"]["Value"] : ucfirst($module);
            }
            $description = $langClass::trans("twoFactor." . $module . ".description");
            if ($description == "twoFactor." . $module . ".description") {
                $description = isset($configuration["ShortDescription"]["Value"]) ? $configuration["ShortDescription"]["Value"] : "No description available";
            }
            $modules[$module] = $friendlyName;
            $descriptions[$module] = $description;
        }
        $response = ["body" => view("authentication.two-factor.enable-choose", ["isAdmin" => $this->isAdmin(), "modules" => $modules, "descriptions" => $descriptions, "webRoot" => \WHMCS\Utility\Environment\WebHelper::getBaseUrl(), "twoFactorEnforced" => $request->get("enforce")])];
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function configure(\WHMCS\Http\Message\ServerRequest $request, $verifyError = "")
    {
        $module = $request->request()->get("module");
        $twofa = $this->initTwoFactorObject();
        $modules = $twofa->getAvailableModules();
        if (!in_array($module, $modules)) {
            throw new \WHMCS\Exception("Invalid module name");
        }
        $output = $twofa->moduleCall("activate", $module, ["verifyError" => $verifyError]);
        if (is_null($output)) {
            return $this->verify($request);
        }
        $response = ["body" => view("authentication.two-factor.enable-configure", ["isAdmin" => $this->isAdmin(), "module" => $module, "twoFactorConfigurationOutput" => $output])];
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function verify(\WHMCS\Http\Message\ServerRequest $request)
    {
        $module = $request->request()->get("module");
        $twofa = $this->initTwoFactorObject();
        $modules = $twofa->getAvailableModules();
        if (!in_array($module, $modules)) {
            throw new \WHMCS\Exception("Invalid module name");
        }
        try {
            $response = $twofa->moduleCall("activateverify", $module);
            $displayMsg = isset($response["msg"]) ? $response["msg"] : "";
            $settings = isset($response["settings"]) ? $response["settings"] : [];
            $backupCode = $twofa->activateUser($module, $settings);
            if (!$backupCode) {
                throw new \WHMCS\Exception(\Lang::trans("twofaactivationerror"));
            }
        } catch (\WHMCS\Exception $e) {
            return $this->configure($request, $e->getMessage());
        }
        $response = ["body" => view("authentication.two-factor.enable-complete", ["isAdmin" => $this->isAdmin(), "displayMsg" => $displayMsg, "backupCode" => $backupCode])];
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function disable(\WHMCS\Http\Message\ServerRequest $request, $errorMsg = "")
    {
        $twofa = $this->initTwoFactorObject();
        $response = ["body" => view("authentication.two-factor.disable-confirm", ["isAdmin" => $this->isAdmin(), "errorMsg" => $errorMsg])];
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function disableConfirm(\WHMCS\Http\Message\ServerRequest $request)
    {
        $inputVerifyPassword = $request->request()->get("pwverify");
        $twofa = $this->initTwoFactorObject();
        try {
            $twofa->validateAndDisableUser($inputVerifyPassword);
        } catch (\WHMCS\Exception $e) {
            $errorMsg = $e->getMessage();
            return $this->disable($request, $errorMsg);
        }
        $response = ["body" => view("authentication.two-factor.disable-complete", ["isAdmin" => $this->isAdmin()]), "hideSubmit" => true];
        return new \WHMCS\Http\Message\JsonResponse($response);
    }
}
