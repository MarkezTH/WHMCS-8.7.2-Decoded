<?php

function getModuleType($id)
{
    $result = select_query("tblservers", "type", ["id" => $id]);
    $data = mysql_fetch_array($result);
    $type = $data["type"];
    return $type;
}

function ModuleBuildParams($serviceID, $addonId = 0)
{
    $server = new WHMCS\Module\Server();
    if ($addonId) {
        if (!$server->loadByAddonId($addonId)) {
            logActivity("Required Product Module '" . $server->getAddonModule() . "' Missing");
        }
    } else {
        if (!$server->loadByServiceID($serviceID)) {
            logActivity("Required Product Module '" . $server->getServiceModule() . "' Missing");
        }
    }
    return $server->buildParams();
}

function ModuleCallFunction($function, $serviceID, $extraParams = [], $addonId = 0)
{
    $server = new WHMCS\Module\Server();
    if ($addonId && !$server->loadByAddonId($addonId)) {
        if (!$server->getAddonModule()) {
            return "success";
        }
        logActivity("Required Product Module '" . $server->getAddonModule() . "' Missing");
        return "Module Not Found";
    }
    if (!$addonId && !$server->loadByServiceID($serviceID)) {
        if (!$server->getServiceModule()) {
            return "success";
        }
        logActivity("Required Product Module '" . $server->getServiceModule() . "' Missing");
        return "Module Not Found";
    }
    $params = $server->buildParams();
    if (is_array($extraParams)) {
        $params = array_merge($params, $extraParams);
    }
    $serviceid = (int) $params["serviceid"];
    $userid = (int) $params["userid"];
    if ($function == "Create") {
        $updateInfo = [];
        if (0 < $addonId && array_key_exists("service", $params)) {
            if (!$params["username"]) {
                $params["username"] = $params["service"]["username"] ?? "";
                $updateInfo["username"] = $params["username"];
            }
            if (!$params["domain"]) {
                $params["domain"] = $params["service"]["domain"] ?? "";
                $updateInfo["domain"] = $params["domain"];
            }
        }
        if ($server->getMetaDataValue("AutoGenerateUsernameAndPassword") !== false) {
            if (!$params["username"]) {
                $usernamegenhook = run_hook("OverrideModuleUsernameGeneration", $params);
                $username = "";
                if (0 < count($usernamegenhook)) {
                    foreach ($usernamegenhook as $usernameval) {
                        if (is_string($usernameval)) {
                            $username = $usernameval;
                        }
                    }
                }
                if (!$username) {
                    $username = createServerUsername($params["domain"]);
                }
                $updateInfo["username"] = $username;
                $params["username"] = $username;
            }
            if (!$params["password"]) {
                $newPassword = $server->generateRandomPasswordForModule();
                $updateInfo["password"] = encrypt($newPassword);
                $params["password"] = $newPassword;
            }
            if (0 < count($updateInfo)) {
                if (0 < $addonId) {
                    if (array_key_exists("password", $updateInfo)) {
                        $updateInfo["password"] = decrypt($updateInfo["password"]);
                    }
                    $params["model"]->serviceProperties->save($updateInfo);
                } else {
                    WHMCS\Database\Capsule::table("tblhosting")->where("id", $serviceid)->update($updateInfo);
                }
            }
        }
    }
    $hookresults = run_hook("PreModule" . $function, ["params" => $params]);
    $hookabort = false;
    foreach ($hookresults as $hookvals) {
        foreach ($hookvals as $k => $v) {
            if ($k == "abortcmd" && $v === true) {
                $hookabort = true;
                $result = "Function Aborted by Action Hook Code";
            }
        }
        if (!$hookabort) {
            $params = array_replace_recursive($params, $hookvals);
        }
    }
    $entityType = "service";
    $entityId = $serviceID;
    $entityModule = $server->getServiceModule();
    $serviceOrAddon = "Service ID: " . $serviceID;
    $extraSaveData = [];
    if ($addonId) {
        $entityType = "addon";
        $entityId = $addonId;
        $entityModule = $server->getAddonModule();
        $serviceOrAddon = "Addon ID: " . $addonId;
    }
    $logName = $function;
    if (!empty(WHMCS\Module\Server::ACTIONS_LOG_REPLACEMENTS[$logName])) {
        $logName = WHMCS\Module\Server::ACTIONS_LOG_REPLACEMENTS[$logName];
    }
    if (!$hookabort) {
        $modfuncname = in_array($function, ["Create", "Suspend", "Unsuspend", "Terminate"]) ? $function . "Account" : $function;
        if ($server->functionExists($modfuncname)) {
            try {
                $result = $server->call($modfuncname, $params);
            } catch (Exception $e) {
                $result = $e->getMessage();
            }
            if ($result == "success") {
                $extra_log_info = $suspendReason = "";
                if ($function == "Suspend") {
                    $suspendReason = "";
                    if (isset($params["suspendreason"]) && $params["suspendreason"] != "Overdue on Payment") {
                        $suspendReason = $params["suspendreason"];
                    }
                    if ($suspendReason) {
                        $extra_log_info = " - Reason: " . $suspendReason;
                    }
                }
                logActivity("Module " . $logName . " Successful" . $extra_log_info . " - " . $serviceOrAddon, $userid);
                $updatearray = [];
                if ($function == "Create") {
                    $updatearray = ["domainstatus" => "Active", "termination_date" => "0000-00-00"];
                    if ($entityType == "addon") {
                        $updatearray = ["status" => "Active", "termination_date" => "0000-00-00"];
                    }
                } else {
                    if ($function == "Suspend") {
                        $updatearray = ["domainstatus" => "Suspended", "suspendreason" => $suspendReason];
                        if ($entityType == "addon") {
                            $updatearray = ["status" => "Suspended"];
                            $extraSaveData["suspend_reason"] = $suspendReason;
                        }
                    } else {
                        if ($function == "Unsuspend") {
                            $updatearray = ["domainstatus" => "Active", "suspendreason" => "", "termination_date" => "0000-00-00"];
                            if ($entityType == "addon") {
                                $updatearray = ["status" => "Active", "termination_date" => "0000-00-00"];
                                $extraSaveData["suspend_reason"] = "";
                            }
                        } else {
                            if ($function == "Terminate") {
                                if ($entityType == "addon") {
                                    $updatearray = ["status" => "Terminated"];
                                    if (in_array(WHMCS\Database\Capsule::table("tblhostingaddons")->where("id", "=", $entityId)->value("termination_date"), ["0000-00-00", "1970-01-01"])) {
                                        $updatearray["termination_date"] = date("Y-m-d");
                                    }
                                    run_hook("AddonTerminated", ["id" => $params["model"]->id, "userid" => $params["model"]->userid, "serviceid" => $params["model"]->serviceId, "addonid" => $params["model"]->addonid]);
                                } else {
                                    $updatearray = ["domainstatus" => "Terminated"];
                                    if (in_array(WHMCS\Database\Capsule::table("tblhosting")->where("id", "=", $serviceid)->value("termination_date"), ["0000-00-00", "1970-01-01"])) {
                                        $updatearray["termination_date"] = date("Y-m-d");
                                    }
                                    $addons = WHMCS\Service\Addon::where("hostingid", "=", $serviceid)->whereIn("status", ["Active", "Suspended"])->get();
                                    foreach ($addons as $addon) {
                                        if ($addon->productAddon->module) {
                                            WHMCS\Service\Automation\AddonAutomation::factory($addon)->runAction("TerminateAccount");
                                        } else {
                                            $addon->status = "Terminated";
                                            $addon->terminationDate = WHMCS\Carbon::now()->toDateString();
                                            $addon->save();
                                            run_hook("AddonTerminated", ["id" => $addon->id, "userid" => $addon->clientId, "serviceid" => $addon->serviceId, "addonid" => $addon->addonId]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if (0 < count($updatearray)) {
                    $table = $entityType == "addon" ? "tblhostingaddons" : "tblhosting";
                    update_query($table, $updatearray, ["id" => $entityId]);
                    if ($extraSaveData) {
                    }
                }
                if ($server->isApplicationLinkSupported() && $server->isApplicationLinkingEnabled()) {
                    try {
                        $errors = $server->doSingleApplicationLinkCall("Create");
                        if (is_array($errors) && 0 < count($errors)) {
                            logActivity("Application Link Provisioning returned the following warnings: " . implode(", ", $errors));
                        }
                    } catch (WHMCS\Exception $e) {
                        logActivity("Application Link Provisioning Failed: " . $e->getMessage() . " - " . $serviceOrAddon);
                    }
                }
                if ($function === "Create") {
                    (new WHMCS\Product\EventAction\EventActionProcessorHandler())->handleModuleEvent($entityType, $params["model"], "aftercreate");
                }
                run_hook("AfterModule" . $function, ["params" => $params]);
                WHMCS\Module\Queue::resolve($entityType, $entityId, $entityModule, $modfuncname);
                return "success";
            }
            WHMCS\Module\Queue::add($entityType, $entityId, $entityModule, $modfuncname, $result);
            run_hook("AfterModule" . $function . "Failed", ["failureResponseMessage" => $result, "params" => $params]);
        } else {
            $result = "Function Not Supported by Module";
            if ($function == "Renew") {
                return $result;
            }
        }
    }
    logActivity("Module " . $logName . " Failed - " . $serviceOrAddon . " - Error: " . $result, $userid);
    return $result;
}

function ServerSuspendAccount($serviceID, $suspendreason = "", $addonId = 0)
{
    $extraParams = ["suspendreason" => $suspendreason ? $suspendreason : "Overdue on Payment"];
    return modulecallfunction("Suspend", $serviceID, $extraParams, $addonId);
}

function ServerUnsuspendAccount($serviceID, $addonId = 0)
{
    return modulecallfunction("Unsuspend", $serviceID, [], $addonId);
}

function ServerTerminateAccount($serviceID, $addonId = 0)
{
    return modulecallfunction("Terminate", $serviceID, [], $addonId);
}

function ServerRenew($serviceID, $addonId = 0)
{
    $result = modulecallfunction("Renew", $serviceID, [], $addonId);
    if ($result == "Function Not Supported by Module") {
        $result = "notsupported";
    }
    return $result;
}

function ServerChangePassword($serviceID, $addonId = 0)
{
    return modulecallfunction("ChangePassword", $serviceID, [], $addonId);
}

function ServerLoginLink($serviceID, $addonId = 0)
{
    $server = new WHMCS\Module\Server();
    if ($addonId) {
        $server->loadByAddonId($addonId);
    } else {
        $server->loadByServiceID($serviceID);
    }
    if ($server->functionExists("LoginLink")) {
        return $server->call("LoginLink");
    }
    return "";
}

function ServerChangePackage($serviceID, $addonId = 0)
{
    return modulecallfunction("ChangePackage", $serviceID, [], $addonId);
}

function ServerCustomFunction($serviceID, $func_name, $addonId = 0)
{
    $server = new WHMCS\Module\Server();
    if ($addonId) {
        $server->loadByAddonId($addonId);
    } else {
        $server->loadByServiceID($serviceID);
    }
    $params = array_merge($server->buildParams(), ["action" => $func_name]);
    $moduleEventActions = $server->callIfExists("EventActions", []);
    if (isset($moduleEventActions[$func_name])) {
        foreach (array_keys($moduleEventActions[$func_name]["Params"] ?? []) as $actionParam) {
            $params[$actionParam] = WHMCS\Input\Sanitize::decode(App::getFromRequest($actionParam));
        }
    }
    $hookresults = run_hook("PreModuleCustom", ["params" => $params]);
    $hookabort = false;
    foreach ($hookresults as $hookvals) {
        if (isset($hookvals["abortcmd"]) && $hookvals["abortcmd"] === true) {
            $hookabort = true;
            $result = "Function Aborted by Action Hook Code";
            if ($hookabort) {
                return $result;
            }
            $result = $server->call($func_name, $params);
            if ($result == "success") {
                run_hook("AfterModuleCustom", ["params" => $params]);
            } else {
                run_hook("AfterModuleCustomFailed", ["failureResponseMessage" => $result, "params" => $params]);
            }
            return $result;
        }
        if (!$hookabort) {
            $params = array_replace_recursive($params, $hookvals);
        }
    }
}

function ServerClientArea($serviceID, $addonId = 0)
{
    $server = new WHMCS\Module\Server();
    if ($addonId) {
        $server->loadByAddonId($addonId);
    } else {
        $server->loadByServiceID($serviceID);
    }
    if ($server->functionExists("ClientArea")) {
        return $server->call("ClientArea");
    }
    return "";
}

function ServerUsageUpdate()
{
    $servers = WHMCS\Product\Server::where("disabled", "0")->orderBy("name", "ASC")->get();
    $updatedServerIds = [];
    foreach ($servers as $serverModel) {
        $server = new WHMCS\Module\Server();
        $server->load($serverModel->type);
        if ($server->functionExists("UsageUpdate")) {
            $updatedServerIds[] = $serverModel->id;
            $response = $server->call("UsageUpdate", $server->getServerParams($serverModel));
            if ($response && !in_array($response, ["success", WHMCS\Module\Server::FUNCTIONDOESNTEXIST])) {
                logActivity("Server Usage Update Failed: " . $response . " - Server ID: " . $serverModel->id);
            }
        }
    }
    return $updatedServerIds;
}

function serverUsernameExists($username)
{
    $serviceCount = WHMCS\Database\Capsule::table("tblhosting")->where("username", $username)->count();
    $addonCount = WHMCS\Database\Capsule::table("tblcustomfieldsvalues")->join("tblcustomfields", "tblcustomfieldsvalues.fieldid", "=", "tblcustomfields.id")->where(["tblcustomfields.type" => "addon", "tblcustomfields.fieldname" => "Username", "tblcustomfieldsvalues.value" => $username])->count();
    $username_exists = $serviceCount + $addonCount;
    if (0 < $username_exists) {
        return true;
    }
    return false;
}

function createServerUsername($domain)
{
    if (!$domain && !WHMCS\Config\Setting::getValue("GenerateRandomUsername")) {
        return "";
    }
    if (!WHMCS\Config\Setting::getValue("GenerateRandomUsername")) {
        $domain = strtolower($domain);
        $username = preg_replace("/[^a-z]/", "", $domain);
        $username = substr($username, 0, 8);
        $username_exists = serverusernameexists($username);
        $suffix = $attemptCount = 0;
        while ($username_exists) {
            $suffix++;
            $trimlength = 8 - strlen($suffix);
            $username = substr($username, 0, $trimlength) . $suffix;
            $username_exists = serverusernameexists($username);
            $attemptCount++;
            if ($attemptCount === 99) {
                logActivity("Error: Unable to generate unique username for " . $domain . ". " . "WHMCS recommends enabling \"Enable Random Usernames\" in Configuration " . "> General Settings > Ordering.");
                $username = "";
            }
        }
    } else {
        $lowercase = "abcdefghijklmnopqrstuvwxyz";
        $str = "";
        $seeds_count = strlen($lowercase) - 1;
        for ($i = 0; $i < 8; $i++) {
            $str .= $lowercase[rand(0, $seeds_count)];
        }
        $username = "";
        for ($i = 0; $i < 8; $i++) {
            $randomnum = rand(0, strlen($str) - 1);
            $username .= $str[$randomnum];
            $str = substr($str, 0, $randomnum) . substr($str, $randomnum + 1);
        }
        $username_exists = serverusernameexists($username);
        $attemptCount = 0;
        while ($username_exists) {
            $username = "";
            $str = "";
            for ($i = 0; $i < 8; $i++) {
                $str .= $lowercase[rand(0, $seeds_count)];
            }
            for ($i = 0; $i < 8; $i++) {
                $randomnum = rand(0, strlen($str) - 1);
                $username .= $str[$randomnum];
                $str = substr($str, 0, $randomnum) . substr($str, $randomnum + 1);
            }
            $username_exists = serverusernameexists($username);
            $attemptCount++;
            if (10 <= $attemptCount) {
                $username = "";
            }
        }
    }
    return $username;
}

function createServerPassword()
{
    return WHMCS\Module\Server::generateRandomPassword();
}

function getServerID($serverType, int $serverGroup = 0)
{
    return WHMCS\Module\Server::getServerId($serverType, $serverGroup);
}

function rebuildModuleHookCache()
{
    $hooksarray = [];
    $inUseProvisioningModules = WHMCS\Product\Product::distinct("servertype")->pluck("servertype")->all();
    $inUseProvisioningModules = array_merge(WHMCS\Product\Addon::distinct("module")->pluck("module")->all(), $inUseProvisioningModules);
    $server = new WHMCS\Module\Server();
    foreach ($server->getList() as $module) {
        if (in_array($module, $inUseProvisioningModules) && is_file(ROOTDIR . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . "servers" . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "hooks.php")) {
            $hooksarray[] = $module;
        }
    }
    WHMCS\Config\Setting::setValue("ModuleHooks", implode(",", $hooksarray));
}

function rebuildAddonHookCache()
{
    $hooksarray = [];
    $inUseAddonModules = WHMCS\Database\Capsule::table("tbladdonmodules")->distinct()->pluck("module")->all();
    foreach ($inUseAddonModules as $module) {
        if (is_file(ROOTDIR . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . "addons" . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "hooks.php")) {
            $hooksarray[] = $module;
        }
    }
    WHMCS\Config\Setting::setValue("AddonModulesHooks", implode(",", $hooksarray));
}

function moduleConfigFieldOutput($values)
{
    if (!isset($values["Value"])) {
        $values["Value"] = isset($values["Default"]) ? $values["Default"] : "";
    }
    if (empty($values["Size"])) {
        $values["Size"] = 40;
    }
    $inputClass = "input-";
    if ($values["Size"] <= 10) {
        if ($values["Size"] <= 20) {
            if ($values["Size"] <= 30) {
                if ($values["Size"] <= 40) {
                    if ($values["Size"] <= 50) {
                        if ($values["Size"] <= 60) {
                            if ($values["Size"] <= 70) {
                                $inputClass .= "400";
                            } else {
                                $inputClass .= "700";
                            }
                        } else {
                            $inputClass .= "600";
                        }
                    } else {
                        $inputClass .= "500";
                    }
                } else {
                    $inputClass .= "400";
                }
            } else {
                $inputClass .= "300";
            }
        } else {
            $inputClass .= "200";
        }
    } else {
        $inputClass .= "100";
    }
    switch ($values["Type"] ?? NULL) {
        case "text":
            $code = "<input type=\"text\" name=\"" . $values["Name"] . "\" class=\"form-control input-inline " . $inputClass . "\" value=\"" . WHMCS\Input\Sanitize::encode($values["Value"]) . "\"" . (isset($values["Placeholder"]) ? " placeholder=\"" . $values["Placeholder"] . "\"" : "") . (!empty($values["Disabled"]) ? " disabled" : "") . (!empty($values["ReadOnly"]) ? " readonly=\"readonly\"" : "") . " />";
            if (isset($values["Description"])) {
                $code .= " " . $values["Description"];
            }
            break;
        case "password":
            $code = "<input type=\"password\" autocomplete=\"off\" name=\"" . $values["Name"] . "\" class=\"form-control input-inline " . $inputClass . "\" value=\"" . replacePasswordWithMasks($values["Value"]) . "\"" . (!empty($values["ReadOnly"]) ? " readonly=\"readonly\"" : "") . " />";
            if (isset($values["Description"])) {
                $code .= " " . $values["Description"];
            }
            break;
        case "yesno":
            $code = "<label class=\"checkbox-inline\"><input type=\"hidden\" name=\"" . $values["Name"] . "\" value=\"\">" . "<input type=\"checkbox\" name=\"" . $values["Name"] . "\"";
            if (!empty($values["Value"])) {
                $code .= " checked=\"checked\"";
            }
            $code .= " /> " . (isset($values["Description"]) ? $values["Description"] : "&nbsp") . "</label>";
            break;
        case "dropdown":
            $code = "<select name=\"" . $values["Name"];
            if (isset($values["Multiple"])) {
                $size = isset($values["Size"]) && is_numeric($values["Size"]) ? $values["Size"] : 3;
                $code .= "[]\" multiple=\"true\" size=\"" . $size . "\"";
                if (0 < strlen($values["Value"])) {
                    $selectedKeys = json_decode($values["Value"]);
                    if (!is_array($selectedKeys)) {
                        $selectedKeys = [];
                    }
                }
            } else {
                $code .= "\"";
                $selectedKeys = [$values["Value"]];
            }
            $code .= " class=\"form-control select-inline\"" . (!empty($values["ReadOnly"]) ? " readonly=\"readonly\"" : "") . ">";
            $dropdownOptions = $values["Options"];
            if (is_array($dropdownOptions)) {
                foreach ($dropdownOptions as $key => $value) {
                    $code .= "<option value=\"" . $key . "\"";
                    if (in_array($key, $selectedKeys)) {
                        $code .= " selected=\"selected\"";
                    }
                    $code .= ">" . $value . "</option>";
                }
            } else {
                $dropdownOptions = explode(",", $dropdownOptions);
                foreach ($dropdownOptions as $value) {
                    $code .= "<option value=\"" . $value . "\"";
                    if (in_array($value, $selectedKeys)) {
                        $code .= " selected=\"selected\"";
                    }
                    $code .= ">" . $value . "</option>";
                }
            }
            $code .= "</select>";
            if (isset($values["Description"])) {
                $code .= " " . $values["Description"];
            }
            break;
        case "radio":
            $code = "";
            if (isset($values["Description"])) {
                $code .= $values["Description"] . "<br />";
            }
            $options = $values["Options"];
            if (is_array($options)) {
                if (!isset($values["Value"])) {
                    $values["Value"] = current($options);
                }
                foreach ($options as $key => $value) {
                    $checked = "";
                    if ($values["Value"] == $key) {
                        $checked = " checked=\"checked\"";
                    }
                    $code .= "<label class=\"radio-inline\">" . "<input type=\"radio\" name=\"" . $values["Name"] . "\"" . " value=\"" . $key . "\"" . $checked . " />" . $value . "</label><br>";
                }
            } else {
                $options = explode(",", $options);
                if (!isset($values["Value"])) {
                    $values["Value"] = $options[0];
                }
                foreach ($options as $value) {
                    $code .= "<label class=\"radio-inline\"><input type=\"radio\" name=\"" . $values["Name"] . "\" value=\"" . $value . "\"";
                    if ($values["Value"] == $value) {
                        $code .= " checked=\"checked\"";
                    }
                    $code .= " /> " . $value . "</label><br />";
                }
            }
            break;
        case "textarea":
            $cols = isset($values["Cols"]) ? $values["Cols"] : "60";
            $rows = isset($values["Rows"]) ? $values["Rows"] : "5";
            $code = "<textarea class=\"form-control\" name=\"" . $values["Name"] . "\" cols=\"" . $cols . "\" rows=\"" . $rows . "\"" . (!empty($values["ReadOnly"]) ? " readonly=\"readonly\"" : "") . ">" . WHMCS\Input\Sanitize::encode($values["Value"]) . "</textarea>";
            if (isset($values["Description"])) {
                $code .= $values["Description"];
            }
            break;
        default:
            $code = $values["Description"];
            return $code;
    }
}
