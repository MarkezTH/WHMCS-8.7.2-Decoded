<?php

function checkDomain($domain)
{
    global $domainparts;
    if (preg_match("/^[a-z0-9][a-z0-9\\-]+[a-z0-9](\\.[a-z]{2,4})+\$/i", $domain)) {
        $domainparts = explode(".", $domain, 2);
        return true;
    }
    return false;
}

function getRegistrarsDropdownMenu($registrar, $name = "registrar", $additionalClasses = "select-inline", $includeAny = false)
{
    $registrarInterface = new WHMCS\Module\Registrar();
    if (!isset($registrarList)) {
        $registrarList = (new WHMCS\Module\Registrar())->getActiveModules();
        foreach ($registrarList as $key => $module) {
            if ($registrarInterface->load($module)) {
                $moduleName = $registrarInterface->getDisplayName();
            } else {
                $moduleName = ucfirst($module);
            }
            $registrarList[$module] = $moduleName;
            unset($moduleName);
            unset($registrarList[$key]);
        }
    }
    $none = AdminLang::trans("global.none");
    $name = "name=\"" . $name . "\"";
    $class = "class=\"form-control " . $additionalClasses . "\"";
    $id = "id=\"registrarsDropDown\"";
    if ($includeAny) {
        $any = AdminLang::trans("global.any");
        $noneSelected = $registrar == "none" ? "selected=\"selected\"" : "";
        $code = "<select " . $id . " " . $name . " " . $class . ">" . "<option value = \"\">" . $any . "</option>" . "<option value = \"none\" " . $noneSelected . ">" . $none . "</option>";
    } else {
        $code = "<select " . $id . " " . $name . " " . $class . ">" . "<option value = \"\">" . $none . "</option>";
    }
    foreach ($registrarList as $module => $moduleName) {
        $selected = "";
        if ($registrar === $module) {
            $selected = "selected=\"selected\"";
        }
        $code .= "<option value=\"" . $module . "\" " . $selected . ">" . $moduleName . "</option>";
    }
    $code .= "</select>";
    return $code;
}

function loadRegistrarModule($registrar)
{
    if (function_exists($registrar . "_getConfigArray")) {
        return true;
    }
    $module = new WHMCS\Module\Registrar();
    return $module->load($registrar);
}

function RegCallFunction($params, $function)
{
    try {
        $domain = WHMCS\Domain\Domain::findOrFail($params["domainid"]);
        $results = $domain->getRegistrarInterface()->call($function, $params);
        if ($results === WHMCS\Module\Registrar::FUNCTIONDOESNTEXIST) {
            throw new WHMCS\Exception\Module\FunctionNotFound();
        }
    } catch (Exception $e) {
        $results = ["na" => true];
    }
    return $results;
}

function getRegistrarConfigOptions($registrar)
{
    $module = new WHMCS\Module\Registrar();
    $module->load($registrar);
    return $module->getSettings();
}

function RegGetNameservers($params)
{
    return regcallfunction($params, "GetNameservers");
}

function RegSaveNameservers($params)
{
    for ($i = 1; $i <= 5; $i++) {
        $params["ns" . $i] = trim($params["ns" . $i]);
    }
    $values = regcallfunction($params, "SaveNameservers");
    if (!$values) {
        return false;
    }
    $userid = get_query_val("tbldomains", "userid", ["id" => $params["domainid"]]);
    if ($values["error"]) {
        logActivity("Domain Registrar Command: Save Nameservers - Failed: " . $values["error"] . " - Domain ID: " . $params["domainid"], $userid);
    } else {
        logActivity("Domain Registrar Command: Save Nameservers - Successful", $userid);
    }
    return $values;
}

function RegGetRegistrarLock($params)
{
    $values = regcallfunction($params, "GetRegistrarLock");
    if (is_array($values)) {
        return "";
    }
    return $values;
}

function RegSaveRegistrarLock($params)
{
    $values = regcallfunction($params, "SaveRegistrarLock");
    if (!$values) {
        return false;
    }
    $userid = get_query_val("tbldomains", "userid", ["id" => $params["domainid"]]);
    if ($values["error"]) {
        logActivity("Domain Registrar Command: Toggle Registrar Lock - Failed: " . $values["error"] . " - Domain ID: " . $params["domainid"], $userid);
    } else {
        logActivity("Domain Registrar Command: Toggle Registrar Lock - Successful", $userid);
    }
    return $values;
}

function RegGetURLForwarding($params)
{
    return regcallfunction($params, "GetURLForwarding");
}

function RegSaveURLForwarding($params)
{
    return regcallfunction($params, "SaveURLForwarding");
}

function RegGetEmailForwarding($params)
{
    return regcallfunction($params, "GetEmailForwarding");
}

function RegSaveEmailForwarding($params)
{
    return regcallfunction($params, "SaveEmailForwarding");
}

function RegGetDNS($params)
{
    return regcallfunction($params, "GetDNS");
}

function RegSaveDNS($params)
{
    return regcallfunction($params, "SaveDNS");
}

function RegRenewDomain($params)
{
    $domainId = $params["domainid"];
    try {
        $domainModel = WHMCS\Domain\Domain::findOrFail($domainId);
        $userid = $domainModel->userid;
        $domain = $domainModel->domain;
    } catch (Exception $e) {
        return ["error" => "Domain Not Found"];
    }
    try {
        $module = $domainModel->getRegistrarInterface();
        $values = $module->call("RenewDomain");
        if ($values === WHMCS\Module\Registrar::FUNCTIONDOESNTEXIST) {
            throw new WHMCS\Exception\Module\FunctionNotFound();
        }
    } catch (WHMCS\Exception\Module\FunctionNotFound $e) {
        return ["error" => "Registrar Function Not Supported"];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
    if (!is_array($values)) {
        return false;
    }
    if (!empty($values["error"])) {
        logActivity("Domain Renewal Failed - Domain ID: " . $domainId . " - Domain: " . $domain . " - Error: " . $values["error"], $userid);
        run_hook("AfterRegistrarRenewalFailed", ["params" => $params, "error" => $values["error"]]);
    } else {
        $expiryInfo = WHMCS\Database\Capsule::table("tbldomains")->where("id", "=", $domainId)->first(["expirydate", "registrationperiod"]);
        $expirydate = $expiryInfo->expirydate;
        $registrationperiod = $expiryInfo->registrationperiod;
        $year = substr($expirydate, 0, 4);
        $month = substr($expirydate, 5, 2);
        $day = substr($expirydate, 8, 2);
        if (strpos($expirydate, "0000-00-00") === false) {
            $newExpiryDate = WHMCS\Carbon::createFromDate($year, $month, $day);
        } else {
            $newExpiryDate = WHMCS\Carbon::create();
        }
        $newExpiryDate = $newExpiryDate->addYears($registrationperiod)->format("Y-m-d");
        $update = ["expirydate" => $newExpiryDate, "status" => "Active", "reminders" => ""];
        WHMCS\Database\Capsule::table("tbldomains")->where("id", "=", $domainId)->update($update);
        logActivity("Domain Renewed Successfully - Domain ID: " . $domainId . " - Domain: " . $domain, $userid);
        run_hook("AfterRegistrarRenewal", ["params" => $params]);
    }
    return $values;
}

function RegRegisterDomain($paramvars)
{
    $domainId = $paramvars["domainid"];
    try {
        $domainModel = WHMCS\Domain\Domain::findOrFail($domainId);
        $userid = $domainModel->userid;
        $domain = $domainModel->domain;
        $registrationperiod = $domainModel->registrationPeriod;
    } catch (Exception $e) {
        return ["error" => "Domain Not Found"];
    }
    run_hook("PreDomainRegister", ["domain" => $domain]);
    try {
        $module = $domainModel->getRegistrarInterface();
        $values = $module->call("RegisterDomain", $paramvars);
        if ($values === WHMCS\Module\Registrar::FUNCTIONDOESNTEXIST) {
            throw new WHMCS\Exception\Module\FunctionNotFound();
        }
    } catch (WHMCS\Exception\Module\FunctionNotFound $e) {
        logActivity("Domain Registration Not Supported by Module - Domain ID: " . $domainId . " - Domain: " . $domain);
        return ["error" => "Registrar Function Not Supported"];
    } catch (Exception $e) {
        return ["error" => "An unknown error occurred"];
    }
    if (!is_array($values)) {
        return false;
    }
    if (!empty($values["error"])) {
        logActivity("Domain Registration Failed - Domain ID: " . $domainId . " - Domain: " . $domain . " - Error: " . $values["error"], $userid);
        run_hook("AfterRegistrarRegistrationFailed", ["params" => $module->getLastParams(), "error" => $values["error"]]);
    } else {
        if (!empty($values["pending"])) {
            $domainModel->status = WHMCS\Domain\Status::PENDING_REGISTRATION;
            $domainModel->save();
            logActivity("Domain Pending Registration Successful - Domain ID: " . $domainId . " - Domain: " . $domain, $userid);
        } else {
            $updateFields = ["registrationdate" => WHMCS\Carbon::today()->toDateString(), "expirydate" => WHMCS\Carbon::today()->addYears($registrationperiod)->toDateString(), "status" => "Active"];
            if ($registrationperiod == 10) {
                $clientsdetails = getClientsDetails($domainModel->client);
                $extensionModel = $domainModel->getDomainObject()->getExtensionModel();
                if ($extensionModel) {
                    $renewalPricing = WHMCS\Domains\Extension\Pricing::ofTldId($extensionModel->id)->ofCurrencyId($clientsdetails["currency"])->ofClientGroup($clientsdetails["groupid"])->ofType("renew")->first();
                    if (!$renewalPricing && $clientsdetails["groupid"]) {
                        $renewalPricing = WHMCS\Domains\Extension\Pricing::ofTldId($extensionModel->id)->ofCurrencyId($clientsdetails["currency"])->ofClientGroup(0)->ofType("renew")->first();
                    }
                    if ($renewalPricing) {
                        do {
                            $registrationperiod -= 1;
                            $var = "year" . $registrationperiod;
                            if (0 <= $renewalPricing->{$var}) {
                                $done = true;
                                $updateFields["registrationperiod"] = $registrationperiod;
                                $updateFields["recurringamount"] = $renewalPricing->{$var};
                            }
                        } while ($done || 1 > $registrationperiod);
                    }
                }
            }
            WHMCS\Database\Capsule::table("tbldomains")->where("id", $domainId)->update($updateFields);
            logActivity("Domain Registered Successfully - Domain ID: " . $domainId . " - Domain: " . $domain, $userid);
        }
        run_hook("AfterRegistrarRegistration", ["params" => $module->getLastParams()]);
    }
    return $values;
}

function RegTransferDomain($paramvars)
{
    $domainId = $paramvars["domainid"];
    $passedepp = $paramvars["transfersecret"];
    try {
        $domainModel = WHMCS\Domain\Domain::findOrFail($domainId);
        $userid = $domainModel->userid;
        $domain = $domainModel->domain;
    } catch (Exception $e) {
        return ["error" => "Domain Not Found"];
    }
    run_hook("PreDomainTransfer", ["domain" => $domain]);
    try {
        $module = $domainModel->getRegistrarInterface();
        $values = $module->call("TransferDomain", $paramvars);
        if ($values === WHMCS\Module\Registrar::FUNCTIONDOESNTEXIST) {
            throw new WHMCS\Exception\Module\FunctionNotFound();
        }
    } catch (WHMCS\Exception\Module\FunctionNotFound $e) {
        logActivity("Domain Registration Not Supported by Module - Domain ID: " . $domainId . " - Domain: " . $domain);
        return ["error" => "Registrar Function Not Supported"];
    } catch (Exception $e) {
        return ["error" => "An unknown error occurred"];
    }
    if (!is_array($values)) {
        return false;
    }
    if ($values["error"]) {
        logActivity("Domain Transfer Failed - Domain ID: " . $domainId . " - Domain: " . $domain . " - Error: " . $values["error"], $userid);
        run_hook("AfterRegistrarTransferFailed", ["params" => $module->getLastParams(), "error" => $values["error"]]);
    } else {
        update_query("tbldomains", ["status" => "Pending Transfer"], ["id" => $domainId]);
        $array = ["date" => "now()", "title" => "Domain Pending Transfer", "description" => "Check the transfer status of the domain " . $domain, "admin" => "", "status" => "In Progress", "duedate" => WHMCS\Carbon::now()->addDays(5)->toDateString()];
        insert_query("tbltodolist", $array);
        logActivity("Domain Transfer Initiated Successfully - Domain ID: " . $domainId . " - Domain: " . $domain, $userid);
        run_hook("AfterRegistrarTransfer", ["params" => $module->getLastParams()]);
    }
    return $values;
}

function RegGetContactDetails($params)
{
    return regcallfunction($params, "GetContactDetails");
}

function RegSaveContactDetails($params)
{
    $domainObj = new WHMCS\Domains\Domain($params["sld"] . "." . $params["tld"]);
    $domainid = get_query_val("tbldomains", "id", ["domain" => $domainObj->getDomain()]);
    $additflds = new WHMCS\Domains\AdditionalFields();
    $params["additionalfields"] = $additflds->getFieldValuesFromDatabase($domainid);
    $originaldetails = $params;
    if (!array_key_exists("original", $params)) {
        $params = foreignChrReplace($params);
        $params["original"] = $originaldetails;
    }
    $params["domainObj"] = $domainObj;
    $values = regcallfunction($params, "SaveContactDetails");
    if (!$values) {
        return false;
    }
    $result = select_query("tbldomains", "userid", ["id" => $params["domainid"]]);
    $data = mysql_fetch_array($result);
    $userid = $data[0];
    if ($values["error"]) {
        logActivity("Domain Registrar Command: Update Contact Details - Failed: " . $values["error"] . " - Domain ID: " . $params["domainid"], $userid);
    } else {
        logActivity("Domain Registrar Command: Update Contact Details - Successful", $userid);
    }
    return $values;
}

function RegGetEPPCode($params)
{
    $values = regcallfunction($params, "GetEPPCode");
    if (!$values) {
        return false;
    }
    if ($values["eppcode"]) {
        $values["eppcode"] = htmlentities($values["eppcode"]);
    }
    return $values;
}

function RegRequestDelete($params)
{
    $values = regcallfunction($params, "RequestDelete");
    if (!$values) {
        return false;
    }
    if (!$values["error"]) {
        update_query("tbldomains", ["status" => "Cancelled"], ["id" => $params["domainid"]]);
    }
    return $values;
}

function RegReleaseDomain($params)
{
    $values = regcallfunction($params, "ReleaseDomain");
    if (isset($values["na"]) && $values["na"] === true) {
        return $values;
    }
    if (!isset($values["error"]) || !$values["error"]) {
        WHMCS\Database\Capsule::table("tbldomains")->where("id", $params["domainid"])->update(["status" => "Transferred Away"]);
    }
    return $values;
}

function RegRegisterNameserver($params)
{
    return regcallfunction($params, "RegisterNameserver");
}

function RegModifyNameserver($params)
{
    return regcallfunction($params, "ModifyNameserver");
}

function RegDeleteNameserver($params)
{
    return regcallfunction($params, "DeleteNameserver");
}

function RegIDProtectToggle($params)
{
    if (!array_key_exists("protectenable", $params)) {
        $domainid = $params["domainid"];
        $result = select_query("tbldomains", "idprotection", ["id" => $domainid]);
        $data = mysql_fetch_assoc($result);
        $idprotection = $data["idprotection"] ? true : false;
        $params["protectenable"] = $idprotection;
    }
    return regcallfunction($params, "IDProtectToggle");
}

function RegGetDefaultNameservers($params, $domain)
{
    $serverid = get_query_val("tblhosting", "server", ["domain" => $domain]);
    if ($serverid) {
        $result = select_query("tblservers", "", ["id" => $serverid]);
        $data = mysql_fetch_array($result);
        for ($i = 1; $i <= 5; $i++) {
            $params["ns" . $i] = trim($data["nameserver" . $i]);
        }
    } else {
        for ($i = 1; $i <= 5; $i++) {
            $params["ns" . $i] = trim(WHMCS\Config\Setting::getValue("DefaultNameserver" . $i));
        }
    }
    return $params;
}

function RegGetRegistrantContactEmailAddress($params)
{
    $values = regcallfunction($params, "GetRegistrantContactEmailAddress");
    if (isset($values["registrantEmail"])) {
        return ["registrantEmail" => $values["registrantEmail"]];
    }
    return [];
}

function RegCustomFunction($params, $func_name)
{
    return regcallfunction($params, $func_name);
}

function RebuildRegistrarModuleHookCache()
{
    $hooksarray = [];
    $registrar = new WHMCS\Module\Registrar();
    foreach ($registrar->getList() as $module) {
        if (is_file(ROOTDIR . "/modules/registrars/" . $module . "/hooks.php") && get_query_val("tblregistrars", "COUNT(*)", ["registrar" => $module])) {
            $hooksarray[] = $module;
        }
    }
    $whmcs = WHMCS\Application::getInstance();
    $whmcs->set_config("RegistrarModuleHooks", implode(",", $hooksarray));
}

function injectDomainObjectIfNecessary($params)
{
    if ((!isset($params["domainObj"]) || !is_object($params["domainObj"])) && !empty($params["sld"])) {
        $params["domainObj"] = new WHMCS\Domains\Domain(sprintf("%s.%s", $params["sld"], $params["tld"]));
    }
    return $params;
}

function convertToCiraCode($code)
{
    if ($code == "YT") {
        $code = "YK";
    }
    return $code;
}
