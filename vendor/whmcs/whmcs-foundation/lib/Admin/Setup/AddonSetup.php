<?php

namespace WHMCS\Admin\Setup;

class AddonSetup
{
    protected $addonId = NULL;
    protected $addon = NULL;
    protected $moduleInterface = NULL;
    protected $mode = NULL;
    const GET_ADD_ON_FEATURES_FUNCTION = "ListAddOnFeatures";
    const GET_ADD_ON_FEATURES_CONFIG_FUNCTION = "GetAddOnFeaturesConfigOptions";

    public function setAddonId($self, $addonId)
    {
        $this->addonId = $addonId;
        if ($addonId) {
            $this->getAddon();
        }
        return $this;
    }

    public function getAddonId()
    {
        return $this->addonId;
    }

    protected function getAddon($addonId = 0)
    {
        if (!$addonId) {
            $addonId = $this->addonId;
        }
        if (is_null($this->addon) && $addonId) {
            $this->addon = \WHMCS\Product\Addon::with("moduleConfiguration")->findOrFail($addonId);
            $this->addonId = $this->addon->id;
            $this->mode = NULL;
        }
        return $this->addon;
    }

    protected function getModuleSetupRequestMode()
    {
        if (!$this->mode) {
            $hasSimpleMode = $this->hasSimpleConfigMode();
            if (!$hasSimpleMode) {
                $mode = "advanced";
            } else {
                $mode = \App::getFromRequest("mode");
                if (!$mode) {
                    $mode = "simple";
                }
            }
            $this->mode = $mode;
        }
        return $this->mode;
    }

    protected function getModuleInterface()
    {
        if (is_null($this->moduleInterface)) {
            $module = \App::getFromRequest("module");
            if (!$module && $this->addon) {
                $module = $this->addon->module;
            }
            if (!$module) {
                return NULL;
            }
            $this->moduleInterface = new \WHMCS\Module\Server();
            if (!$this->moduleInterface->load($module)) {
                throw new \Exception("Invalid module");
            }
        }
        return $this->moduleInterface;
    }

    protected function hasSimpleConfigMode()
    {
        $moduleInterface = $this->getModuleInterface();
        if ($moduleInterface && $moduleInterface->functionExists("ConfigOptions")) {
            $configArray = $moduleInterface->call("ConfigOptions", ["producttype" => $this->addon->type]);
            foreach ($configArray as $values) {
                if (array_key_exists("SimpleMode", $values) && $values["SimpleMode"]) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function getModuleSettingsFields()
    {
        $mode = $this->getModuleSetupRequestMode();
        $moduleInterface = $this->getModuleInterface();
        if (!$moduleInterface || $moduleInterface->isMetaDataValueSet("NoEditModuleSettings") && $moduleInterface->getMetaDataValue("NoEditModuleSettings")) {
            return [];
        }
        $isSimpleModeRequest = false;
        $noServerFound = false;
        $params = [];
        if ($mode == "simple") {
            $isSimpleModeRequest = true;
            $serverId = (int) \App::getFromRequest("server");
            if (!$serverId) {
                $addonId = \App::getFromRequest("id");
                $serverGroup = \App::getFromRequest("servergroup");
                if (!$serverGroup && !\App::isInRequest("servergroup") && $addonId) {
                    $serverGroup = $this->getAddon($addonId)->serverGroupId;
                } else {
                    $serverGroup = 0;
                }
                $serverId = getServerID($moduleInterface->getLoadedModule(), $serverGroup);
                if (!$serverId && $moduleInterface->getMetaDataValue("RequiresServer") !== false) {
                    $noServerFound = true;
                } else {
                    $params = $moduleInterface->getServerParams($serverId);
                }
            }
        }
        $provisioningType = "standard";
        if ($this->addon && $this->addon instanceof \WHMCS\Product\Addon) {
            $provisioningType = $this->addon->provisioningType;
        }
        $params["producttype"] = $this->addon ? $this->addon->type : "hostingaccount";
        $params["isAddon"] = true;
        if (\App::isInRequest("atype") && \App::getFromRequest("atype") === \WHMCS\Product\Addon::PROVISIONING_TYPE_FEATURE || !\App::isInRequest("atype") && $provisioningType === \WHMCS\Product\Addon::PROVISIONING_TYPE_FEATURE) {
            $params["provisioningType"] = $provisioningType;
            $featureNameOptions = \WHMCS\Module\ConfigOption\ConfigOption::factory("Feature Name")->setLoader($moduleInterface->getLoadedModule() . "_" . self::GET_ADD_ON_FEATURES_FUNCTION)->setSimpleMode(true);
            $configArray = $moduleInterface->call(self::GET_ADD_ON_FEATURES_CONFIG_FUNCTION, $params);
            if ($configArray instanceof \WHMCS\Module\ConfigOption\ConfigOptionList) {
                $configArray = $configArray->toArray();
            }
            if (!is_array($configArray)) {
                $configArray = [];
            }
            $configArray = $featureNameOptions->toArray() + $configArray;
        } else {
            $configArray = $moduleInterface->call("ConfigOptions", $params);
        }
        $i = 0;
        $isConfigured = false;
        foreach ($configArray as $key => &$values) {
            $i++;
            if (!array_key_exists("FriendlyName", $values)) {
                $values["FriendlyName"] = $key;
            }
            $values["Name"] = "packageconfigoption[" . $i . "]";
            $variable = "configoption" . $i;
            if (\App::isInRequest($values["Name"])) {
                $values["Value"] = \App::getFromRequest($values["Name"]);
            } else {
                if ($this->addon) {
                    $moduleConfiguration = $this->addon->moduleConfiguration->where("setting_name", $variable)->first();
                    $values["Value"] = $moduleConfiguration ? $moduleConfiguration->value : "";
                }
            }
            if ($values["Value"] !== "") {
                $isConfigured = true;
            }
        }
        unset($values);
        $i = 0;
        $fields = [];
        foreach ($configArray as $key => $values) {
            $i++;
            if (!$isConfigured) {
                $values["Value"] = NULL;
            }
            if ($mode == "advanced" || $mode == "simple" && array_key_exists("SimpleMode", $values) && $values["SimpleMode"]) {
                $dynamicFetchError = NULL;
                $supportsFetchingValues = false;
                if (in_array($values["Type"], ["text", "dropdown", "radio"]) && $isSimpleModeRequest && !empty($values["Loader"])) {
                    if ($noServerFound) {
                        $dynamicFetchError = "No server found so unable to fetch values";
                    } else {
                        $supportsFetchingValues = true;
                        try {
                            $loader = $values["Loader"];
                            $values["Options"] = $loader($params);
                            if ((\App::isInRequest("atype") && \App::getFromRequest("atype") === \WHMCS\Product\Addon::PROVISIONING_TYPE_FEATURE || !\App::isInRequest("atype") && $provisioningType === \WHMCS\Product\Addon::PROVISIONING_TYPE_FEATURE) && $values["FriendlyName"] === "Feature Name") {
                                $values["Options"] = ["" => "Please Select"] + $values["Options"];
                            }
                            if ($values["Type"] == "text") {
                                $values["Type"] = "dropdown";
                                if ($values["Value"] && !array_key_exists($values["Value"], $values["Options"])) {
                                    $values["Options"][$values["Value"]] = ucwords($values["Value"]);
                                }
                            }
                        } catch (\WHMCS\Exception\Module\InvalidConfiguration $e) {
                            $dynamicFetchError = \AdminLang::trans("products.serverConfigurationInvalid");
                        } catch (\Exception $e) {
                            $dynamicFetchError = $e->getMessage();
                        }
                    }
                }
                $html = moduleConfigFieldOutput($values);
                if (!is_null($dynamicFetchError)) {
                    $html .= "<i id=\"errorField" . $i . "\" class=\"fas fa-exclamation-triangle icon-warning\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . $dynamicFetchError . "\"></i>";
                }
                if ($supportsFetchingValues) {
                    $html .= "<i id=\"refreshField" . $i . "\" class=\"fas fa-sync icon-refresh\" data-product-id=\"" . \App::getFromRequest("id") . "\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"" . \AdminLang::trans("products.refreshDynamicInfo") . "\"></i>";
                }
                $fields[$values["FriendlyName"]] = $html;
            }
        }
        return $fields;
    }

    public function getAddonSettingsFields()
    {
        if (!function_exists("getServerID")) {
            require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "modulefunctions.php";
        }
        return $this->getModuleSettingsFields();
    }

    public function getModuleSettings($addonId)
    {
        $this->setAddonId($addonId);
        $moduleInterface = $this->getModuleInterface();
        $fields = $this->getAddonSettingsFields();
        $i = 1;
        $html = "<table class=\"form module-settings\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\" id=\"tblModuleSettings\"><tr>";
        foreach ($fields as $friendlyName => $fieldOutput) {
            $i++;
            $html .= "<td class=\"fieldlabel\" width=\"20%\">" . $friendlyName . "</td>" . "<td class=\"fieldarea\">" . $fieldOutput . "</td>";
            if ($i % 2 !== 0) {
                $html .= "</tr><tr>";
            }
        }
        $html .= "</tr></table>";
        $supportsFeatures = $moduleInterface->functionExists(self::GET_ADD_ON_FEATURES_FUNCTION);
        $languageStrings = ["notAvailableForStyle" => \AdminLang::trans("addons.notAvailableForStyle")];
        return ["content" => $html, "mode" => $this->mode, "supportsFeatures" => $supportsFeatures, "languageStrings" => $languageStrings];
    }
}
