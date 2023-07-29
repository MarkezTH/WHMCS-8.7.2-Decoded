<?php

require_once "lib/Plesk/Loader.php";
function plesk_MetaData()
{
    return ["DisplayName" => "Plesk", "APIVersion" => "1.1", "ListAccountsUniqueIdentifierDisplayName" => "Domain", "ListAccountsUniqueIdentifierField" => "domain", "ListAccountsProductField" => "configoption1", "PasswordGenerationSpecialCharacters" => "!@#\$%^&*?_~"];
}

function plesk_EventActions()
{
    return ["InstallWordPress" => ["AllowClient" => true, "AllowAdmin" => true, "FriendlyName" => "wptk.installWordPress", "FriendlyShortName" => "wptk.installWordPressShort", "ModuleFunction" => "InstallWordPress", "Params" => ["blog_title" => ["Description" => "Blog Title", "Default" => "New Blog Title", "Type" => "text"], "blog_path" => ["Description" => "WordPress Path", "Default" => "", "Type" => "text"], "admin_pass" => ["Description" => "Admin Password", "Type" => "password", "Disabled" => true]], "Events" => ["aftercreate"]]];
}

function plesk_ConfigOptions($params)
{
    require_once "lib/Plesk/Translate.php";
    $translator = new Plesk_Translate();
    $resellerSimpleMode = $params["producttype"] == "reselleraccount";
    $configarray = ["servicePlanName" => ["FriendlyName" => $translator->translate("CONFIG_SERVICE_PLAN_NAME"), "Type" => "text", "Size" => "25", "Loader" => function ($params) {
        $return = [];
        Plesk_Loader::init($params);
        $packages = Plesk_Registry::getInstance()->manager->getServicePlans();
        $return[""] = "None";
        foreach ($packages as $package) {
            $return[$package] = $package;
        }
        return $return;
    }, "SimpleMode" => true], "resellerPlanName" => ["FriendlyName" => $translator->translate("CONFIG_RESELLER_PLAN_NAME"), "Type" => "text", "Size" => "25", "Loader" => function ($params) {
        $return = [];
        Plesk_Loader::init($params);
        $packages = Plesk_Registry::getInstance()->manager->getResellerPlans();
        $return[""] = "None";
        foreach ($packages as $package) {
            $return[$package->name] = $package->name;
        }
        return $return;
    }, "SimpleMode" => $resellerSimpleMode], "ipAdresses" => ["FriendlyName" => $translator->translate("CONFIG_WHICH_IP_ADDRESSES"), "Type" => "dropdown", "Options" => "IPv4 shared; IPv6 none,IPv4 dedicated; IPv6 none,IPv4 none; IPv6 shared,IPv4 none; IPv6 dedicated,IPv4 shared; IPv6 shared,IPv4 shared; IPv6 dedicated,IPv4 dedicated; IPv6 shared,IPv4 dedicated; IPv6 dedicated", "Default" => "IPv4 shared; IPv6 none", "Description" => "", "SimpleMode" => true], "powerUser" => ["FriendlyName" => $translator->translate("CONFIG_POWER_USER_MODE"), "Type" => "yesno", "Description" => $translator->translate("CONFIG_POWER_USER_MODE_DESCRIPTION")]];
    return $configarray;
}

function plesk_AdminLink($params)
{
    $address = $params["serverhostname"] ? $params["serverhostname"] : $params["serverip"];
    $port = $params["serveraccesshash"] ? $params["serveraccesshash"] : "8443";
    $secure = $params["serversecure"] ? "https" : "http";
    if (empty($address)) {
        return "";
    }
    $form = sprintf("<form action=\"%s://%s:%s/login_up.php3\" method=\"post\" target=\"_blank\"><input type=\"hidden\" name=\"login_name\" value=\"%s\" /><input type=\"hidden\" name=\"passwd\" value=\"%s\" /><input type=\"submit\" value=\"%s\"></form>", $secure, WHMCS\Input\Sanitize::encode($address), WHMCS\Input\Sanitize::encode($port), WHMCS\Input\Sanitize::encode($params["serverusername"]), WHMCS\Input\Sanitize::encode($params["serverpassword"]), "Login to panel");
    return $form;
}

function plesk_ClientArea($params)
{
    try {
        Plesk_Loader::init($params);
        $webspace = Plesk_Registry::getInstance()->manager->getWebspaceByDomain($params["domain"]);
        $model = $params["model"];
        $productModuleActionSettings = [];
        if ($model instanceof WHMCS\Service\Service) {
            $productModuleActionSettings = json_decode($model->product->getModuleConfigurationSetting("moduleActions")->value, true) ?? [];
        }
        $wpInstances = json_decode(WHMCS\Input\Sanitize::decode($model->serviceProperties->get("WordPress Instances")), true) ?: [];
        $wpInstances = array_map(function ($item) {
            return array_merge($item, ["path" => rtrim(parse_url($item["instanceUrl"], PHP_URL_PATH), "/")]);
        }, $wpInstances);
        return ["tabOverviewReplacementTemplate" => "overview.tpl", "templateVariables" => ["scheme" => $params["serverhttpprefix"], "hostname" => $params["serverhostname"], "domainId" => $webspace->webspace->get->result->id, "ssoLoginUrl" => "clientarea.php?action=productdetails&id=" . $params["serviceid"] . "&dosinglesignon=1&success_redirect_url=%2Fsmb%2Fweb%2Fview", "lastupdate" => WHMCS\Carbon::now()->toDateTimeString(), "diskPercentMax" => 100 < substr($params["diskpercent"], 0, -1) ? substr($params["diskpercent"], 0, -1) : 100, "bwPercentMax" => 100 < substr($params["bwpercent"], 0, -1) ? substr($params["bwpercent"], 0, -1) : 100, "allowWpClientInstall" => $productModuleActionSettings["InstallWordPress"]["client"] ?? false, "serviceId" => $model->id, "wpDomain" => $model->domain, "wpInstances" => $wpInstances]];
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function plesk_CreateAccount($params)
{
    try {
        Plesk_Loader::init($params);
        $translator = Plesk_Registry::getInstance()->translator;
        if ("" == $params["clientsdetails"]["firstname"] && "" == $params["clientsdetails"]["lastname"]) {
            return $translator->translate("ERROR_ACCOUNT_VALIDATION_EMPTY_FIRST_OR_LASTNAME");
        }
        if ("" == $params["username"]) {
            return $translator->translate("ERROR_ACCOUNT_VALIDATION_EMPTY_USERNAME");
        }
        Plesk_Registry::getInstance()->manager->createTableForAccountStorage();
        $account = WHMCS\Database\Capsule::table("mod_pleskaccounts")->where("userid", $params["clientsdetails"]["userid"])->where("usertype", $params["type"])->first();
        $panelExternalId = is_null($account) ? "" : $account->panelexternalid;
        $params["clientsdetails"]["panelExternalId"] = $panelExternalId;
        $accountId = NULL;
        try {
            $accountInfo = Plesk_Registry::getInstance()->manager->getAccountInfo($params, $panelExternalId);
            if (isset($accountInfo["id"])) {
                $accountId = $accountInfo["id"];
            }
        } catch (Exception $e) {
            if (Plesk_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                throw $e;
            }
        }
        if (!is_null($accountId) && Plesk_Object_Customer::TYPE_RESELLER == $params["type"]) {
            return $translator->translate("ERROR_RESELLER_ACCOUNT_IS_ALREADY_EXISTS", ["EMAIL" => $params["clientsdetails"]["email"]]);
        }
        $params = array_merge($params, Plesk_Registry::getInstance()->manager->getIps($params));
        if (is_null($accountId)) {
            try {
                $accountId = Plesk_Registry::getInstance()->manager->addAccount($params);
            } catch (Exception $e) {
                if (Plesk_Api::ERROR_OPERATION_FAILED == $e->getCode()) {
                    return $translator->translate("ERROR_ACCOUNT_CREATE_COMMON_MESSAGE");
                }
                throw $e;
            }
        }
        Plesk_Registry::getInstance()->manager->addIpToIpPool($accountId, $params);
        if ("" == $panelExternalId && "" != ($possibleExternalId = Plesk_Registry::getInstance()->manager->getCustomerExternalId($params))) {
            WHMCS\Database\Capsule::table("mod_pleskaccounts")->insert(["userid" => $params["clientsdetails"]["userid"], "usertype" => $params["type"], "panelexternalid" => $possibleExternalId]);
        }
        if (!is_null($accountId) && Plesk_Object_Customer::TYPE_RESELLER == $params["type"]) {
            return "success";
        }
        $params["ownerId"] = $accountId;
        Plesk_Registry::getInstance()->manager->addWebspace($params);
        if (!empty($params["configoptions"])) {
            Plesk_Registry::getInstance()->manager->processAddons($params);
        }
        return "success";
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function plesk_SuspendAccount($params)
{
    try {
        Plesk_Loader::init($params);
        $params["status"] = "root" != $params["serverusername"] && "admin" != $params["serverusername"] ? Plesk_Object_Customer::STATUS_SUSPENDED_BY_RESELLER : Plesk_Object_Customer::STATUS_SUSPENDED_BY_ADMIN;
        switch ($params["type"]) {
            case Plesk_Object_Customer::TYPE_CLIENT:
                Plesk_Registry::getInstance()->manager->setWebspaceStatus($params);
                break;
            case Plesk_Object_Customer::TYPE_RESELLER:
                Plesk_Registry::getInstance()->manager->setResellerStatus($params);
                break;
            default:
                return "success";
        }
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function plesk_UnsuspendAccount($params)
{
    try {
        Plesk_Loader::init($params);
        switch ($params["type"]) {
            case Plesk_Object_Customer::TYPE_CLIENT:
                $params["status"] = Plesk_Object_Webspace::STATUS_ACTIVE;
                Plesk_Registry::getInstance()->manager->setWebspaceStatus($params);
                break;
            case Plesk_Object_Customer::TYPE_RESELLER:
                $params["status"] = Plesk_Object_Customer::STATUS_ACTIVE;
                Plesk_Registry::getInstance()->manager->setResellerStatus($params);
                break;
            default:
                return "success";
        }
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function plesk_TerminateAccount($params)
{
    try {
        Plesk_Loader::init($params);
        switch ($params["type"]) {
            case Plesk_Object_Customer::TYPE_CLIENT:
                Plesk_Registry::getInstance()->manager->deleteWebspace($params);
                break;
            case Plesk_Object_Customer::TYPE_RESELLER:
                Plesk_Registry::getInstance()->manager->deleteReseller($params);
                break;
            default:
                return "success";
        }
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function plesk_ChangePassword($params)
{
    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->setAccountPassword($params);
        if (Plesk_Object_Customer::TYPE_RESELLER == $params["type"]) {
            return "success";
        }
        Plesk_Registry::getInstance()->manager->setWebspacePassword($params);
        return "success";
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function plesk_DetermineUserState($params)
{
    try {
        Plesk_Loader::init($params);
        $translator = Plesk_Registry::getInstance()->translator;
        $accountInfo = Plesk_Registry::getInstance()->manager->getAccountInfo($params);
        $responseString = "";
        if ($accountInfo["login"] == $params["username"]) {
            $responseString = $translator->translate("FIELD_CHANGE_PASSWORD_MAIN_PACKAGE_DESCR");
        } else {
            $primaryAccount = WHMCS\Database\Capsule::table("tblhosting")->where("username", $accountInfo["login"])->join("tblproducts", "tblhosting.packageid", "=", "tblproducts.id")->first(["tblproducts.name", "tblhosting.domain"]);
            $responseString = $translator->translate("FIELD_CHANGE_PASSWORD_ADDITIONAL_PACKAGE_DESCR", ["PACKAGE" => $primaryAccount->name, "DOMAIN" => $primaryAccount->domain]);
        }
    } catch (Exception $e) {
        $responseString = Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
    $response = new WHMCS\Admin("View Clients Products/Services");
    $response->jsonResponse(["string" => $responseString]);
}

function plesk_ChangePackage($params)
{
    try {
        Plesk_Loader::init($params);
        $params = array_merge($params, Plesk_Registry::getInstance()->manager->getIps($params));
        Plesk_Registry::getInstance()->manager->switchSubscription($params);
        if (Plesk_Object_Customer::TYPE_RESELLER == $params["type"]) {
            return "success";
        }
        Plesk_Registry::getInstance()->manager->processAddons($params);
        Plesk_Registry::getInstance()->manager->changeSubscriptionIp($params);
        return "success";
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function _plesk_RetrieveAccountsAllocatedToServer($serverId)
{
    $services = WHMCS\Service\Service::where("server", "=", $serverId)->whereIn("domainstatus", ["Active", "Suspended"])->get();
    $addons = WHMCS\Service\Addon::whereHas("customFieldValues.customField", function ($query) {
        $query->where("fieldname", "Domain");
    })->with("customFieldValues", "customFieldValues.customField")->where("server", "=", $serverId)->whereIn("status", ["Active", "Suspended"])->get();
    $domains = $domainToModel = [];
    $resellerUsernames = ["service" => [], "addon" => []];
    $resellerToModel = [];
    foreach ($services as $service) {
        if ($service->product->type == "reselleraccount") {
            $resellerUsernames["service"][] = $service->username;
            $resellerToModel[$service->username] = $service;
        } else {
            if ($service->domain) {
                $domains[] = $service->domain;
                $domainToModel[$service->domain] = $service;
            }
        }
    }
    foreach ($addons as $addon) {
        if ($addon->productAddon->type == "reselleraccount") {
            $addonUsername = $addon->serviceProperties->get("username");
            if ($addonUsername) {
                $resellerUsernames["addon"][] = $addonUsername;
                $resellerToModel[$addonUsername] = $addon;
            }
        } else {
            foreach ($addon->customFieldValues as $customFieldValue) {
                if ($customFieldValue->customField) {
                    if ($customFieldValue->value) {
                        $domains[] = $customFieldValue->value;
                        $domainToModel[$customFieldValue->value] = $addon;
                    }
                }
            }
        }
    }
    return [$domains, $domainToModel, $resellerUsernames, $resellerToModel];
}

function _plesk_RetrieveResellerAccountUsage($params, $resellerUsernames)
{
    $params["usernames"] = array_merge($resellerUsernames["service"] ?? [], $resellerUsernames["addon"] ?? []);
    $resellerAccountsUsage = [];
    if (!empty($params["usernames"])) {
        Plesk_Loader::init($params);
        $resellerAccountsUsage = Plesk_Registry::getInstance()->manager->getResellersUsage($params);
    }
    return $resellerAccountsUsage;
}

function _plesk_RetrieveCustomerAccountUsage($params, $domains)
{
    if (!$domains) {
        return [];
    }
    $params["domains"] = $domains;
    Plesk_Loader::init($params);
    return Plesk_Registry::getInstance()->manager->getWebspacesUsage($params);
}

function plesk_UsageUpdate($params)
{
    list($domains, $domainToModel, $resellerUsernames, $resellerToModel) = _plesk_retrieveaccountsallocatedtoserver((int) $params["serverid"]);
    try {
        $resellerAccountsUsage = _plesk_retrievereselleraccountusage($params, $resellerUsernames);
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
    if (!empty($resellerAccountsUsage)) {
        foreach ($resellerAccountsUsage as $username => $usage) {
            $domainModel = $resellerToModel[$username];
            if ($domainModel) {
                $domainModel->serviceProperties->save(["diskusage" => $usage["diskusage"], "disklimit" => $usage["disklimit"], "bwusage" => $usage["bwusage"], "bwlimit" => $usage["bwlimit"], "lastupdate" => WHMCS\Carbon::now()->toDateTimeString()]);
            }
        }
    }
    if (!empty($domains)) {
        try {
            $domainsUsage = _plesk_retrievecustomeraccountusage($params, $domains);
        } catch (Exception $e) {
            return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
        }
        foreach ($domainsUsage as $domainName => $usage) {
            $domainModel = $domainToModel[$domainName];
            if ($domainModel) {
                $domainModel->serviceProperties->save(["diskusage" => $usage["diskusage"], "disklimit" => $usage["disklimit"], "bwusage" => $usage["bwusage"], "bwlimit" => $usage["bwlimit"], "lastupdate" => WHMCS\Carbon::now()->toDateTimeString()]);
            }
        }
    }
    return "success";
}

function plesk_TestConnection($params)
{
    try {
        Plesk_Loader::init($params);
        $translator = Plesk_Registry::getInstance()->translator;
        return ["success" => true];
    } catch (Exception $e) {
        return ["error" => Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()])];
    }
}

function plesk_GenerateCertificateSigningRequest($params)
{
    try {
        Plesk_Loader::init($params);
        $result = Plesk_Registry::getInstance()->manager->generateCSR($params);
        if (!$result) {
            throw new WHMCS\Exception\Module\NotServicable("Unable to automatically retrieve Certificate Signing Request from Plesk");
        }
        return ["csr" => $result->certificate->generate->result->csr->__toString(), "key" => $result->certificate->generate->result->pvt->__toString(), "saveData" => true];
    } catch (WHMCS\Exception\Module\NotServicable $e) {
        throw $e;
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function plesk_InstallSsl($params)
{
    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->installSsl($params);
        return "success";
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function plesk_GetMxRecords($params)
{
    try {
        Plesk_Loader::init($params);
        return Plesk_Registry::getInstance()->manager->getMxRecords($params);
    } catch (Exception $e) {
        throw new Exception("MX Retrieval Failed: " . Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]));
    }
}

function plesk_DeleteMxRecords($params)
{
    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->deleteMxRecords($params);
    } catch (Exception $e) {
        throw new Exception("Unable to Delete MX Record: " . Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]));
    }
}

function plesk_AddMxRecords($params)
{
    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->addMxRecords($params);
    } catch (Exception $e) {
        throw new Exception("MX Creation Failed: " . Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]));
    }
}

function plesk_CreateFileWithinDocRoot($params)
{
    $logParams = ["serverhostname" => $params["serverhostname"], "username" => $params["username"], "filename" => $params["filename"], "fileContent" => $params["fileContent"]];
    try {
        $tempFile = tempnam(sys_get_temp_dir(), "plesk");
        if ($tempFile === false) {
            throw new Exception("Plesk: Unable to create DV Auth File: Unable to Create Temp File");
        }
        if (file_put_contents($tempFile, $params["fileContent"]) === false) {
            throw new Exception("Plesk: Unable to create DV Auth File: Unable to Write to Temp File");
        }
        $ftpConnection = false;
        if (function_exists("ftp_ssl_connect")) {
            $ftpConnection = @ftp_ssl_connect($params["serverhostname"]);
        }
        if (!$ftpConnection) {
            $ftpConnection = @ftp_connect($params["serverhostname"]);
        }
        if (!$ftpConnection) {
            throw new Exception("Plesk: Unable to create DV Auth File: FTP Connection Failed");
        }
        if (!@ftp_login($ftpConnection, $params["username"], $params["password"])) {
            throw new Exception("Plesk: Unable to create DV Auth File: FTP Login Failed");
        }
        $list = function () use($ftpConnection) {
            $pwdFiles = ftp_nlist($ftpConnection, "-a .");
            if (!is_array($pwdFiles)) {
                return [];
            }
            return array_filter($pwdFiles, function ($value) {
                return $value != "." && $value != "..";
            });
        };
        $cd = function ($dir, $pwdFiles = NULL) use($ftpConnection, $list) {
            if ($pwdFiles === NULL) {
                $pwdFiles = $list();
            }
            if (in_array($dir, $pwdFiles)) {
                return ftp_chdir($ftpConnection, $dir);
            }
            return false;
        };
        $put = function ($file, $contents) use($ftpConnection) {
            return ftp_put($ftpConnection, $file, $contents, FTP_ASCII);
        };
        ftp_pasv($ftpConnection, true);
        $pwdFiles = ftp_nlist($ftpConnection, ".");
        if ($pwdFiles === false) {
            ftp_pasv($ftpConnection, false);
        }
        if (!$cd("httpdocs", $pwdFiles)) {
            throw new Exception("Plesk: Did not find expected directory 'httpdocs'");
        }
        if (isset($params["dir"])) {
            $pwdFiles = NULL;
            foreach (explode("/", $params["dir"]) as $dir) {
                if (!$cd($dir, $pwdFiles)) {
                    if (!ftp_mkdir($ftpConnection, $dir)) {
                        throw new Exception("Plesk: Unable to create " . $params["dir"]);
                    }
                    if (!ftp_chdir($ftpConnection, $dir)) {
                        throw new Exception("Plesk: Unable to traverse into " . $params["dir"]);
                    }
                    $pwdFiles = [];
                }
            }
            unset($dir);
            unset($pwdFiles);
        }
        if (!$put($params["filename"], $tempFile)) {
            throw new Exception("Plesk: Unable to create DV Auth File: Unable to Upload File: " . json_encode(error_get_last()));
        }
    } catch (Exception $e) {
        logModuleCall("plesk", "plesk_CreateFileWithinDocRoot", $logParams, $e->getMessage(), $e->getMessage());
        throw $e;
    } finally {
        if (is_resource($ftpConnection)) {
            ftp_close($ftpConnection);
        }
        if ($tempFile !== false) {
            unlink($tempFile);
        }
    }
}

function plesk_ListAccounts($params)
{
    try {
        Plesk_Loader::init($params);
        return ["success" => true, "accounts" => Plesk_Registry::getInstance()->manager->listAccounts($params)];
    } catch (Exception $e) {
        return ["error" => Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()])];
    }
}

function plesk_GetDomainCount($params)
{
    try {
        Plesk_Loader::init($params);
        $serverInformation = Plesk_Registry::getInstance()->manager->getServerData([]);
        return ["success" => true, "totalAccounts" => (int) $serverInformation->stat->objects->domains, "ownedAccounts" => (int) $serverInformation->stat->objects->active_domains];
    } catch (Exception $e) {
        return ["error" => Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()])];
    }
}

function plesk_GetUserCount($params)
{
    try {
        $totalCount = $ownedAccounts = 0;
        Plesk_Loader::init($params);
        $mainAccountId = 0;
        try {
            $mainAccount = Plesk_Registry::getInstance()->manager->getResellerByLogin(["username" => $params["serverusername"]]);
            $mainAccountId = $mainAccount["id"];
        } catch (Exception $e) {
        }
        $customers = Plesk_Registry::getInstance()->manager->getCustomers([]);
        foreach ($customers as $customer) {
            $customerData = (array) $customer->data->gen_info;
            if (array_key_exists("owner-login", $customerData) && $customerData["owner-login"] == $params["serverusername"]) {
                $totalCount += 1;
                $ownedAccounts += 1;
            } else {
                if (array_key_exists("owner-id", $customerData) && $customerData["owner-id"] == $mainAccountId) {
                    $totalCount += 1;
                    $ownedAccounts += 1;
                }
            }
        }
        try {
            $resellers = Plesk_Registry::getInstance()->manager->getResellers([]);
            foreach ($resellers as $reseller) {
                $reseller = (array) $reseller;
                $resellerId = $reseller["id"];
                if ($resellerId != $mainAccountId) {
                    $totalCount += count($resellers);
                    $ownedAccounts += count($resellers);
                    $resellerCustomers = Plesk_Registry::getInstance()->manager->getCustomersByOwner(["ownerId" => $resellerId]);
                    $totalCount += count($resellerCustomers);
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
        return ["success" => true, "totalAccounts" => $totalCount, "ownedAccounts" => $ownedAccounts];
    } catch (Exception $e) {
        return ["error" => Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()])];
    }
}

function plesk_GetRemoteMetaData($params)
{
    try {
        $version = "-";
        $loads = ["fifteen" => "0", "five" => "0", "one" => "0"];
        $maxUsers = $maxDomains = "0";
        try {
            Plesk_Loader::init($params);
        } catch (Throwable $t) {
            return ["error" => $t->getMessage()];
        }
        if (empty($params["domains"])) {
            $params["domains"] = [];
        }
        $webspaceUsageInformation = Plesk_Registry::getInstance()->manager->getWebspacesUsage($params);
        $serverInformation = Plesk_Registry::getInstance()->manager->getServerData([]);
        if (isset($serverInformation->stat->version)) {
            $version = (string) $serverInformation->stat->version->plesk_version;
        }
        if (isset($serverInformation->stat->load_avg)) {
            $loads = ["fifteen" => (int) $serverInformation->stat->load_avg->l15 / 100, "five" => (int) $serverInformation->stat->load_avg->l5 / 100, "one" => (int) $serverInformation->stat->load_avg->l1 / 100];
        }
        if (isset($serverInformation->key)) {
            $licenseInfo = [];
            foreach ($serverInformation->key->property as $data) {
                $data = (array) $data;
                $licenseInfo[$data["name"]] = $data["value"];
            }
            if (array_key_exists("lim_cl", $licenseInfo)) {
                $maxUsers = $licenseInfo["lim_cl"];
            }
            if (array_key_exists("lim_dom", $licenseInfo)) {
                $maxDomains = $licenseInfo["lim_dom"];
            }
        }
        return ["version" => $version, "load" => $loads, "max_accounts" => $maxUsers, "max_domains" => $maxDomains, "service_count" => count($webspaceUsageInformation)];
    } catch (Exception $e) {
        return ["error" => Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()])];
    }
}

function plesk_RenderRemoteMetaData($params)
{
    $remoteData = $params["remoteData"];
    if ($remoteData) {
        $metaData = $remoteData->metaData;
        $version = "Unknown";
        $loadOne = $loadFive = $loadFifteen = 0;
        $maxType = "Accounts";
        $maxValue = "Unlimited";
        if (array_key_exists("version", $metaData)) {
            $version = $metaData["version"];
        }
        if (array_key_exists("load", $metaData)) {
            $loadOne = $metaData["load"]["one"];
            $loadFive = $metaData["load"]["five"];
            $loadFifteen = $metaData["load"]["fifteen"];
        }
        if (array_key_exists("max_accounts", $metaData) && 0 < $metaData["max_accounts"]) {
            $maxValue = $metaData["max_accounts"];
        }
        if (array_key_exists("max_domains", $metaData) && 0 < $metaData["max_domains"]) {
            $maxValue = $metaData["max_domains"];
            $maxType = "Domains";
        }
        return "Plesk Version: " . $version . "<br>\nLoad Averages: " . $loadOne . " " . $loadFive . " " . $loadFifteen . "<br>\nLicense Max # of " . $maxType . ": " . $maxValue;
    }
    return "";
}

function plesk_GetSPFRecord($params)
{
    try {
        Plesk_Loader::init($params);
        return Plesk_Registry::getInstance()->manager->getSPFRecord($params);
    } catch (Exception $e) {
        throw new Exception("SPF Retrieval Failed: " . Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]));
    }
}

function plesk_SetSPFRecord($params)
{
    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->setSPFRecord($params);
    } catch (Exception $e) {
        throw new Exception("SPF Set Failed: " . Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]));
    }
}

function plesk_ListAddOnFeatures($params)
{
    Plesk_Loader::init($params);
    $vasManager = new Plesk_ValueAddedServiceManager($params);
    try {
        return $vasManager->getValueAddedServicesList($params);
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function plesk_ProvisionAddOnFeature($params)
{
    try {
        Plesk_Loader::init($params);
        (new Plesk_ValueAddedServiceManager($params))->checkRequiredValueAddedServicesExist($params);
        $params["configoptions"] = [$params["configoption1"] => 1];
        Plesk_Registry::getInstance()->manager->processAddons($params);
        return "success";
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function plesk_DeprovisionAddOnFeature($params)
{
    try {
        Plesk_Loader::init($params);
        (new Plesk_ValueAddedServiceManager($params))->checkRequiredValueAddedServicesExist($params);
        $params["configoptions"] = [$params["configoption1"] => 0];
        Plesk_Registry::getInstance()->manager->processAddons($params);
        return "success";
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
    }
}

function plesk_SuspendAddOnFeature($params)
{
    return plesk_deprovisionaddonfeature($params);
}

function plesk_UnsuspendAddOnFeature($params)
{
    return plesk_provisionaddonfeature($params);
}

function plesk_getProductTypesForAddOn($params)
{
    switch ($params["Feature Name"]) {
        case "Plesk WordPress Toolkit with Smart Updates":
            return ["hostingaccount"];
            break;
        default:
            return ["hostingsaccount", "reselleraccount", "server", "other"];
    }
}

function plesk_ServiceSingleSignOn($params)
{
    Plesk_Loader::init($params);
    try {
        $accountInfo = Plesk_Registry::getInstance()->manager->getAccountInfo($params);
        $redirectUrl = Plesk_Registry::getInstance()->manager->getSsoRedirectUrl($params, $accountInfo["login"]);
        $postLoginUrl = WHMCS\Input\Sanitize::decode(App::get_req_var("success_redirect_url"));
        $postLoginUrl = trim(str_replace(["\r", "\n", ":"], "", $postLoginUrl));
        $postLoginUrl = preg_replace("|/+|", "/", $postLoginUrl);
        if ($postLoginUrl) {
            $redirectUrl .= (strpos($redirectUrl, "?") === false ? "?" : "&") . "success_redirect_url=" . urlencode($postLoginUrl);
        }
        return ["success" => true, "redirectTo" => $redirectUrl];
    } catch (Throwable $e) {
        return ["success" => false, "errorMsg" => "Plesk API Response: " . $e->getMessage()];
    }
}

function plesk_AdminSingleSignOn($params)
{
    Plesk_Loader::init($params);
    try {
        $redirectUrl = Plesk_Registry::getInstance()->manager->getSsoRedirectUrl($params, $params["serverusername"]);
        return ["success" => true, "redirectTo" => $redirectUrl];
    } catch (Throwable $e) {
        return ["success" => false, "errorMsg" => "Plesk API Response: " . $e->getMessage()];
    }
}

function plesk_InstallWordPress($params)
{
    $cliParams = ["domain-name" => $params["domain"], "admin-email" => $params["clientsdetails"]["email"], "table-prefix" => "wp", "site-title" => substr($params["blog_title"] ?? "", 0, 128)];
    if ($cliParams["site-title"] === "") {
        $cliParams["site-title"] = "New Site Title";
    }
    if (isset($params["blog_path"]) && $params["blog_path"] !== "") {
        $path = preg_replace("/(^\\/)|(\\/\$)/", "", trim($params["blog_path"]));
        $isValidPath = function ($path) {
            $patterns = ["/[^a-z\\d\\-_\\/]/i", "/^\\/+/", "/\\/+\$/", "/\\/{2,}/"];
            return !array_reduce($patterns, function ($carry, $pattern) {
                return $carry ?: (bool) preg_match($pattern, $path);
            });
        };
        if (!$isValidPath($path)) {
            return ["error" => LANG::trans("wordpress.invalidPath")];
        }
        $cliParams["path"] = $path;
    }
    if (isset($params["admin_user"]) && $params["admin_user"] !== "") {
        $cliParams["username"] = $params["admin_user"];
    }
    if (isset($params["admin_pass"]) && $params["admin_pass"] !== "") {
        $cliParams["password"] = $params["admin_pass"];
    }
    try {
        Plesk_Loader::init($params);
        $response = (new Plesk_ExtensionCommand())->callWpToolkitCli("install", $params, $cliParams);
        $serviceOrAddon = $params["model"];
        $serviceWpInstances = json_decode(WHMCS\Input\Sanitize::decode($serviceOrAddon->serviceProperties->get("WordPress Instances")), true) ?: [];
        $serviceWpInstances[] = ["blogTitle" => $response["site-title"], "instanceUrl" => $response["protocol"] . "://" . $response["domain"] . "/" . $response["path"]];
        $serviceOrAddon->serviceProperties->save(["WordPress Instances" => WHMCS\Input\Sanitize::encode(json_encode($serviceWpInstances))]);
    } catch (Throwable $e) {
        $error = Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
        return ["error" => $error, "jsonResponse" => ["error" => $error]];
    }
    $response["jsonResponse"] = ["success" => "WordPress has been successfully installed"];
    return $response;
}

function plesk_ResetWordPressAdminPassword($params)
{
    $cliParams = ["instance-id" => $params["instance_id"]];
    if (isset($params["admin_user"]) && $params["admin_user"] !== "") {
        $cliParams["admin-login"] = $params["admin_user"];
    }
    try {
        Plesk_Loader::init($params);
        $response = (new Plesk_ExtensionCommand())->callWpToolkitCli("site-admin-reset-password", $params, $cliParams);
    } catch (Throwable $e) {
        $error = Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
        return ["error" => $error, "jsonResponse" => ["error" => $error]];
    }
    $response["jsonResponse"] = ["success" => "WordPress password has been successfully changed"];
    return $response;
}

function plesk_GetWordPressInstanceInfo($params)
{
    $cliParams = ["instance-id" => $params["instance_id"]];
    try {
        Plesk_Loader::init($params);
        return (new Plesk_ExtensionCommand())->callWpToolkitCli("info", $params, $cliParams);
    } catch (Throwable $e) {
        $error = Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]);
        return ["error" => $error, "jsonResponse" => ["error" => $error]];
    }
}

function plesk_AdminServicesTabFields($params)
{
    $service = $params["model"];
    $productModuleActionSettings = [];
    if ($service instanceof WHMCS\Service\Service) {
        $productModuleActionSettings = json_decode($service->product->getModuleConfigurationSetting("moduleActions")->value, true) ?? [];
    }
    $moduleActions = plesk_eventactions();
    if (empty($productModuleActionSettings["InstallWordPress"]["admin"]) && empty($productModuleActionSettings["InstallWordPress"]["client"]) && empty($productModuleActionSettings["InstallWordPress"]["auto"])) {
        unset($moduleActions["InstallWordPress"]);
    }
    $serviceActionFields = [WHMCS\Table::EMPTY_ROW];
    foreach ($moduleActions as $actionName => $actionData) {
        $html = "";
        if ($actionName === "InstallWordPress") {
            $serviceWpInstances = json_decode(WHMCS\Input\Sanitize::decode($service->serviceProperties->get("WordPress Instances")), true) ?: [];
            if (!empty($serviceWpInstances)) {
                $wpControlHtml = "<select id=\"wordPressInstances\" class=\"form-control\"\" style=\"display: inline-block; margin-right: 10px; max-width: 410px\">";
                foreach ($serviceWpInstances as $instance) {
                    $instancePath = parse_url($instance["instanceUrl"], PHP_URL_PATH);
                    $wpControlHtml .= "<option value=\"" . $instance["instanceUrl"] . "\">" . $instance["blogTitle"] . ($instancePath !== "/" ? " (" . $instancePath . ")" : "") . "</option>";
                }
                $wpControlHtml .= "</select>";
                $wpControlHtml .= "<button type=\"button\" class=\"btn btn-default\" id=\"btnOpenWordPressInstance\" style=\"height: 30px; padding: 2px 10px; margin-bottom: 3px;\" " . (empty($serviceWpInstances) ? " disabled " : "") . ">" . AdminLang::trans("wptk.visitHomepage") . "</button>";
                $wpControlHtml .= "<script>\n(function(\$) {\n    \$(document).ready(function() {\n         \$('#btnOpenWordPressInstance').click(function() {\n               window.open(\$('#wordPressInstances').val());\n         });\n    });\n})(jQuery);\n</script>";
                $serviceActionFields[AdminLang::trans("wptk.manageWordPress")] = $wpControlHtml;
            }
        }
        if (!empty($actionData["AllowAdmin"]) && !empty($productModuleActionSettings[$actionName]["admin"])) {
            foreach ($actionData["Params"] as $paramName => $paramData) {
                $fieldType = ($paramData["Type"] ?? "") === "password" ? "password" : "text";
                $html .= "<input type=\"" . $fieldType . "\" name=\"" . WHMCS\Input\Sanitize::encode($paramName) . "\" size=\"30\" class=\"form-control input-200\" placeholder=\"" . WHMCS\Input\Sanitize::encode($paramData["Description"]) . "\" id=\"input" . WHMCS\Input\Sanitize::encode($paramName) . "\" style=\"display: inline-block; margin-right: 10px;\">";
            }
            $html .= "<button type=\"button\" class=\"btn btn-default\" id=\"btnPerform" . $actionName . "\" style=\"height: 30px; padding: 2px 10px; margin-bottom: 3px;\" " . ($service->status !== WHMCS\Service\Service::STATUS_ACTIVE ? " disabled " : "") . ">" . AdminLang::trans($actionData["FriendlyShortName"]) . "</button>";
            if ($actionName === "InstallWordPress") {
                $html .= "<script>\n(function(\$) {\n    \$(document).ready(function() {\n        \$('#btnPerformInstallWordPress').click(function() {\n            var self = this;\n            var extraVars = '&blog_title=' + escape(\$('#inputblog_title').val())\n                + '&blog_path=' + escape(\$('#inputblog_path').val())\n                + '&admin_pass=' + escape(\$('#inputadmin_pass').val());\n            \n            \$(self).attr('disabled', 'disabled');\n\n            runModuleCommand('custom', 'InstallWordPress', extraVars);\n        });\n    });      \n})(jQuery);\n</script>";
            }
        }
        if ($html) {
            $serviceActionFields[AdminLang::trans($actionData["FriendlyName"])] = $html;
        }
        $serviceActionFields[] = WHMCS\Table::EMPTY_ROW;
    }
    $data = http_build_query(["userid" => $params["userid"], "id" => $params["serviceid"], "aid" => $params["addonid"] ?? NULL, "modop" => "custom", "ac" => "DetermineUserState", "token" => generate_token("plain")]);
    $javascript = "<div id=\"pleskUserState\"></div>\n<script>\n    var targetElement = jQuery(\"div#pleskUserState\");\n    jQuery(document).ready(function() {\n        WHMCS.http.jqClient.jsonPost({\n            url: \"clientsservices.php\",\n            data: \"" . $data . "\",\n            success: function(data) {\n                targetElement.text(data.string);\n            }\n        });\n    });\n</script>";
    return array_merge($serviceActionFields, ["" => $javascript]);
}

function plesk_GetDns($params)
{
    try {
        Plesk_Loader::init($params);
        return Plesk_Registry::getInstance()->manager->getDnsRecords($params);
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("DNS Retrieval Failed: " . Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]));
    }
}

function plesk_ModifyDns($params)
{
    $serverDnsRecords = plesk_getdns($params);
    $recordsToCreate = [];
    $recordsToDelete = [];
    $dnsRecordsToProvision = $params["dnsRecordsToProvision"];
    foreach ($dnsRecordsToProvision as $recordToProvision) {
        if (!$recordToProvision["name"] && !$recordToProvision["host"]) {
            unset($params["dnsRecordsToProvision"]);
            if (0 < count($recordsToDelete)) {
                $params["dnsRecords"] = $recordsToDelete;
                plesk_DeleteDnsRecords($params);
                unset($params["dnsRecords"]);
            }
            if (0 < count($recordsToCreate)) {
                $params["dnsRecords"] = $recordsToCreate;
                plesk_AddDns($params);
                unset($params["dnsRecords"]);
            }
            return true;
        }
        $recordToUpdate = NULL;
        $dnsHost = $recordToProvision["name"] ?: $recordToProvision["host"];
        foreach ($serverDnsRecords as $existingRecord) {
            if ($existingRecord["type"] == $recordToProvision["type"] && Plesk_Utils::dnsNormaliseHostname($existingRecord, $params["domain"]) == $dnsHost) {
                $recordToUpdate = $existingRecord;
                if (is_null($recordToUpdate)) {
                    $recordsToCreate[] = ["type" => $recordToProvision["type"], "host" => $dnsHost, "value" => $recordToProvision["value"], "opt" => $recordToProvision["opt"] ?: false];
                } else {
                    $recordToUpdate["value"] = $recordToProvision["value"];
                    $recordToUpdate["opt"] = $recordToProvision["opt"] ?: false;
                    $recordsToCreate[] = $recordToUpdate;
                    $recordsToDelete[] = $recordToUpdate;
                }
            }
        }
    }
}

function plesk_DeleteDnsRecords($params)
{
    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->deleteDnsRecords($params);
    } catch (Exception $e) {
        throw new Exception("Unable to Delete DNS Records: " . Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]));
    }
}

function plesk_AddDns($params)
{
    $dnsRecords = [];
    foreach ($params["dnsRecords"] as $dnsRecord) {
        if ($dnsRecord["type"] || $dnsRecord["value"]) {
            $dnsRecords[] = ["type" => $dnsRecord["type"], "host" => Plesk_Utils::dnsNormaliseHostname($dnsRecord, $params["domain"]), "value" => $dnsRecord["value"], "opt" => $dnsRecord["opt"] ?: false];
        }
    }
    unset($params["dnsRecords"]);
    $params["dnsRecords"] = $dnsRecords;
    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->addDnsRecords($params);
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate("DNS Adding Failed Failed: " . Plesk_Registry::getInstance()->translator->translate("ERROR_COMMON_MESSAGE", ["CODE" => $e->getCode(), "MESSAGE" => $e->getMessage()]));
    }
    return true;
}

function plesk_MetricItems()
{
    if (!$items) {
        $items = [new WHMCS\UsageBilling\Metrics\Metric("disk_usage", _plesk_translate_metric_key("usagebilling.metric.diskSpace"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\GigaBytes()), new WHMCS\UsageBilling\Metrics\Metric("bandwidth_usage", _plesk_translate_metric_key("usagebilling.metric.bandwidth"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_PERIOD_MONTH, new WHMCS\UsageBilling\Metrics\Units\GigaBytes()), new WHMCS\UsageBilling\Metrics\Metric("mailbox_usage", _plesk_translate_metric_key("usagebilling.metric.emailAccounts"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Accounts("Email Accounts")), new WHMCS\UsageBilling\Metrics\Metric("domain_usage", _plesk_translate_metric_key("usagebilling.metric.addonDomains"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Domains("Addon Domains")), new WHMCS\UsageBilling\Metrics\Metric("aliases_usage", _plesk_translate_metric_key("usagebilling.metric.parkedDomains"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Domains("Parked Domains")), new WHMCS\UsageBilling\Metrics\Metric("subdomain_usage", _plesk_translate_metric_key("usagebilling.metric.subDomains"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Domains("Subdomains")), new WHMCS\UsageBilling\Metrics\Metric("db_usage", _plesk_translate_metric_key("usagebilling.metric.mysqlDatabases"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\WholeNumber("MySQL Databases", "Database", "Databases")), new WHMCS\UsageBilling\Metrics\Metric("mysql_usage", _plesk_translate_metric_key("usagebilling.metric.mysqlDiskUsage"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\GigaBytes()), new WHMCS\UsageBilling\Metrics\Metric("subaccounts", _plesk_translate_metric_key("usagebilling.metric.subAccounts"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Accounts("Sub-Accounts"))];
    }
    return $items;
}

function _plesk_translate_metric_key($key)
{
    if (App::isAdminAreaRequest()) {
        return AdminLang::trans($key);
    }
    return Lang::trans($key);
}

function plesk_MetricProvider($params)
{
    $items = plesk_metricitems();
    $serverUsage = function (WHMCS\UsageBilling\Contracts\Metrics\ProviderInterface $provider, $tenant = NULL) use($params) {
        $usage = [];
        list($domains, $domainToModel, $resellerUsernames, $resellerToModel) = _plesk_retrieveaccountsallocatedtoserver((int) $params["serverid"]);
        if (empty($domains) && empty($resellerUsernames["service"]) && empty($resellerUsernames["addon"])) {
            return $usage;
        }
        $tenants = [];
        $usernames = [];
        $resellersUsernameToDomain = [];
        if ($tenant) {
            if (array_key_exists($tenant, $domainToModel)) {
                $username = $domainToModel[$tenant]->serviceProperties->get("username");
                $domain = $domainToModel[$tenant]->serviceProperties->get("domain");
                $usernames[] = $domain;
                $tenants[$username] = $domain;
            }
            foreach ($resellerUsernames["service"] as $username) {
                $model = $resellerToModel[$username];
                $domain = $model->serviceProperties->get("domain");
                if ($domain === $tenant) {
                    $resellerUsernames = [];
                    $resellerUsernames["service"][] = $username;
                    $resellerUsernames["addon"] = [];
                    $tenants[$username] = $tenant;
                    $resellersUsernameToDomain[$username] = $domain;
                    foreach ($resellerUsernames["addon"] as $username) {
                        $model = $resellerToModel[$username];
                        $domain = $model->serviceProperties->get("domain");
                        if ($domain === $tenant) {
                            $resellerUsernames = [];
                            $resellerUsernames["service"] = [];
                            $resellerUsernames["addon"][] = $username;
                            $tenants[$username] = $tenant;
                            $resellersUsernameToDomain[$username] = $domain;
                        }
                    }
                }
            }
        } else {
            $usernames = $domains;
            foreach ($resellerUsernames["service"] as $username) {
                $model = $resellerToModel[$username];
                $domain = $model->serviceProperties->get("domain");
                $resellersUsernameToDomain[$username] = $domain;
            }
            foreach ($resellerUsernames["addon"] as $username) {
                $model = $resellerToModel[$username];
                $domain = $model->serviceProperties->get("domain");
                $resellersUsernameToDomain[$username] = $domain;
            }
        }
        $hostingStats = _plesk_retrievecustomeraccountusage($params, $usernames);
        $resellerStats = _plesk_retrievereselleraccountusage($params, $resellerUsernames);
        $metrics = $provider->metrics();
        if ($tenant && count($hostingStats) + count($resellerStats) === 0) {
            throw new WHMCS\Exception\Module\NotServicable("The system could not refresh the account metrics. Make certain that you are the account owner.");
        }
        $stats = array_merge($hostingStats, $resellerStats);
        foreach ($stats as $identifier => $data) {
            $resellerDomain = $resellersUsernameToDomain[$identifier] ?? NULL;
            $domainName = $identifier;
            if ($resellerDomain) {
                $domainName = $resellerDomain;
            }
            foreach ($data as $metricName => $metricValue) {
                $metric = $metrics[$metricName] ?? NULL;
                if ($metric) {
                    $metric->units()->suffix();
                    switch ($metric->units()->suffix()) {
                        case "":
                        default:
                            $units = $metric->units();
                            $from = "MB";
                            $metricValue = $units::convert($metricValue, $from, $units->suffix());
                            $usage[$domainName][$metricName] = $metric->withUsage(new WHMCS\UsageBilling\Metrics\Usage($metricValue));
                    }
                }
            }
        }
        return $usage;
    };
    $tenantUsage = function ($tenant, WHMCS\UsageBilling\Contracts\Metrics\ProviderInterface $provider) use($params, $serverUsage) {
        $usage = call_user_func($serverUsage, $provider, $tenant);
        if (isset($usage[$tenant])) {
            return $usage[$tenant];
        }
        return [];
    };
    $provider = new WHMCS\UsageBilling\Metrics\Providers\CallbackUsage($items, $serverUsage, $tenantUsage);
    return $provider;
}

function plesk_CustomActions($CustomActionCollection, $params)
{
    $customActionCollection = new WHMCS\Module\Server\CustomActionCollection();
    $customActionCollection->add(WHMCS\Module\Server\CustomAction::factory("plesk", "plesklogin", "plesk_ServiceSingleSignOn", [$params], ["productsso"], $params["model"]->status === WHMCS\Service\Service::STATUS_ACTIVE));
    return $customActionCollection;
}
