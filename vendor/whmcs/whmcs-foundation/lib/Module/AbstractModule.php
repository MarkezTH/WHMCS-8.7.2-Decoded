<?php

namespace WHMCS\Module;

abstract class AbstractModule
{
    protected $type = "";
    protected $loadedmodule = "";
    protected $metaData = [];
    protected $moduleParams = [];
    protected $usesDirectories = true;
    protected $cacheActiveModules = NULL;
    const TYPE_ADMIN = "admin";
    const TYPE_ADDON = "addons";
    const TYPE_FRAUD = "fraud";
    const TYPE_GATEWAY = "gateways";
    const TYPE_MAIL = "mail";
    const TYPE_NOTIFICATION = "notifications";
    const TYPE_REGISTRAR = "registrars";
    const TYPE_REPORT = "reports";
    const TYPE_SECURITY = "security";
    const TYPE_SERVER = "servers";
    const TYPE_SOCIAL = "social";
    const TYPE_SUPPORT = "support";
    const TYPE_WIDGET = "widgets";
    const ALL_TYPES = NULL;
    const FUNCTIONDOESNTEXIST = "!Function not found in module!";
    const MODULE_NOT_ACTIVE = "Module Not Activated";

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        if (!in_array($type, self::ALL_TYPES)) {
            throw new \WHMCS\Exception("Invalid type: " . $type);
        }
        $this->type = $type;
    }

    protected function setLoadedModule($module)
    {
        $this->loadedmodule = $module;
    }

    public function getLoadedModule()
    {
        return $this->loadedmodule;
    }

    public function getList($type = "")
    {
        if ($type) {
            $this->setType($type);
        }
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callingFunction = "";
        if (isset($backtrace[1]) && isset($backtrace[1]["function"])) {
            $callingFunction = $backtrace[1]["function"];
        }
        $base_dir = $this->getBaseModuleDir();
        if (is_dir($base_dir)) {
            $modules = [];
            $dh = opendir($base_dir);
            while (false !== ($module = readdir($dh))) {
                if (!($callingFunction === "getAutoPopulateServers" && $module === "licensing")) {
                    if (!$this->usesDirectories) {
                        $module = str_replace(".php", "", $module);
                    }
                    if (is_file($this->getModulePath($module)) && !in_array($module, $modules)) {
                        $modules[] = $module;
                    }
                }
            }
            sort($modules);
            return $modules;
        }
        return false;
    }

    protected function getBaseModulesDir()
    {
        return ROOTDIR . DIRECTORY_SEPARATOR . "modules";
    }

    public function getBaseModuleDir()
    {
        return $this->getBaseModulesDir() . DIRECTORY_SEPARATOR . $this->getType();
    }

    public function getModuleDirectory($module)
    {
        return $this->getBaseModuleDir() . DIRECTORY_SEPARATOR . $module;
    }

    public function getModulePath($module)
    {
        if ($this->usesDirectories) {
            return $this->getModuleDirectory($module) . DIRECTORY_SEPARATOR . $module . ".php";
        }
        return $this->getBaseModuleDir() . DIRECTORY_SEPARATOR . $module . ".php";
    }

    public function getAppMetaDataFilePath($module)
    {
        return $this->getModuleDirectory($module) . DIRECTORY_SEPARATOR . "whmcs.json";
    }

    public function load($module, $globalVariable = NULL)
    {
        $whmcs = \App::self();
        $licensing = \DI::make("license");
        $module = $whmcs->sanitize("0-9a-z_-", $module);
        $modpath = $this->getModulePath($module);
        \Log::debug("Attempting to load module", ["type" => $this->getType(), "module" => $module, "path" => $modpath]);
        if (file_exists($modpath)) {
            if (!is_null($globalVariable)) {
                ${$globalVariable} =& ${$globalVariable};
            }
            include_once $modpath;
            $this->setLoadedModule($module);
            $this->setMetaData($this->getMetaData());
            return true;
        }
        return false;
    }

    public function call($function, $params = [])
    {
        $whmcs = \App::self();
        $licensing = \DI::make("license");
        if ($this->functionExists($function)) {
            $params = $this->prepareParams($params);
            $params = array_merge($this->getParams(), $params);
            try {
                $moduleEventProcessor = new ModuleEventProcessor($this);
                $params = $moduleEventProcessor->beforeFunctionCall($function, $params);
                $callResult = call_user_func($this->getLoadedModule() . "_" . $function, $params);
                return $moduleEventProcessor->afterFunctionCall($function, $params, $callResult);
            } catch (Exception\ModuleFunctionCallException $e) {
                return $e->getMessage();
            }
        }
        return self::FUNCTIONDOESNTEXIST;
    }

    public function functionExists($name)
    {
        if (!$this->getLoadedModule()) {
            return false;
        }
        return function_exists($this->getLoadedModule() . "_" . $name);
    }

    public function callIfExists($name, $default = NULL)
    {
        if ($this->functionExists($name)) {
            return $this->call($name);
        }
        return $default;
    }

    protected function getMetaData()
    {
        $moduleName = $this->getLoadedModule();
        if ($this->functionExists("MetaData")) {
            return $this->call("MetaData");
        }
    }

    protected function setMetaData($metaData)
    {
        if (is_array($metaData)) {
            $this->metaData = $metaData;
            return true;
        }
        $this->metaData = [];
        return false;
    }

    public function getMetaDataValue($keyName)
    {
        return array_key_exists($keyName, $this->metaData) ? $this->metaData[$keyName] : "";
    }

    public function isMetaDataValueSet($keyName)
    {
        return array_key_exists($keyName, $this->metaData);
    }

    public function getDisplayName()
    {
        $DisplayName = $this->getMetaDataValue("DisplayName");
        if (!$DisplayName) {
            $DisplayName = ucfirst($this->getLoadedModule());
        }
        return \WHMCS\Input\Sanitize::makeSafeForOutput($DisplayName);
    }

    public function getAPIVersion()
    {
        $APIVersion = $this->getMetaDataValue("APIVersion");
        if (!$APIVersion) {
            $APIVersion = $this->getDefaultAPIVersion();
        }
        return $APIVersion;
    }

    public function getApplicationLinkDescription()
    {
        return $this->getMetaDataValue("ApplicationLinkDescription");
    }

    public function getLogoFilename()
    {
        $modulePath = $this->getBaseModuleDir() . DIRECTORY_SEPARATOR . $this->getLoadedModule() . DIRECTORY_SEPARATOR;
        $logoExtensions = [".png", ".jpg", ".gif"];
        $assetHelper = \DI::make("asset");
        foreach ($logoExtensions as $extension) {
            if (file_exists($modulePath . "logo" . $extension)) {
                return $assetHelper->getWebRoot() . str_replace(ROOTDIR, "", $modulePath) . "logo" . $extension;
            }
        }
        return "";
    }

    public function getSmallLogoFilename()
    {
        $modulePath = $this->getBaseModuleDir() . DIRECTORY_SEPARATOR . $this->getLoadedModule() . DIRECTORY_SEPARATOR;
        $logoExtensions = [".png", ".jpg", ".gif"];
        foreach ($logoExtensions as $extension) {
            if (file_exists($modulePath . "logo_small" . $extension)) {
                return str_replace(ROOTDIR, "", $modulePath) . "logo_small" . $extension;
            }
        }
        return "";
    }

    protected function getDefaultAPIVersion()
    {
        $moduleType = $this->getType();
        switch ($moduleType) {
            case "gateways":
                $version = "1.0";
                break;
            default:
                $version = "1.1";
                return $version;
        }
    }

    public function prepareParams($params)
    {
        $whmcs = \App::self();
        $this->addParam("whmcsVersion", $whmcs->getVersion()->getCanonical());
        if (version_compare($this->getAPIVersion(), "1.1", "<")) {
            $params = \WHMCS\Input\Sanitize::convertToCompatHtml($params);
        } else {
            if (version_compare($this->getAPIVersion(), "1.1", ">=")) {
                $params = \WHMCS\Input\Sanitize::decode($params);
            }
        }
        return $params;
    }

    protected function addParam($key, $value)
    {
        $this->moduleParams[$key] = $value;
        return $this;
    }

    public function getParams()
    {
        $moduleParams = $this->moduleParams;
        return $this->prepareParams($moduleParams);
    }

    public function getParam($key)
    {
        $moduleParams = $this->getParams();
        return isset($moduleParams[$key]) ? $moduleParams[$key] : "";
    }

    public function findTemplate($templateName)
    {
        $templateName = preg_replace("/\\.tpl\$/", "", $templateName);
        $whmcs = \App::self();
        $currentTheme = $whmcs->getClientAreaTemplate()->getName();
        $templatePath = DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . $currentTheme;
        $modulePath = DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . $this->getType() . DIRECTORY_SEPARATOR . $this->getLoadedModule();
        $moduleTemplateProvidedByTheme = $templatePath . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . $this->getType() . DIRECTORY_SEPARATOR . $this->getLoadedModule() . DIRECTORY_SEPARATOR . $templateName . ".tpl";
        $themeSpecificModuleTemplate = $modulePath . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . $currentTheme . DIRECTORY_SEPARATOR . $templateName . ".tpl";
        $moduleTemplateInModuleSubdirectory = $modulePath . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . $templateName . ".tpl";
        $moduleTemplateInModuleDirectory = $modulePath . DIRECTORY_SEPARATOR . $templateName . ".tpl";
        if (file_exists(ROOTDIR . $moduleTemplateProvidedByTheme)) {
            return $moduleTemplateProvidedByTheme;
        }
        if (file_exists(ROOTDIR . $themeSpecificModuleTemplate)) {
            return $themeSpecificModuleTemplate;
        }
        if (file_exists(ROOTDIR . $moduleTemplateInModuleSubdirectory)) {
            return $moduleTemplateInModuleSubdirectory;
        }
        if (file_exists(ROOTDIR . $moduleTemplateInModuleDirectory)) {
            return $moduleTemplateInModuleDirectory;
        }
        return "";
    }

    public function isApplicationLinkSupported()
    {
        return $this->functionExists("CreateApplicationLink") && $this->functionExists("DeleteApplicationLink");
    }

    public function isApplicationLinkingEnabled()
    {
        $appLink = \WHMCS\ApplicationLink\ApplicationLink::firstOrNew(["module_type" => $this->getType(), "module_name" => $this->getLoadedModule()]);
        return $appLink->isEnabled;
    }

    public function activate($parameters = [])
    {
        throw new \WHMCS\Exception\Module\NotImplemented();
    }

    public function deactivate($parameters = [])
    {
        throw new \WHMCS\Exception\Module\NotImplemented();
    }

    public function updateConfiguration($parameters = [])
    {
        throw new \WHMCS\Exception\Module\NotImplemented();
    }

    public function getConfiguration()
    {
        throw new \WHMCS\Exception\Module\NotImplemented();
    }

    public function getActiveModules()
    {
        return [];
    }

    public function isActive($moduleName)
    {
        if (is_null($this->cacheActiveModules)) {
            $this->cacheActiveModules = $this->getActiveModules();
        }
        return in_array($moduleName, $this->cacheActiveModules);
    }

    public function getApps()
    {
        $apps = [];
        foreach ($this->getList() as $module) {
            $apps[] = \WHMCS\Apps\App\Model::factoryFromModule($this, $module);
        }
        return $apps;
    }

    public function getAdminActivationForms($moduleName)
    {
        throw new \WHMCS\Exception\Module\NotImplemented();
    }

    public function getAdminManagementForms($moduleName)
    {
        throw new \WHMCS\Exception\Module\NotImplemented();
    }

    public function getSettings()
    {
        throw new \WHMCS\Exception\Module\NotImplemented();
    }

    public function validateConfiguration($newSettings)
    {
        throw new \WHMCS\Exception\Module\NotImplemented();
    }

    public function prepareSettingsToValidate($newSettings)
    {
        $moduleSettings = $this->getConfiguration();
        if ($this instanceof Mail) {
            $previousSettings = $this->getSettings()["configuration"];
        } else {
            if ($this instanceof Registrar) {
                $previousSettings = $this->getSettings();
            }
        }
        $settingsToValidate = [];
        foreach ($moduleSettings as $key => $values) {
            $type = NULL;
            if (array_key_exists("Type", $values)) {
                $type = $values["Type"];
            }
            if ($type != "System") {
                if (isset($newSettings[$key])) {
                    $settingsToValidate[$key] = $newSettings[$key];
                } else {
                    if ($type == "yesno") {
                        $settingsToValidate[$key] = "";
                    } else {
                        if (isset($values["Default"])) {
                            $settingsToValidate[$key] = $values["Default"];
                        }
                    }
                }
                switch ($type) {
                    case "password":
                        if (isset($newSettings[$key]) && isset($previousSettings[$key])) {
                            $updatedPassword = interpretMaskedPasswordChangeForStorage($newSettings[$key], $previousSettings[$key]);
                            if ($updatedPassword === false) {
                                $settingsToValidate[$key] = $previousSettings[$key];
                            }
                        }
                        break;
                    case "yesno":
                        if (!empty($settingsToValidate[$key]) && $settingsToValidate[$key] !== "off" && $settingsToValidate[$key] !== "disabled") {
                            $settingsToValidate[$key] = "on";
                        } else {
                            $settingsToValidate[$key] = "";
                        }
                        break;
                    default:
                        if (empty($settingsToSave[$key])) {
                            $settingsToSave[$key] = "";
                        }
                }
            }
        }
        return $settingsToValidate;
    }
}
