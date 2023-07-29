<?php

namespace WHMCS\Module;

class Addon extends AbstractModule
{
    protected $type = self::TYPE_ADDON;
    const MODULE_NAME_PROJECT_MANAGEMENT = "project_management";

    public function getActiveModules()
    {
        return \WHMCS\Database\Capsule::table("tbladdonmodules")->distinct("module")->pluck("module")->all();
    }

    public function call($function, $params = [])
    {
        $return = parent::call($function, $params);
        if (isset($return["jsonResponse"])) {
            $response = new \WHMCS\Http\JsonResponse();
            $response->setData($return["jsonResponse"]);
            $response->send();
            \WHMCS\Terminus::getInstance()->doExit();
        }
        return $return;
    }

    public function getAdminActivationForms($moduleName)
    {
        return [(new \WHMCS\View\Form())->setUriPrefixAdminBaseUrl("configaddonmods.php")->setMethod(\WHMCS\View\Form::METHOD_POST)->setParameters(["token" => generate_token("plain"), "action" => "activate", "module" => $moduleName])->setSubmitLabel(\AdminLang::trans("global.activate"))];
    }

    public function getAdminManagementForms($moduleName)
    {
        return [(new \WHMCS\View\Form())->setUriPrefixAdminBaseUrl("addonmodules.php")->setMethod(\WHMCS\View\Form::METHOD_GET)->setParameters(["module" => $moduleName])->setSubmitLabel(\AdminLang::trans("apps.info.useApp"))];
    }

    public function activate($parameters = [])
    {
        if (!$this->loadedmodule) {
            throw new \WHMCS\Exception("No module loaded");
        }
        if (!$this->functionExists("activate")) {
            throw new \WHMCS\Exception\Module\NotImplemented("Module cannot be activated due to no activate method being present");
        }
        if (!$this->functionExists("config")) {
            throw new \WHMCS\Exception\Module\NotImplemented("Module cannot be activated due to no config method being present");
        }
        $config = $this->call("config");
        if (empty($config) || !is_array($config)) {
            throw new \WHMCS\Exception\Module\NotActivated("Could not activate " . $this->getLoadedModule() . " due to invalid return from config method");
        }
        $activeModules = $this->getActiveModules();
        $response = $this->call("activate");
        if (!$response || is_array($response) && ($response["status"] === "success" || $response["status"] === "info")) {
            $version = $config["version"] ?: "1.0";
            $activeModules[] = $this->getLoadedModule();
            sort($activeModules);
            \WHMCS\Config\Setting::setValue("ActiveAddonModules", implode(",", $activeModules));
            if ($version != \AdminLang::trans("addonmodules.nooutput")) {
                $addonModuleVersion = new Addon\Setting();
                $addonModuleVersion->module = $this->getLoadedModule();
                $addonModuleVersion->setting = "version";
                $addonModuleVersion->value = $version;
                $addonModuleVersion->save();
            }
            logActivity("Addon Module Activated - " . $config["name"]);
        } else {
            throw new \WHMCS\Exception\Module\NotActivated($response["description"] ?: "An unknown error occurred");
        }
    }

    public static function isEnabled($moduleName)
    {
        return in_array($moduleName, (new static())->getActiveModules());
    }
}
