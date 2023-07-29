<?php

namespace WHMCS\Hook;

class Manager
{
    protected $skipHookLogging = ["LogActivity"];
    private static $hooks = [];

    public function add($hookName, $priority, $hookFunction = "", $rollbackFunction)
    {
        $hooks = $this->getHooks();
        if (!array_key_exists($hookName, $hooks)) {
            $hooks[$hookName] = [];
        }
        array_push($hooks[$hookName], ["priority" => $priority, "hookFunction" => $hookFunction, "rollbackFunction" => $rollbackFunction]);
        $this->log($hookName, "Hook Defined for Point: %s - Priority: %s - Function Name: %s", $hookName, $priority, $this->toString($hookFunction));
        uasort($hooks[$hookName], ["self", "sortArrayByPriority"]);
        $this->setHooks($hooks);
    }

    public function clear($hookName)
    {
        $hooks = $this->getHooks();
        if (array_key_exists($hookName, $hooks)) {
            unset($hooks[$hookName]);
        }
        $this->setHooks($hooks);
    }

    public function getRegistered($hookName)
    {
        $hooks = $this->getHooks();
        if (is_array($hooks) && isset($hooks[$hookName]) && is_array($hooks[$hookName])) {
            return $hooks[$hookName];
        }
        return [];
    }

    public function boot()
    {
        global $CONFIG;
        ob_start();
        if (defined("WHMCSLIVECHAT")) {
            return NULL;
        }
        if (\DI::make("config")->disable_hook_loading === true) {
            return NULL;
        }
        $this->setHooks();
        $this->loadGenericHooks();
        \WHMCS\Module\Module::defineHooks();
        \WHMCS\MarketConnect\Promotion::initHooks();
        \WHMCS\Notification\Events::defineHooks();
        ob_end_clean();
    }

    private function loadGenericHooks()
    {
        $hooksDir = ROOTDIR . "/includes/hooks/";
        $dh = opendir($hooksDir);
        while (false !== ($hookFile = readdir($dh))) {
            $hookFilePath = $hooksDir . $hookFile;
            if (is_file($hookFilePath)) {
                $extension = pathinfo($hookFile, PATHINFO_EXTENSION);
                if ($extension == "php" && strtolower($hookFile) !== "index.php" && strpos($hookFile, "_") !== 0) {
                    $this->log("", "Attempting to load hook file: %s", $hookFilePath);
                    $hookLoaded = \WHMCS\Utility\SafeInclude::file($hookFilePath, function ($errorMessage) {
                        $this->log("", $errorMessage);
                    });
                    if ($hookLoaded) {
                        $this->log("", "Hook File Loaded: %s", $hookFilePath);
                        if (!is_array(self::$hooks)) {
                            $this->log("", "Hook File: %s mutated the hooks list from Array to %s", $hookFilePath, ucfirst(gettype(self::$hooks)));
                            $this->setHooks();
                        }
                    }
                }
            }
        }
        closedir($dh);
    }

    public function log($hookName, ...$msg, $inputs)
    {
        if (in_array($hookName, $this->skipHookLogging)) {
            return NULL;
        }
        $HooksDebugMode = \WHMCS\Config\Setting::getValue("HooksDebugMode");
        $hookLogging = defined("HOOKSLOGGING") || $HooksDebugMode;
        if ($hookLogging) {
            $specificHookLogging = \DI::make("config")->hooks_debug_whitelist;
            if ($specificHookLogging && is_array($specificHookLogging) && 0 < count($specificHookLogging) && !in_array($hookName, $specificHookLogging)) {
                $hookLogging = false;
            }
        }
        if ($hookLogging) {
            $msg = "Hooks Debug: " . $msg;
            if (defined("IN_CRON")) {
                $msg = "Cron Job: " . $msg;
            }
            array_unshift($inputs, $msg);
            logActivity(call_user_func_array("sprintf", $inputs));
        }
    }

    public function remove($hookName, $priority, $hookFunction = "", $rollbackFunction)
    {
        $hooks = $this->getHooks();
        if (array_key_exists($hookName, $hooks)) {
            reset($hooks[$hookName]);
            foreach ($hooks[$hookName] as $key => $hook) {
                if (0 <= $priority && $priority == $hook["priority"] || $hookFunction && $hookFunction == $hook["hookFunction"] || $rollbackFunction && $rollbackFunction == $hook["rollbackFunction"]) {
                    unset($hooks[$hookName][$key]);
                    if (count($hooks[$hookName]) === 0) {
                        unset($hooks[$hookName]);
                    }
                }
            }
        }
        $this->setHooks($hooks);
    }

    public function run($hookName = [], $args = false, $unpackArguments)
    {
        $hooks = $this->getHooks();
        $this->log($hookName, "Called Hook Point %s", $hookName);
        if (!array_key_exists($hookName, $hooks)) {
            $this->log($hookName, "No Hook Functions Defined for %s", $hookName);
            return [];
        }
        $license = \DI::make("license");
        if ($license->isUnlicensed() && defined("ADMINAREA") && constant("ADMINAREA")) {
            $this->log($hookName, "Admin Hook Functions not allowed without active license", $hookName);
            return [];
        }
        unset($rollbacks);
        $rollbacks = [];
        reset($hooks[$hookName]);
        $results = [];
        foreach ($hooks[$hookName] as $hook) {
            array_push($rollbacks, $hook["rollbackFunction"]);
            if (is_callable($hook["hookFunction"])) {
                $this->log($hookName, "Hook Point %s - Calling Hook Function %s", $hookName, $this->toString($hook["hookFunction"]));
                $res = $unpackArguments ? call_user_func_array($hook["hookFunction"], array_values($args)) : call_user_func($hook["hookFunction"], $args);
                if ($res) {
                    $results[] = $res;
                    $this->log($hookName, "Hook Completed - Returned True");
                } else {
                    $this->log($hookName, "Hook Completed - Returned False");
                }
            } else {
                $this->log($hookName, "Hook Function %s Not Found", $this->toString($hook["hookFunction"]));
            }
        }
        return $results;
    }

    public function setRegistered($hookName, $hooksToDefine)
    {
        $hooks = $this->getHooks();
        if (!is_array($hooksToDefine)) {
            $hooksToDefine = [];
        }
        $hooks[$hookName] = $hooksToDefine;
        $this->setHooks($hooks);
    }

    public function toString($hook)
    {
        if (is_object($hook) && $hook instanceof \WHMCS\Scheduling\Task\TaskInterface && !is_callable($hook)) {
            $callableName = get_class($hook);
        } else {
            is_callable($hook, false, $callableName);
        }
        if ($callableName == "Closure::__invoke") {
            $callableName = "(anonymous function)";
        }
        return $callableName;
    }

    public function validate($validate, $hookName = [], $args)
    {
        $hookErrors = $this->run($hookName, $args);
        if (is_array($hookErrors) && count($hookErrors)) {
            foreach ($hookErrors as $hookError) {
                if (is_array($hookError)) {
                    $validate->addErrors($hookError);
                } else {
                    $validate->addError($hookError);
                }
            }
        }
    }

    public function processResults($moduleName, $function = [], $hookResults)
    {
        if (!empty($hookResults)) {
            $hookErrors = [];
            $abortWithSuccess = false;
            foreach ($hookResults as $hookResult) {
                if (!empty($hookResult["abortWithError"])) {
                    $hookErrors[] = $hookResult["abortWithError"];
                }
                if (array_key_exists("abortWithSuccess", $hookResult) && $hookResult["abortWithSuccess"] === true) {
                    $abortWithSuccess = true;
                }
            }
            if (count($hookErrors)) {
                throw new \WHMCS\Exception(implode(" ", $hookErrors));
            }
            if ($abortWithSuccess) {
                logActivity("Function " . $moduleName . "->" . $function . "() Aborted by Action Hook Code");
                return true;
            }
        }
        return false;
    }

    protected function getHooks()
    {
        return self::$hooks;
    }

    protected function setHooks($hooks)
    {
        self::$hooks = $hooks;
    }

    public function sortArrayByPriority($a, $b)
    {
        return $a["priority"] < $b["priority"] ? -1 : 1;
    }
}
