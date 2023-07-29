<?php

namespace WHMCS;

class Addon
{
    private $id = 0;
    private $userId = 0;
    private $data = [];
    private $moduleParams = [];
    private $moduleResults = [];

    public function __construct($addonId = NULL, $userId = NULL)
    {
        if (!function_exists("checkContactPermission")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        if ($addonId) {
            $this->setAddonId($addonId, $userId);
        }
    }

    public function setAddonId($addonId, $userId = 0)
    {
        $this->id = $addonId;
        $this->userId = $userId;
        $this->data = [];
        $this->moduleParams = [];
        $this->moduleResults = [];
        return $this->getAddonData();
    }

    public function getAddonData()
    {
        $addon = Service\Addon::with("productAddon")->where("id", $this->id);
        if ($this->userId) {
            $addon = $addon->where("userid", $this->userId);
        }
        $addon = $addon->first();
        if ($addon) {
            $data = $addon->toArray();
            $data["name"] = $data["name"] ?: $addon->productAddon->name;
            $data["addonModel"] = $addon;
            $this->data = $data;
            return true;
        }
        return false;
    }

    public function getData($key1, $key2 = "")
    {
        if ($key2) {
            return isset($this->data[$key1][$key2]) ? $this->data[$key1][$key2] : "";
        }
        return isset($this->data[$key1]) ? $this->data[$key1] : "";
    }

    public function getModule()
    {
        return \App::sanitize("0-9a-z_-", $this->getData("product_addon", "module"));
    }

    public function getId()
    {
        return (int) $this->getData("id");
    }

    public function hasFunction($function)
    {
        $moduleInterface = new Module\Server();
        $moduleName = $this->getModule();
        if (!$moduleName) {
            $this->moduleResults = ["error" => "Addon not assigned to a module"];
            return false;
        }
        $loaded = $moduleInterface->load($moduleName);
        if (!$loaded) {
            $this->moduleResults = ["error" => "Product Addon module not found"];
            return false;
        }
        return $moduleInterface->functionExists($function);
    }

    public function moduleCall($function, $vars = [])
    {
        $moduleInterface = new Module\Server();
        $moduleName = $this->getModule();
        if (!$moduleName) {
            $this->moduleResults = ["error" => "Addon not assigned to a module"];
            return false;
        }
        $loaded = $moduleInterface->load($moduleName);
        if (!$loaded) {
            $this->moduleResults = ["error" => "Addon module not found"];
            return false;
        }
        $moduleInterface->setAddonId($this->getId());
        switch ($function) {
            case "CreateAccount":
                $hookFunction = "Create";
                break;
            case "SuspendAccount":
                $hookFunction = "Suspend";
                break;
            case "TerminateAccount":
                $hookFunction = "Terminate";
                break;
            case "UnsuspendAccount":
                $hookFunction = "Unsuspend";
                break;
            default:
                $hookFunction = $function;
                $builtParams = array_merge($moduleInterface->getParams(), $vars);
                $hookResults = run_hook("PreModule" . $hookFunction, ["params" => $vars]);
                try {
                    if (\HookMgr::processResults($moduleName, $function, $hookResults)) {
                        return true;
                    }
                } catch (Exception $e) {
                    $this->moduleResults = ["error" => $e->getMessage()];
                    return false;
                }
                $results = $moduleInterface->call($function, $builtParams);
                $hookVars = ["params" => $builtParams, "results" => $results, "functionExists" => $results !== Module\Server::FUNCTIONDOESNTEXIST, "functionSuccessful" => is_array($results) && empty($results["error"]) || is_object($results)];
                $successOrFail = "";
                if (!$hookVars["functionSuccessful"] && $hookResults["functionExists"]) {
                    $successOrFail = "Failed";
                } else {
                    if ($hookFunction === "Create") {
                        (new Product\EventAction\EventActionProcessorHandler())->handleModuleEvent(Product\EventAction\EventActionProcessorHandler::ENTITY_TYPE_ADDON, $this->getData("addonModel"), "aftercreate");
                    }
                }
                $hookResults = run_hook("AfterModule" . $hookFunction . $successOrFail, $hookVars);
                try {
                    if (\HookMgr::processResults($moduleName, $function, $hookResults)) {
                        return true;
                    }
                } catch (Exception $e) {
                    $this->moduleResults = ["error" => $e->getMessage()];
                    return false;
                }
                if (!$results) {
                    $this->moduleResults = ["error" => "Function not found"];
                    return false;
                }
                if (is_array($results)) {
                    $results = ["data" => $results];
                } else {
                    $results = $results == "success" || !$results ? [] : ["error" => $results, "data" => $results];
                }
                $this->moduleResults = $results;
                return isset($results["error"]) && $results["error"] ? false : true;
        }
    }

    public function getModuleReturn($var = "")
    {
        if (!$var) {
            return $this->moduleResults;
        }
        return isset($this->moduleResults[$var]) ? $this->moduleResults[$var] : "";
    }

    public function getLastError()
    {
        return $this->getModuleReturn("error");
    }
}
