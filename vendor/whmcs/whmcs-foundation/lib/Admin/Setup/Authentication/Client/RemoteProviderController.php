<?php

namespace WHMCS\Admin\Setup\Authentication\Client;

class RemoteProviderController
{
    public function viewProviderSettings(\WHMCS\Http\Message\ServerRequest $request)
    {
        $aInt = new \WHMCS\Admin("Configure Sign-In Integration");
        $aInt->setResponseType(\WHMCS\Admin::RESPONSE_HTML_MESSAGE);
        $aInt->title = \AdminLang::trans("remoteAuthn.remoteClientAuthentication");
        $aInt->sidebar = "config";
        $aInt->icon = "autosettings";
        $aInt->helplink = "Sign-In Integrations";
        $remoteAuth = \DI::make("remoteAuth");
        $providers = $remoteAuth->getProviders();
        $templateData = [];
        foreach ($providers as $provider) {
            $data["name"] = $provider::NAME;
            $data["displayName"] = $provider::FRIENDLY_NAME;
            $data["description"] = $provider->getDescription();
            $data["configurationIntro"] = $provider->getConfigurationDescription();
            $data["fields"] = [];
            $fields = $provider->getConfigurationFields();
            $settings = $provider->getConfiguration();
            foreach ($fields as $settingName => $displayName) {
                $type = "text";
                if (strcasecmp($settingName, "enabled") === 0) {
                    $type = "checkbox";
                }
                $data["fields"][] = ["displayName" => $displayName, "name" => $settingName, "value" => isset($settings[$settingName]) ? $settings[$settingName] : "", "type" => $type];
            }
            $templateData[] = $data;
        }
        $moduleToActivate = "";
        \App::getFromRequest("activate");
        switch (\App::getFromRequest("activate")) {
            case "google":
                $moduleToActivate = "google_signin";
                break;
            case "facebook":
                $moduleToActivate = "facebook_signin";
                break;
            case "twitter":
                $moduleToActivate = "twitter_oauth";
                break;
            default:
                $output = view("authentication.manage-remote-providers", ["providers" => $templateData, "moduleToActivate" => $moduleToActivate]);
                $aInt->setBodyContent($output);
                return $aInt->display();
        }
    }

    public function deleteAccountLink(\WHMCS\Http\Message\ServerRequest $request)
    {
        $userAuthenticationId = $request->request()->get("auth_id");
        try {
            $accountLink = \WHMCS\Authentication\Remote\AccountLink::findOrFail($userAuthenticationId);
            $accountLink->delete();
            \DI::make("remoteAuth")->logAccountLinkDeletion($accountLink);
            $responseData = ["status" => "success", "message" => "Sign-In Integration Link Removed."];
        } catch (\Exception $e) {
            $responseData = ["status" => "error", "message" => "failed to load Remote Authentication User ID: " . $userAuthenticationId];
        }
        return new \WHMCS\Http\Message\JsonResponse($responseData);
    }

    public function deactivate(\WHMCS\Http\Message\ServerRequest $request)
    {
        $providerName = $request->request()->get("provider");
        if (!$providerName) {
            $responseData = ["status" => "error", "message" => "Provider not specified!"];
        } else {
            $provider = NULL;
            try {
                $remoteAuth = \DI::make("remoteAuth");
                $provider = $remoteAuth->getProviderByName($providerName);
                $settings = $provider->getConfiguration();
                $provider->setConfiguration($settings);
                $provider->setEnabled(false);
                $provider->saveConfiguration();
                $state = $settings["Enabled"] ? "Enabled" : "Disabled";
                $responseData = ["status" => "success", "message" => sprintf("%s %s", $provider::FRIENDLY_NAME, $state)];
            } catch (\Exception $e) {
                $providerName = $provider ? $provider::FRIENDLY_NAME : $providerName;
                $responseData = ["status" => "error", "message" => "Unable to alter state for provider " . $providerName];
            }
        }
        return new \WHMCS\Http\Message\JsonResponse($responseData);
    }

    public function activate(\WHMCS\Http\Message\ServerRequest $request)
    {
        $provider = $request->request()->get("provider");
        if ($provider) {
            $inputs = $request->getParsedBody();
            $inputs[$provider . "_Enabled"] = 1;
            $request = $request->withParsedBody($inputs);
        }
        return $this->updateProviderSettings($request);
    }

    protected function updateProviderSettings(\WHMCS\Http\Message\ServerRequest $request)
    {
        $responseData = ["status" => "success"];
        $providerName = $request->request()->get("provider");
        if (!$providerName) {
            $responseData["status"] = "error";
            $responseData["errorMessage"] = "Incomplete form data";
        } else {
            $settings = $this->getSettingsFromRequest($request);
            $remoteAuth = \DI::make("remoteAuth");
            $provider = $remoteAuth->getProviderByName($providerName);
            $provider->setConfiguration($settings);
            try {
                if (!\App::getSystemURL()) {
                    throw new \RuntimeException("You must configure the System URL in Configuration <i class=“far fa-wrench” aria-hidden=\"true\"></i> > System Settings > General Settings");
                }
                $provider->verifyConfiguration();
                $provider->saveConfiguration();
            } catch (\Exception $e) {
                $providerName = $provider ? $provider::FRIENDLY_NAME : $providerName;
                if (!defined("ADMINAREA")) {
                    if ($e instanceof \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException) {
                        $msgTemplate = "Unable to connect to %s. Please check credentials and try again. %s";
                    } else {
                        $msgTemplate = "Could not verify details for Sign In Integration \"%s\". %s";
                    }
                    $msg = sprintf($msgTemplate, $providerName, $e->getMessage());
                } else {
                    $msg = \AdminLang::trans("global.error") . ": " . $e->getMessage();
                }
                $responseData = ["status" => "warning", "errorMessage" => $msg];
            }
        }
        return new \WHMCS\Http\Message\JsonResponse($responseData);
    }

    protected function getSettingsFromRequest(\WHMCS\Http\Message\ServerRequest $request)
    {
        $providerName = $request->request()->get("provider");
        $settings = [];
        foreach ($request->request() as $id => $value) {
            if (strpos($id, $providerName . "_") === 0) {
                $settingName = substr($id, strlen($providerName) + 1);
                $settings[$settingName] = trim($value);
            }
        }
        $settings["Enabled"] = (int) (!empty($settings["Enabled"]));
        return $settings;
    }
}
