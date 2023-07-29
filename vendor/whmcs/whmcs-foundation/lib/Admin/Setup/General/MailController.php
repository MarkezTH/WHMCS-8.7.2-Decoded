<?php

namespace WHMCS\Admin\Setup\General;

class MailController implements \WHMCS\Admin\Setup\Oauth2MailControllerInterface
{
    use \WHMCS\Admin\Setup\Oauth2MailControllerTrait;
    protected $context = \WHMCS\Mail\MailAuthHandler::CONTEXT_OUTGOING_MAIL;

    public function mailProviders(\WHMCS\Http\Message\ServerRequest $request)
    {
        $mailInterface = \WHMCS\Module\Mail::factory();
        $mailModules = $mailInterface->getList();
        $allModules = [];
        foreach (["PhpMail", "SmtpMail"] as $legacyModule) {
            if (in_array($legacyModule, $mailModules)) {
                try {
                    $mailInterface->load($legacyModule);
                    $allModules[$legacyModule] = $mailInterface->getDisplayName();
                } catch (\Exception $e) {
                }
                array_flip($mailModules);
                unset($mailModules[$legacyModule]);
                array_flip($mailModules);
            }
        }
        foreach ($mailModules as $mailModule) {
            $mailInterface = \WHMCS\Module\Mail::factory();
            try {
                $mailInterface->load($mailModule);
                $allModules[$mailModule] = $mailInterface->getDisplayName();
            } catch (\Exception $e) {
            }
        }
        $mailInterface = \WHMCS\Module\Mail::factory();
        return new \WHMCS\Http\Message\JsonResponse(["body" => view("admin.setup.mail.providers", ["allModules" => $allModules, "currentConfiguration" => $mailInterface->getSettings(), "mailInterface" => $mailInterface])]);
    }

    public function mailProviderConfiguration($JsonResponse, $request)
    {
        $module = $request->get("module");
        $mailInterface = \WHMCS\Module\Mail::factory();
        try {
            $mailInterface->load($module);
        } catch (\Exception $e) {
            throw new \WHMCS\Exception\Module\NotServicable("Invalid Module Request");
        }
        $currentlyStoredConfiguration = $mailInterface->getSettings();
        if ($currentlyStoredConfiguration["module"] != $module) {
            $currentlyStoredConfiguration = ["module" => $module, "configuration" => []];
        }
        return new \WHMCS\Http\Message\JsonResponse(["body" => view("admin.setup.mail.config", ["currentConfiguration" => $currentlyStoredConfiguration, "mailInterface" => $mailInterface])]);
    }

    public function mailProviderSave($JsonResponse, $request)
    {
        try {
            $module = $request->get("module");
            $mailInterface = \WHMCS\Module\Mail::factory();
            try {
                $mailInterface->load($module);
            } catch (\Exception $e) {
                throw new \WHMCS\Exception\Module\NotServicable("Invalid Module Request");
            }
            $configuration = [];
            foreach ($mailInterface->getConfiguration() as $key => $value) {
                $configuration[$key] = $request->get($key);
            }
            $mailInterface->updateConfiguration($configuration);
            $response = ["dismiss" => true, "successMsgTitle" => "", "successMsg" => \AdminLang::trans("global.changesuccess"), "body" => "<script>jQuery('#mailProviderName')" . ".text('" . $mailInterface->getDisplayName() . "')</script>"];
        } catch (\Exception $e) {
            $response = ["errorMsg" => \AdminLang::trans("global.validationerror") . ": " . $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function mailProviderConfigurationTest($JsonResponse, $request)
    {
        try {
            $module = $request->get("module");
            $mailInterface = \WHMCS\Module\Mail::factory();
            try {
                $mailInterface->load($module);
            } catch (\Exception $e) {
                throw new \WHMCS\Exception\Module\NotServicable("Invalid Module Request");
            }
            $configuration = [];
            foreach ($mailInterface->getConfiguration() as $key => $value) {
                $configuration[$key] = $request->get($key);
            }
            $mailInterface->validateConfiguration($configuration);
            $response = ["success" => true];
        } catch (\Exception $e) {
            $response = ["error" => \AdminLang::trans("global.validationerror") . ": " . $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function getStoredClientSecret($request)
    {
        $storedClientSecret = NULL;
        $targetModule = $request->get("target");
        if (in_array($targetModule, ["SmtpMail", "MicrosoftGraph"])) {
            $mailConfig = \WHMCS\Module\Mail::getStoredConfiguration();
            if ($mailConfig["module"] === $targetModule) {
                $storedClientSecret = $mailConfig["configuration"]["oauth2_client_secret"] ?? NULL;
            }
        }
        return $storedClientSecret;
    }
}
