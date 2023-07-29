<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$licensing = DI::make("license");
if (defined("CPANELCONFPACKAGEADDONLICENSE")) {
    exit("License Hacking Attempt Detected");
}
define("CPANELCONFPACKAGEADDONLICENSE", $licensing->isActiveAddon("Configurable Package Addon"));
include_once __DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "Cpanel" . DIRECTORY_SEPARATOR . "ApplicationLink" . DIRECTORY_SEPARATOR . "Server.php";
function cpanel_MetaData()
{
    return ["DisplayName" => "cPanel", "APIVersion" => "1.1", "DefaultNonSSLPort" => "2086", "DefaultSSLPort" => "2087", "ServiceSingleSignOnLabel" => "Login to cPanel", "AdminSingleSignOnLabel" => "Login to WHM", "ApplicationLinkDescription" => "Provides customers with links that utilise Single Sign-On technology to automatically transfer and log your customers into the WHMCS billing &amp; support portal from within the cPanel user interface.", "ListAccountsUniqueIdentifierDisplayName" => "Domain", "ListAccountsUniqueIdentifierField" => "domain", "ListAccountsProductField" => "configoption1"];
}

function cpanel_EventActions()
{
    return ["InstallWordPress" => ["AllowClient" => true, "AllowAdmin" => true, "FriendlyName" => "wptk.installWordPress", "FriendlyShortName" => "wptk.installWordPressShort", "ModuleFunction" => "InstallWordPress", "Params" => ["blog_title" => ["Description" => "Blog Title", "Default" => "New Blog Title", "Type" => "text"], "blog_path" => ["Description" => "WordPress Path", "Default" => "", "Type" => "text"], "admin_pass" => ["Description" => "Admin Password", "Type" => "password", "Disabled" => true]], "Events" => ["aftercreate"]]];
}

function cpanel_ConfigOptions($params)
{
    $resellerSimpleMode = $params["producttype"] == "reselleraccount";
    return ["WHM Package Name" => ["Type" => "text", "Size" => "25", "Loader" => "cpanel_ListPackages", "SimpleMode" => true], "Max FTP Accounts" => ["Type" => "text", "Size" => "5"], "Web Space Quota" => ["Type" => "text", "Size" => "5", "Description" => "MB"], "Max Email Accounts" => ["Type" => "text", "Size" => "5"], "Bandwidth Limit" => ["Type" => "text", "Size" => "5", "Description" => "MB"], "Dedicated IP" => ["Type" => "yesno"], "Shell Access" => ["Type" => "yesno", "Description" => "Check to grant access"], "Max SQL Databases" => ["Type" => "text", "Size" => "5"], "CGI Access" => ["Type" => "yesno", "Description" => "Check to grant access"], "Max Subdomains" => ["Type" => "text", "Size" => "5"], "Frontpage Extensions" => ["Type" => "yesno", "Description" => "Check to grant access"], "Max Parked Domains" => ["Type" => "text", "Size" => "5"], "cPanel Theme" => ["Type" => "text", "Size" => "15"], "Max Addon Domains" => ["Type" => "text", "Size" => "5"], "Limit Reseller by Number" => ["Type" => "text", "Size" => "5", "Description" => "Enter max number of allowed accounts"], "Limit Reseller by Usage" => ["Type" => "yesno", "Description" => "Check to limit by resource usage"], "Reseller Disk Space" => ["Type" => "text", "Size" => "7", "Description" => "MB", "SimpleMode" => $resellerSimpleMode], "Reseller Bandwidth" => ["Type" => "text", "Size" => "7", "Description" => "MB", "SimpleMode" => $resellerSimpleMode], "Allow DS Overselling" => ["Type" => "yesno", "Description" => "MB"], "Allow BW Overselling" => ["Type" => "yesno", "Description" => "MB"], "Reseller ACL List" => ["Type" => "text", "Size" => "20", "SimpleMode" => $resellerSimpleMode], "Add Prefix to Package" => ["Type" => "yesno", "Description" => "Add username_ to package name"], "Configure Nameservers" => ["Type" => "yesno", "Description" => "Setup Custom ns1/ns2 Nameservers"], "Reseller Ownership" => ["Type" => "yesno", "Description" => "Set the reseller to own their own account"]];
}

function cpanel_costrrpl($val)
{
    $val = str_replace("MB", "", $val);
    $val = str_replace("Accounts", "", $val);
    $val = trim($val);
    if ($val == "Yes") {
        $val = true;
    } else {
        if ($val == "No") {
            $val = false;
        } else {
            if ($val == "Unlimited") {
                $val = "unlimited";
            }
        }
    }
    return $val;
}

function cpanel_CreateAccount($params)
{
    $mailinglists = $languageco = "";
    if (CPANELCONFPACKAGEADDONLICENSE) {
        if (isset($params["configoptions"]["Disk Space"])) {
            $params["configoption17"] = cpanel_costrrpl($params["configoptions"]["Disk Space"]);
            $params["configoption3"] = $params["configoption17"];
        }
        if (isset($params["configoptions"]["Bandwidth"])) {
            $params["configoption18"] = cpanel_costrrpl($params["configoptions"]["Bandwidth"]);
            $params["configoption5"] = $params["configoption18"];
        }
        if (isset($params["configoptions"]["FTP Accounts"])) {
            $params["configoption2"] = cpanel_costrrpl($params["configoptions"]["FTP Accounts"]);
        }
        if (isset($params["configoptions"]["Email Accounts"])) {
            $params["configoption4"] = cpanel_costrrpl($params["configoptions"]["Email Accounts"]);
        }
        if (isset($params["configoptions"]["MySQL Databases"])) {
            $params["configoption8"] = cpanel_costrrpl($params["configoptions"]["MySQL Databases"]);
        }
        if (isset($params["configoptions"]["Subdomains"])) {
            $params["configoption10"] = cpanel_costrrpl($params["configoptions"]["Subdomains"]);
        }
        if (isset($params["configoptions"]["FrontPage Extensions"])) {
            $params["configoption11"] = cpanel_costrrpl($params["configoptions"]["FrontPage Extensions"]);
        }
        if (isset($params["configoptions"]["Parked Domains"])) {
            $params["configoption12"] = cpanel_costrrpl($params["configoptions"]["Parked Domains"]);
        }
        if (isset($params["configoptions"]["Addon Domains"])) {
            $params["configoption14"] = cpanel_costrrpl($params["configoptions"]["Addon Domains"]);
        }
        if (isset($params["configoptions"]["Dedicated IP"])) {
            $params["configoption6"] = cpanel_costrrpl($params["configoptions"]["Dedicated IP"]);
        }
        if (isset($params["configoptions"]["CGI Access"])) {
            $params["configoption9"] = cpanel_costrrpl($params["configoptions"]["CGI Access"]);
        }
        if (isset($params["configoptions"]["Shell Access"])) {
            $params["configoption7"] = cpanel_costrrpl($params["configoptions"]["Shell Access"]);
        }
        if (isset($params["configoptions"]["Mailing Lists"])) {
            $mailinglists = cpanel_costrrpl($params["configoptions"]["Mailing Lists"]);
        }
        if (isset($params["configoptions"]["Package Name"])) {
            $params["configoption1"] = $params["configoptions"]["Package Name"];
        }
        if (isset($params["configoptions"]["Language"])) {
            $languageco = $params["configoptions"]["Language"];
        }
    }
    $dedicatedip = $params["configoption6"] ? true : false;
    $cgiaccess = $params["configoption9"] ? true : false;
    $shellaccess = $params["configoption7"] ? true : false;
    $fpextensions = $params["configoption11"] ? true : false;
    try {
        $packages = cpanel_ListPackages($params, false);
    } catch (Exception $e) {
        return $e->getMessage();
    }
    $postfields = [];
    $postfields["username"] = $params["username"];
    $postfields["password"] = $params["password"];
    $postfields["domain"] = $params["domain"];
    $postfields["savepkg"] = 0;
    $packageRequired = true;
    if (isset($params["configoption3"]) && $params["configoption3"] != "") {
        $postfields["quota"] = $params["configoption3"];
        $packageRequired = false;
    }
    if (isset($params["configoption5"]) && $params["configoption5"] != "") {
        $postfields["bwlimit"] = $params["configoption5"];
        $packageRequired = false;
    }
    if ($params["configoption1"] == "") {
        $packageRequired = false;
    }
    if ($dedicatedip) {
        $postfields["ip"] = $dedicatedip;
    }
    if ($cgiaccess) {
        $postfields["cgi"] = $cgiaccess;
    }
    if ($fpextensions) {
        $postfields["frontpage"] = $fpextensions;
    }
    if ($shellaccess) {
        $postfields["hasshell"] = $shellaccess;
    }
    $postfields["contactemail"] = $params["clientsdetails"]["email"];
    if (isset($params["configoption13"]) && $params["configoption13"] != "") {
        $postfields["cpmod"] = $params["configoption13"];
    }
    if (isset($params["configoption2"]) && $params["configoption12"] != "") {
        $postfields["maxftp"] = $params["configoption2"];
    }
    if (isset($params["configoption8"]) && $params["configoption8"] != "") {
        $postfields["maxsql"] = $params["configoption8"];
    }
    if (isset($params["configoption4"]) && $params["configoption4"] != "") {
        $postfields["maxpop"] = $params["configoption4"];
    }
    if (isset($mailinglists) && $mailinglists != "") {
        $postfields["maxlst"] = $mailinglists;
    }
    if (isset($params["configoption10"]) && $params["configoption10"] != "") {
        $postfields["maxsub"] = $params["configoption10"];
    }
    if (isset($params["configoption12"]) && $params["configoption12"] != "") {
        $postfields["maxpark"] = $params["configoption12"];
    }
    if (isset($params["configoption14"]) && $params["configoption14"] != "") {
        $postfields["maxaddon"] = $params["configoption14"];
    }
    if (isset($languageco) && $languageco != "") {
        $postfields["language"] = $languageco;
    }
    try {
        $postfields["plan"] = cpanel_ConfirmPackageName($params["configoption1"], $params["serverusername"], $packages);
    } catch (WHMCS\Exception\Module\NotServicable $e) {
        if ($packageRequired) {
            return $e->getMessage();
        }
        $postfields["plan"] = ($params["configoption22"] ? $params["username"] . "_" : "") . $params["configoption1"];
    } catch (Exception $e) {
        return $e->getMessage();
    }
    $postfields["api.version"] = 1;
    $postfields["reseller"] = 0;
    $output = cpanel_jsonRequest($params, "/json-api/createacct", $postfields);
    if (!is_array($output)) {
        return $output;
    }
    if (array_key_exists("metadata", $output) && $output["metadata"]["result"] == "0") {
        $error = $output["metadata"]["reason"];
        if (!$error) {
            $error = "An unknown error occurred";
        }
        return $error;
    }
    if ($dedicatedip) {
        $newaccountip = $output["data"]["ip"];
        $params["model"]->serviceProperties->save(["dedicatedip" => $newaccountip]);
    }
    try {
        if ($params["type"] == "reselleraccount") {
            $makeowner = $params["configoption24"] ? 1 : 0;
            $output = cpanel_jsonRequest($params, "/json-api/setupreseller", ["user" => $params["username"], "makeowner" => $makeowner]);
            if (!is_array($output)) {
                return $output;
            }
            if (!$output["result"][0]["status"]) {
                $error = $output["result"][0]["statusmsg"];
                if (!$error) {
                    $error = "An unknown error occurred";
                }
                return $error;
            }
            $postVars = "user=" . $params["username"];
            if ($params["configoption16"]) {
                $postVars .= "&enable_resource_limits=1&diskspace_limit=" . urlencode($params["configoption17"]) . "&bandwidth_limit=" . urlencode($params["configoption18"]);
                if ($params["configoption19"]) {
                    $postVars .= "&enable_overselling_diskspace=1";
                }
                if ($params["configoption20"]) {
                    $postVars .= "&enable_overselling_bandwidth=1";
                }
            }
            if ($params["configoption15"]) {
                $postVars .= "&enable_account_limit=1&account_limit=" . urlencode($params["configoption15"]);
            }
            $output = cpanel_jsonRequest($params, "/json-api/setresellerlimits", $postVars);
            if (!is_array($output)) {
                return $output;
            }
            if (!$output["result"][0]["status"]) {
                $error = $output["result"][0]["statusmsg"];
                if (!$error) {
                    $error = "An unknown error occurred";
                }
                return $error;
            }
            $postVars = "reseller=" . $params["username"] . "&acllist=" . urlencode($params["configoption21"]);
            $output = cpanel_jsonRequest($params, "/json-api/setacls", $postVars);
            if (!is_array($output)) {
                return $output;
            }
            if (!$output["result"][0]["status"]) {
                $error = $output["result"][0]["statusmsg"];
                if (!$error) {
                    $error = "An unknown error occurred";
                }
                return $error;
            }
            if ($params["configoption23"]) {
                $postVars = "user=" . $params["username"] . "&nameservers=ns1." . $params["domain"] . ",ns2." . $params["domain"];
                $output = cpanel_jsonRequest($params, "/json-api/setresellernameservers", $postVars);
                if (!is_array($output)) {
                    return $output;
                }
                if (!$output["result"][0]["status"]) {
                    $error = $output["result"][0]["statusmsg"];
                    if (!$error) {
                        $error = "An unknown error occurred";
                    }
                    return $error;
                }
            }
        }
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    return "success";
}

function cpanel_SuspendAccount($params)
{
    if (!$params["username"]) {
        return "Cannot perform action without accounts username";
    }
    try {
        if ($params["type"] == "reselleraccount") {
            $postVars = "api.version=1&user=" . urlencode($params["username"]) . "&reason=" . urlencode($params["suspendreason"]);
            $output = cpanel_jsonRequest($params, "/json-api/suspendreseller", $postVars);
        } else {
            $postVars = "api.version=1&user=" . urlencode($params["username"]) . "&reason=" . urlencode($params["suspendreason"]);
            $output = cpanel_jsonRequest($params, "/json-api/suspendacct", $postVars);
        }
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    if (!is_array($output)) {
        return $output;
    }
    $metadata = isset($output["metadata"]) ? $output["metadata"] : [];
    $resultCode = isset($metadata["result"]) ? $metadata["result"] : 0;
    if ($resultCode == "1") {
        return "success";
    }
    return isset($metadata["reason"]) ? $metadata["reason"] : "An unknown error occurred";
}

function cpanel_UnsuspendAccount($params)
{
    if (!$params["username"]) {
        return "Cannot perform action without accounts username";
    }
    try {
        if ($params["type"] == "reselleraccount") {
            $postVars = "api.version=1&user=" . urlencode($params["username"]);
            $output = cpanel_jsonRequest($params, "/json-api/unsuspendreseller", $postVars);
        } else {
            $postVars = "api.version=1&user=" . urlencode($params["username"]);
            $output = cpanel_jsonRequest($params, "/json-api/unsuspendacct", $postVars);
        }
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    if (!is_array($output)) {
        return $output;
    }
    $metadata = isset($output["metadata"]) ? $output["metadata"] : [];
    $resultCode = isset($metadata["result"]) ? $metadata["result"] : 0;
    if ($resultCode == "1") {
        return "success";
    }
    return isset($metadata["reason"]) ? $metadata["reason"] : "An unknown error occurred";
}

function cpanel_TerminateAccount($params)
{
    if (!$params["username"]) {
        return "Cannot perform action without accounts username";
    }
    try {
        if ($params["type"] == "reselleraccount") {
            $postVars = "reseller=" . $params["username"] . "&terminatereseller=1&verify=I%20understand%20this%20will%20irrevocably%20remove%20all%20the%20accounts%20owned%20by%20the%20reseller%20" . $params["username"];
            $output = cpanel_jsonRequest($params, "/json-api/terminatereseller", $postVars);
            if (!is_array($output)) {
                return $output;
            }
            if (!$output["result"][0]["status"]) {
                $error = $output["result"][0]["statusmsg"];
                if (!$error) {
                    $error = "An unknown error occurred";
                }
                return $error;
            }
        } else {
            $request = ["user" => $params["username"], "keepdns" => 0];
            if (array_key_exists("keepZone", $params)) {
                $request["keepdns"] = $params["keepZone"];
            }
            $output = cpanel_jsonRequest($params, "/json-api/removeacct", $request);
            if (!is_array($output)) {
                return $output;
            }
            if (!$output["result"][0]["status"]) {
                $error = $output["result"][0]["statusmsg"];
                if (!$error) {
                    $error = "An unknown error occurred";
                }
                return $error;
            }
        }
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    return "success";
}

function cpanel_ChangePassword($params)
{
    $postVars = "user=" . $params["username"] . "&pass=" . urlencode($params["password"]);
    try {
        $output = cpanel_jsonRequest($params, "/json-api/passwd", $postVars);
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    if (!is_array($output)) {
        return $output;
    }
    if (!$output["passwd"][0]["status"]) {
        $error = $output["passwd"][0]["statusmsg"];
        if (!$error) {
            $error = "An unknown error occurred";
        }
        return $error;
    }
    return "success";
}

function cpanel_ChangePackage($params)
{
    if (array_key_exists("Package Name", $params["configoptions"])) {
        $params["configoption1"] = $params["configoptions"]["Package Name"];
    }
    try {
        $packages = cpanel_ListPackages($params, false);
        if ($params["serverusername"] !== "root") {
            $hasAllPerm = cpanel_hasEverythingPerm($params);
        }
        if ($params["serverusername"] === "root" || $hasAllPerm) {
            $output = cpanel_ListResellers($params);
        }
        $rusernames = [];
        if (isset($output["data"]) && is_array($output["data"])) {
            $rusernames = $output["data"];
        }
        if ($params["type"] == "reselleraccount") {
            $accountData = cpanel_getUserData($params);
            $newPackage = $params["configoption1"];
            if (!empty($accountData["userData"])) {
                $accountData = $accountData["userData"];
                if ($accountData["product"] != $newPackage) {
                    $postVars = "user=" . $params["username"] . "&pkg=" . urlencode($newPackage);
                    $changePkg = cpanel_jsonRequest($params, "/json-api/changepackage", $postVars);
                    if (!is_array($changePkg)) {
                        return $changePkg;
                    }
                    if (!$changePkg["result"][0]["status"]) {
                        $error = $changePkg["result"][0]["statusmsg"];
                        if (!$error) {
                            $error = "An unknown error occurred";
                        }
                        return $error;
                    }
                }
            }
            if (!in_array($params["username"], $rusernames)) {
                $makeowner = $params["configoption24"] ? 1 : 0;
                $postVars = "user=" . $params["username"] . "&makeowner=" . $makeowner;
                $output = cpanel_jsonRequest($params, "/json-api/setupreseller", $postVars);
                if (!is_array($output)) {
                    return $output;
                }
                if (!$output["result"][0]["status"]) {
                    $error = $output["result"][0]["statusmsg"];
                    if (!$error) {
                        $error = "An unknown error occurred";
                    }
                    return $error;
                }
            }
            if ($params["configoption21"]) {
                $postVars = "reseller=" . $params["username"] . "&acllist=" . urlencode($params["configoption21"]);
                $output = cpanel_jsonRequest($params, "/json-api/setacls", $postVars);
                if (!is_array($output)) {
                    return $output;
                }
                if (!$output["result"][0]["status"]) {
                    $error = $output["result"][0]["statusmsg"];
                    if (!$error) {
                        $error = "An unknown error occurred";
                    }
                    return $error;
                }
            }
            $postVars = "user=" . $params["username"];
            if ($params["configoption16"]) {
                $postVars .= "&enable_resource_limits=1&diskspace_limit=" . urlencode($params["configoption17"]) . "&bandwidth_limit=" . urlencode($params["configoption18"]);
                if ($params["configoption19"]) {
                    $postVars .= "&enable_overselling_diskspace=1";
                }
                if ($params["configoption20"]) {
                    $postVars .= "&enable_overselling_bandwidth=1";
                }
            } else {
                $postVars .= "&enable_resource_limits=0";
            }
            if ($params["configoption15"]) {
                if ($params["configoption15"] == "unlimited") {
                    $postVars .= "&enable_account_limit=1&account_limit=";
                } else {
                    $postVars .= "&enable_account_limit=1&account_limit=" . urlencode($params["configoption15"]);
                }
            } else {
                $postVars .= "&enable_account_limit=0&account_limit=";
            }
            $output = cpanel_jsonRequest($params, "/json-api/setresellerlimits", $postVars);
            if (!is_array($output)) {
                return $output;
            }
            if (!$output["result"][0]["status"]) {
                $error = $output["result"][0]["statusmsg"];
                if (!$error) {
                    $error = "An unknown error occurred";
                }
                return $error;
            }
        } else {
            if (in_array($params["username"], $rusernames)) {
                $postVars = "user=" . $params["username"];
                $output = cpanel_jsonRequest($params, "/json-api/unsetupreseller", $postVars);
            }
            if ($params["configoption1"] != "Custom") {
                try {
                    $plan = cpanel_ConfirmPackageName($params["configoption1"], $params["serverusername"], $packages);
                } catch (Exception $e) {
                    return $e->getMessage();
                }
                $postVars = "user=" . $params["username"] . "&pkg=" . urlencode($plan);
                $output = cpanel_jsonRequest($params, "/json-api/changepackage", $postVars);
                if (!is_array($output)) {
                    return $output;
                }
                if (!$output["result"][0]["status"]) {
                    $error = $output["result"][0]["statusmsg"];
                    if (!$error) {
                        $error = "An unknown error occurred";
                    }
                    return $error;
                }
            }
        }
        if (CPANELCONFPACKAGEADDONLICENSE && count($params["configoptions"])) {
            if (isset($params["configoptions"]["Disk Space"])) {
                $params["configoption3"] = cpanel_costrrpl($params["configoptions"]["Disk Space"]);
                $postVars = "api.version=1&user=" . urlencode($params["username"]) . "&quota=" . urlencode($params["configoption3"]);
                $output = cpanel_jsonRequest($params, "/json-api/editquota", $postVars);
            }
            if (isset($params["configoptions"]["Bandwidth"])) {
                $params["configoption5"] = cpanel_costrrpl($params["configoptions"]["Bandwidth"]);
                $postVars = "api.version=1&user=" . urlencode($params["username"]) . "&bwlimit=" . urlencode($params["configoption5"]);
                $output = cpanel_jsonRequest($params, "/json-api/limitbw", $postVars);
            }
            $postVars = "";
            if (isset($params["configoptions"]["FTP Accounts"])) {
                $params["configoption2"] = cpanel_costrrpl($params["configoptions"]["FTP Accounts"]);
                $postVars .= "MAXFTP=" . $params["configoption2"] . "&";
            }
            if (isset($params["configoptions"]["Email Accounts"])) {
                $params["configoption4"] = cpanel_costrrpl($params["configoptions"]["Email Accounts"]);
                $postVars .= "MAXPOP=" . $params["configoption4"] . "&";
            }
            if (isset($params["configoptions"]["MySQL Databases"])) {
                $params["configoption8"] = cpanel_costrrpl($params["configoptions"]["MySQL Databases"]);
                $postVars .= "MAXSQL=" . $params["configoption8"] . "&";
            }
            if (isset($params["configoptions"]["Subdomains"])) {
                $params["configoption10"] = cpanel_costrrpl($params["configoptions"]["Subdomains"]);
                $postVars .= "MAXSUB=" . $params["configoption10"] . "&";
            }
            if (isset($params["configoptions"]["Parked Domains"])) {
                $params["configoption12"] = cpanel_costrrpl($params["configoptions"]["Parked Domains"]);
                $postVars .= "MAXPARK=" . $params["configoption12"] . "&";
            }
            if (isset($params["configoptions"]["Addon Domains"])) {
                $params["configoption14"] = cpanel_costrrpl($params["configoptions"]["Addon Domains"]);
                $postVars .= "MAXADDON=" . $params["configoption14"] . "&";
            }
            if (isset($params["configoptions"]["CGI Access"])) {
                $params["configoption9"] = cpanel_costrrpl($params["configoptions"]["CGI Access"]);
                $postVars .= "HASCGI=" . $params["configoption9"] . "&";
            }
            if (isset($params["configoptions"]["Shell Access"])) {
                $params["configoption7"] = cpanel_costrrpl($params["configoptions"]["Shell Access"]);
                $postVars .= "shell=" . $params["configoption7"] . "&";
            }
            if ($postVars) {
                $postVars = "user=" . $params["username"] . "&domain=" . $params["domain"] . "&" . $postVars;
                if ($params["configoption13"]) {
                    $postVars .= "CPTHEME=" . $params["configoption13"];
                }
                $output = cpanel_jsonRequest($params, "/json-api/modifyacct", $postVars);
            }
            if (isset($params["configoptions"]["Dedicated IP"])) {
                $params["configoption6"] = cpanel_costrrpl($params["configoptions"]["Dedicated IP"]);
                if ($params["configoption6"]) {
                    $currentip = "";
                    $alreadydedi = false;
                    $postVars = "user=" . $params["username"];
                    $output = cpanel_jsonRequest($params, "/json-api/accountsummary", $postVars);
                    $currentip = $output["acct"][0]["ip"];
                    $output = cpanel_jsonRequest($params, "/json-api/listips", []);
                    foreach ($output["result"] as $result) {
                        if ($result["ip"] == $currentip && $result["mainaddr"] != "1") {
                            $alreadydedi = true;
                        }
                    }
                    if (!$alreadydedi) {
                        foreach ($output["result"] as $result) {
                            $active = $result["active"];
                            $dedicated = $result["dedicated"];
                            $ipaddr = $result["ip"];
                            $used = $result["used"];
                            if ($active && $dedicated && !$used) {
                                $postVars = "user=" . $params["username"] . "&ip=" . $ipaddr;
                                $output = cpanel_jsonRequest($params, "/json-api/setsiteip", $postVars);
                                if ($output["result"][0]["status"]) {
                                    $params["model"]->serviceProperties->save(["dedicatedip" => $ipaddr]);
                                }
                            }
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    return "success";
}

function cpanel_UsageUpdate($params)
{
    $params["overrideTimeout"] = 30;
    try {
        $output = cpanel_jsonRequest($params, "/json-api/listaccts", []);
    } catch (Exception $e) {
        return $e->getMessage();
    }
    $domainData = [];
    $addons = WHMCS\Service\Addon::whereHas("productAddon", function ($query) {
        $query->where("module", "cpanel");
    })->with("productAddon")->where("server", "=", $params["serverid"])->whereIn("status", ["Active", "Suspended"])->get();
    if (is_array($output) && $output["acct"]) {
        foreach ($output["acct"] as $data) {
            $domain = $data["domain"];
            $diskused = $data["diskused"];
            $disklimit = $data["disklimit"];
            $diskused = str_replace("M", "", $diskused);
            $disklimit = str_replace("M", "", $disklimit);
            $domainData[$domain] = ["diskusage" => $diskused, "disklimit" => $disklimit, "lastupdate" => WHMCS\Carbon::now()->toDateTimeString()];
        }
    }
    unset($output);
    $output = cpanel_jsonRequest($params, "/json-api/showbw", []);
    if (is_array($output) && !empty($output["bandwidth"][0]["acct"])) {
        foreach ($output["bandwidth"][0]["acct"] as $data) {
            $domain = $data["maindomain"];
            $bwused = $data["totalbytes"];
            $bwlimit = $data["limit"];
            if (!is_numeric($bwlimit)) {
                $bwlimit = 0;
            }
            $bwused = $bwused / 1048576;
            $bwlimit = $bwlimit / 1048576;
            $domainData[$domain]["bwusage"] = $bwused;
            $domainData[$domain]["bwlimit"] = $bwlimit;
        }
    }
    unset($output);
    foreach ($domainData as $domain => $data) {
        $update = WHMCS\Database\Capsule::table("tblhosting")->where("domain", "=", $domain)->where("server", "=", $params["serverid"])->update($data);
        if (!$update) {
            foreach ($addons as $hostingAddonAccount) {
                $addonDomain = $hostingAddonAccount->serviceProperties->get("domain");
                if ($addonDomain == $domain) {
                    $hostingAddonAccount->serviceProperties->save($data);
                }
            }
        }
        unset($domainData[$domain]);
    }
    unset($domainData);
    $data = WHMCS\Database\Capsule::table("tblhosting")->where("server", "=", $params["serverid"])->where("type", "=", "reselleraccount")->whereIn("domainstatus", ["Active", "Suspended"])->join("tblproducts", "tblproducts.id", "=", "tblhosting.packageid")->pluck("domain", "username")->all();
    foreach ($data as $username => $domain) {
        if ($username) {
            $postVars = "reseller=" . $username;
            try {
                $output = cpanel_jsonRequest($params, "/json-api/resellerstats", $postVars);
                if (is_array($output) && $output["result"]) {
                    $diskUsed = $output["result"]["diskused"];
                    $diskLimit = $output["result"]["diskquota"];
                    if (!$diskLimit) {
                        $diskLimit = $output["result"]["totaldiskalloc"];
                    }
                    $bwUsed = $output["result"]["totalbwused"];
                    $bwLimit = $output["result"]["bandwidthlimit"];
                    if (!$bwLimit) {
                        $bwLimit = $output["result"]["totalbwalloc"];
                    }
                    WHMCS\Database\Capsule::table("tblhosting")->where("domain", "=", $domain)->where("server", "=", $params["serverid"])->update(["diskusage" => $diskUsed, "disklimit" => $diskLimit, "bwusage" => $bwUsed, "bwlimit" => $bwLimit, "lastupdate" => WHMCS\Carbon::now()->toDateTimeString()]);
                }
            } catch (WHMCS\Exception $e) {
                logActivity("Server Usage Reseller Stats Update Failed: " . $e->getMessage() . " - Server ID: " . $params["serverid"]);
            }
        }
        unset($output);
        unset($username);
        unset($domain);
        unset($diskUsed);
        unset($diskLimit);
        unset($bwUsed);
        unset($bwLimit);
    }
    foreach ($addons as $addon) {
        if ($addon->productAddon->type == "reselleraccount") {
            $username = $addon->serviceProperties->get("username");
            $postVars = "reseller=" . $username;
            try {
                $output = cpanel_jsonRequest($params, "/json-api/resellerstats", $postVars);
                if (is_array($output) && $output["result"]) {
                    $diskUsed = $output["result"]["diskused"];
                    $diskLimit = $output["result"]["diskquota"];
                    if (!$diskLimit) {
                        $diskLimit = $output["result"]["totaldiskalloc"];
                    }
                    if (!$diskLimit) {
                        $diskLimit = "Unlimited";
                    }
                    $bwUsed = $output["result"]["totalbwused"];
                    $bwLimit = $output["result"]["bandwidthlimit"];
                    if (!$bwLimit) {
                        $bwLimit = $output["result"]["totalbwalloc"];
                    }
                    if (!$bwLimit) {
                        $bwLimit = "Unlimited";
                    }
                    $addon->serviceProperties->save(["diskusage" => $diskUsed, "disklimit" => $diskLimit, "bwusage" => $bwUsed, "bwlimit" => $bwLimit, "lastupdate" => WHMCS\Carbon::now()->toDateTimeString()]);
                }
            } catch (WHMCS\Exception $e) {
                logActivity("Server Usage Reseller Stats Update Failed: " . $e->getMessage() . " - Server ID: " . $params["serverid"]);
            }
        }
    }
}

function cpanel_req($params, $request, $notxml = false)
{
    try {
        $requestParts = explode("?", $request, 2);
        list($apiCommand, $requestString) = $requestParts;
        $data = cpanel_curlRequest($params, $apiCommand, $requestString);
    } catch (WHMCS\Exception $e) {
        return $e->getMessage();
    }
    if ($notxml) {
        $results = $data;
    } else {
        if (strpos($data, "Brute Force Protection")) {
            $results = "WHM has imposed a Brute Force Protection Block - Contact cPanel for assistance";
        } else {
            if (strpos($data, "<form action=\"/login/\" method=\"POST\">")) {
                $results = "Login Failed";
            } else {
                if (strpos($data, "SSL encryption is required")) {
                    $results = "SSL Required for Login";
                } else {
                    if (strpos($data, "META HTTP-EQUIV=\"refresh\" CONTENT=") && !$usessl) {
                        $results = "You must enable SSL Mode";
                    } else {
                        if (substr($data, 0, 1) != "<") {
                            $data = substr($data, strpos($data, "<"));
                        }
                        $results = XMLtoARRAY($data);
                        if ($results["CPANELRESULT"]["DATA"]["REASON"] == "Access denied") {
                            $results = "Login Failed";
                        }
                    }
                }
            }
        }
    }
    unset($data);
    return $results;
}

function cpanel_curlRequest($params, $apiCommand, $postVars, $stringsToMask = [])
{
    $whmIP = $params["serverip"];
    $whmHostname = $params["serverhostname"];
    $whmUsername = $params["serverusername"];
    $whmPassword = $params["serverpassword"];
    $whmHttpPrefix = $params["serverhttpprefix"];
    $whmPort = $params["serverport"];
    $whmAccessHash = preg_replace("'(\r|\n)'", "", $params["serveraccesshash"]);
    $whmSSL = $params["serversecure"] ? true : false;
    $curlTimeout = array_key_exists("overrideTimeout", $params) ? $params["overrideTimeout"] : 400;
    if (!$whmIP && !$whmHostname) {
        throw new WHMCS\Exception\Module\InvalidConfiguration("You must provide either an IP or Hostname for the Server");
    }
    if (!$whmUsername) {
        throw new WHMCS\Exception\Module\InvalidConfiguration("WHM Username is missing for the selected server");
    }
    if ($whmAccessHash) {
        $authStr = "WHM " . $whmUsername . ":" . $whmAccessHash;
    } else {
        if ($whmPassword) {
            $authStr = "Basic " . base64_encode($whmUsername . ":" . $whmPassword);
        } else {
            throw new WHMCS\Exception\Module\InvalidConfiguration("You must provide either an API Token (Recommended) or Password for WHM for the selected server");
        }
    }
    if (substr($apiCommand, 0, 1) == "/") {
        $apiCommand = substr($apiCommand, 1);
    }
    $url = $whmHttpPrefix . "://" . ($whmIP ? $whmIP : $whmHostname) . ":" . $whmPort . "/" . $apiCommand;
    if (is_array($postVars)) {
        $requestString = build_query_string($postVars);
    } else {
        if (is_string($postVars)) {
            $requestString = $postVars;
        } else {
            $requestString = "";
        }
    }
    $curlOptions = ["CURLOPT_HTTPHEADER" => ["Authorization: " . $authStr], "CURLOPT_TIMEOUT" => $curlTimeout];
    $ch = curlCall($url, $requestString, $curlOptions, true);
    $data = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new WHMCS\Exception\Module\NotServicable("Connection Error: " . curl_error($ch) . "(" . curl_errno($ch) . ")");
    }
    if (strpos($data, "META HTTP-EQUIV=\"refresh\" CONTENT=") && !$whmSSL) {
        throw new WHMCS\Exception\Module\NotServicable("Please enable SSL Mode for this server and try again.");
    }
    if (!$data) {
        throw new WHMCS\Exception\Module\NotServicable("No response received. Please check connection settings.");
    }
    curl_close($ch);
    $action = str_replace(["/xml-api/", "/json-api/"], "", $apiCommand);
    logModuleCall("cpanel", $action, $requestString, $data, "", $stringsToMask);
    return $data;
}

function cpanel_jsonRequest($params, $apiCommand, $postVars, $stringsToMask = [])
{
    $data = cpanel_curlrequest($params, $apiCommand, $postVars, $stringsToMask);
    if ($data) {
        $decodedData = json_decode($data, true);
        if (is_null($decodedData) && json_last_error() !== JSON_ERROR_NONE) {
            throw new WHMCS\Exception\Module\NotServicable($data);
        }
        if (isset($decodedData["cpanelresult"]["error"])) {
            throw new WHMCS\Exception\Module\GeneralError($decodedData["cpanelresult"]["error"]);
        }
        if (isset($decodedData["statusmsg"]) && $decodedData["statusmsg"] === "Permission Denied") {
            throw new WHMCS\Exception\Module\GeneralError($decodedData["statusmsg"]);
        }
        if (isset($decodedData["error"])) {
            throw new WHMCS\Exception\Module\GeneralError($decodedData["error"]);
        }
        return $decodedData;
    }
    throw new WHMCS\Exception\Module\NotServicable("No Response from WHM API");
}

function cpanel_ClientArea($params)
{
    $hasWordPressToolkitDeluxe = false;
    $wptkDeluxeAddonId = 0;
    $model = $params["model"];
    $productModuleActionSettings = [];
    if ($model instanceof WHMCS\Service\Service) {
        $wpProductAddons = WHMCS\Config\Module\ModuleConfiguration::where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", "wp-toolkit-deluxe")->pluck("entity_id")->toArray();
        foreach ($model->addons as $addon) {
            if ($addon->moduleConfiguration && $addon->addonId && $addon->status === WHMCS\Utility\Status::ACTIVE && $addon->provisioningType !== WHMCS\Product\Addon::PROVISIONING_TYPE_STANDARD && in_array($addon->addonId, $wpProductAddons)) {
                $hasWordPressToolkitDeluxe = true;
                $wptkDeluxeAddonId = $addon->id;
                $productModuleActionSettings = json_decode($model->product->getModuleConfigurationSetting("moduleActions")->value, true) ?? [];
            }
        }
    }
    $wpInstances = json_decode(WHMCS\Input\Sanitize::decode($model->serviceProperties->get("WordPress Instances")), true) ?: [];
    $wpInstances = array_map(function ($item) {
        return array_merge($item, ["path" => rtrim(parse_url($item["instanceUrl"], PHP_URL_PATH), "/")]);
    }, $wpInstances);
    return ["overrideDisplayTitle" => ucfirst($params["domain"]), "tabOverviewReplacementTemplate" => "overview.tpl", "tabOverviewModuleOutputTemplate" => "loginbuttons.tpl", "templateVariables" => ["hasWPTDeluxe" => $hasWordPressToolkitDeluxe, "wptkDeluxeAddonId" => $wptkDeluxeAddonId, "allowWpClientInstall" => $productModuleActionSettings["InstallWordPress"]["client"] ?? false, "serviceId" => $model->id, "wpDomain" => $model->domain, "wpInstances" => $wpInstances]];
}

function cpanel_TestConnection($params)
{
    try {
        $response = cpanel_jsonrequest($params, "/json-api/version", []);
        if (is_array($response) && array_key_exists("version", $response)) {
            return ["success" => true];
        }
        return ["error" => $response];
    } catch (Throwable $e) {
        return ["error" => $e->getMessage()];
    }
}

function cpanel_SingleSignOn($params, $user, $service, $app = "")
{
    if (!$user) {
        return "Username is required for login.";
    }
    $vars = ["api.version" => "1", "user" => $user, "service" => $service];
    if ($app) {
        $vars["app"] = $app;
    }
    try {
        $response = cpanel_jsonrequest($params, "/json-api/create_user_session", $vars);
        $resultCode = isset($response["metadata"]["result"]) ? $response["metadata"]["result"] : 0;
        if ($resultCode == "1") {
            $redirURL = $response["data"]["url"];
            if (!$params["serversecure"]) {
                $secureParts = ["https:", ":2087", ":2083", ":2096"];
                $insecureParts = ["http:", ":2086", ":2082", ":2095"];
                $redirURL = str_replace($secureParts, $insecureParts, $redirURL);
            }
            return ["success" => true, "redirectTo" => $redirURL];
        }
        if (isset($response["cpanelresult"]["data"]["reason"])) {
            return ["success" => false, "errorMsg" => "cPanel API Response: " . $response["cpanelresult"]["data"]["reason"]];
        }
        if (isset($response["metadata"]["reason"])) {
            return ["success" => false, "errorMsg" => "cPanel API Response: " . $response["metadata"]["reason"]];
        }
    } catch (WHMCS\Exception\Module\InvalidConfiguration $e) {
        return ["success" => false, "errorMsg" => "cPanel API Configuration Problem: " . $e->getMessage()];
    } catch (WHMCS\Exception\Module\NotServicable $e) {
        return ["success" => false, "errorMsg" => "cPanel API Unreachable: " . $e->getMessage()];
    } catch (WHMCS\Exception $e) {
    }
    return ["success" => false];
}

function cpanel_ServiceSingleSignOn($params)
{
    $user = $params["username"];
    $app = App::get_req_var("app");
    if ($params["producttype"] == "reselleraccount") {
        if ($app) {
            $service = "cpaneld";
        } else {
            $service = "whostmgrd";
        }
    } else {
        $service = "cpaneld";
    }
    return cpanel_singlesignon($params, $user, $service, $app);
}

function cpanel_AdminSingleSignOn($params)
{
    $user = $params["serverusername"];
    $service = "whostmgrd";
    return cpanel_singlesignon($params, $user, $service);
}

function cpanel_ClientAreaAllowedFunctions()
{
    return ["CreateEmailAccount"];
}

function cpanel_CreateEmailAccount($params)
{
    $vars = ["cpanel_jsonapi_user" => $params["username"], "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "Email", "cpanel_jsonapi_func" => "addpop", "domain" => $params["domain"], "email" => App::get_req_var("email_prefix"), "password" => App::get_req_var("email_pw"), "quota" => (int) App::get_req_var("email_quota")];
    try {
        $response = cpanel_jsonrequest($params, "/json-api/cpanel", $vars);
        $resultCode = isset($response["cpanelresult"]["event"]["result"]) ? $response["cpanelresult"]["event"]["result"] : 0;
        if ($resultCode == "1") {
            return ["jsonResponse" => ["success" => true]];
        }
    } catch (WHMCS\Exception\Module\GeneralError $e) {
        return ["jsonResponse" => ["success" => false, "errorMsg" => $e->getMessage()]];
    } catch (WHMCS\Exception\Module\InvalidConfiguration $e) {
        logActivity("cPanel Client Quick Email Create Failed: API Configuration Problem - " . $e->getMessage());
    } catch (WHMCS\Exception\Module\NotServicable $e) {
        logActivity("cPanel Client Quick Email Create Failed: API Unreachable - " . $e->getMessage());
    } catch (WHMCS\Exception $e) {
        logActivity("cPanel Client Quick Email Create Failed: Unknown Error - " . $e->getMessage());
    }
    return ["jsonResponse" => ["success" => false, "errorMsg" => "An error occurred. Please contact support."]];
}

function cpanel__addErrorToList($errorMsg, &$errors)
{
    if (!$errorMsg) {
        return NULL;
    }
    if (preg_match("/\\s+\\(XID ([a-z\\d]+)\\)\\s+/i", $errorMsg, $matches)) {
        $xidFull = trim($matches[0]);
        $xidCode = $matches[1];
        $cleanMsg = str_replace($xidFull, " ", $errorMsg);
        $errors[$cleanMsg][] = $xidCode;
    } else {
        $errors[$errorMsg] = [];
    }
}

function cpanel__formatErrorList($errors)
{
    $ret = [];
    $maxXids = 5;
    foreach ($errors as $errorMsg => $xids) {
        $xidCount = is_array($xids) ? count($xids) : 0;
        if ($xidCount) {
            if ($maxXids < $xidCount) {
                $andMore = " and " . ($xidCount - $maxXids) . " more.";
                $xids = array_slice($xids, 0, $maxXids);
            } else {
                $andMore = "";
            }
            $xidList = " XIDs: " . implode(", ", $xids) . $andMore;
        } else {
            $xidList = "";
        }
        $ret[] = $errorMsg . $xidList;
    }
    return $ret;
}

function cpanel_GetSupportedApplicationLinks()
{
    $appLinksData = file_get_contents(ROOTDIR . "/modules/servers/cpanel/data/application_links.json");
    $appLinks = json_decode($appLinksData, true);
    if (array_key_exists("supportedApplicationLinks", $appLinks)) {
        return $appLinks["supportedApplicationLinks"];
    }
    return [];
}

function cpanel_GetRemovedApplicationLinks()
{
    $appLinksData = file_get_contents(ROOTDIR . "/modules/servers/cpanel/data/application_links.json");
    $appLinks = json_decode($appLinksData, true);
    if (array_key_exists("disabledApplicationLinks", $appLinks)) {
        return $appLinks["disabledApplicationLinks"];
    }
    return [];
}

function cpanel_IsApplicationLinkingSupportedByServer($params)
{
    try {
        $cpanelResponse = cpanel_jsonrequest($params, "/json-api/applist", "api.version=1");
        $resultCode = isset($cpanelResponse["metadata"]["result"]) ? $cpanelResponse["metadata"]["result"] : 0;
        if (!$resultCode) {
            $resultCode = isset($cpanelResponse["cpanelresult"]["data"]["result"]) ? $cpanelResponse["cpanelresult"]["data"]["result"] : 0;
        }
        if (0 < $resultCode) {
            return ["isSupported" => in_array("create_integration_link", $cpanelResponse["data"]["app"])];
        }
        if (isset($cpanelResponse["cpanelresult"]["error"])) {
            $errorMsg = $cpanelResponse["cpanelresult"]["error"];
        } else {
            if (isset($cpanelResponse["metadata"]["reason"])) {
                $errorMsg = $cpanelResponse["metadata"]["reason"];
            } else {
                $errorMsg = "Server response: " . preg_replace("/([\\d\"]),\"/", "\$1, \"", json_encode($cpanelResponse));
            }
        }
    } catch (WHMCS\Exception $e) {
        $errorMsg = $e->getMessage();
    }
    return ["errorMsg" => $errorMsg];
}

function cpanel_CreateApplicationLink($params)
{
    $systemUrl = $params["systemUrl"];
    $tokenEndpoint = $params["tokenEndpoint"];
    $clientCollection = $params["clientCredentialCollection"];
    $appLinks = $params["appLinks"];
    $stringsToMask = [];
    $commands = [];
    foreach ($clientCollection as $client) {
        $secret = $client->decryptedSecret;
        $identifier = $client->identifier;
        $apiData = ["api.version" => 1, "user" => $client->service->username, "group_id" => "whmcs", "label" => "Billing & Support", "order" => "1"];
        $commands[] = "command=create_integration_group?" . urlencode(http_build_query($apiData));
        foreach ($appLinks as $scopeName => $appLinkParams) {
            $queryParams = ["scope" => "clientarea:sso " . $scopeName, "module_type" => "server", "module" => "cpanel"];
            $fallbackUrl = $appLinkParams["fallback_url"];
            $fallbackUrl .= (strpos($fallbackUrl, "?") ? "&" : "?") . "ssoredirect=1";
            unset($appLinkParams["fallback_url"]);
            $apiData = ["api.version" => 1, "user" => $client->service->username, "subscriber_unique_id" => $identifier, "url" => $systemUrl . $fallbackUrl, "token" => $secret, "autologin_token_url" => $tokenEndpoint . "?" . http_build_query($queryParams)];
            $commands[] = "command=create_integration_link?" . urlencode(http_build_query($apiData + $appLinkParams));
            $stringsToMask[] = urlencode(urlencode($secret));
        }
    }
    $errors = [];
    try {
        $cpanelResponse = cpanel_jsonrequest($params, "/json-api/batch", "api.version=1&" . implode("&", $commands), $stringsToMask);
        if ($cpanelResponse["metadata"]["result"] == 0) {
            foreach ($cpanelResponse["data"]["result"] as $key => $values) {
                if ($values["metadata"]["result"] == 0) {
                    $reasonMsg = isset($values["metadata"]["reason"]) ? $values["metadata"]["reason"] : "";
                    cpanel__adderrortolist($reasonMsg, $errors);
                }
            }
        }
    } catch (Throwable $e) {
        cpanel__adderrortolist($e->getMessage(), $errors);
    }
    return cpanel__formaterrorlist($errors);
}

function cpanel_DeleteApplicationLink($params)
{
    $clientCollection = $params["clientCredentialCollection"];
    $appLinks = $params["appLinks"];
    $commands = [];
    foreach ($clientCollection as $client) {
        $apiData = ["api.version" => 1, "user" => $client->service->username, "group_id" => "whmcs"];
        $commands[] = "command=remove_integration_group?" . urlencode(http_build_query($apiData));
        foreach ($appLinks as $scopeName => $appLinkParams) {
            $apiData = ["api.version" => 1, "user" => $client->service->username, "app" => $appLinkParams["app"]];
            $commands[] = "command=remove_integration_link?" . urlencode(http_build_query($apiData));
        }
    }
    $errors = [];
    try {
        $cpanelResponse = cpanel_jsonrequest($params, "/json-api/batch", "api.version=1&" . implode("&", $commands));
        if ($cpanelResponse["metadata"]["result"] == 0) {
            foreach ($cpanelResponse["data"]["result"] as $key => $values) {
                if ($values["metadata"]["result"] == 0) {
                    $reasonMsg = isset($values["metadata"]["reason"]) ? $values["metadata"]["reason"] : "";
                    cpanel__adderrortolist($reasonMsg, $errors);
                }
            }
        }
    } catch (Throwable $e) {
        cpanel__adderrortolist($e->getMessage(), $errors);
    }
    return cpanel__formaterrorlist($errors);
}

function cpanel_ConfirmPackageName($package, $username, $packages)
{
    switch ($username) {
        case "":
        case "root":
            if (array_key_exists($package, $packages)) {
                return $package;
            }
            break;
        default:
            if (array_key_exists($username . "_" . $package, $packages)) {
                return $username . "_" . $package;
            }
            if (array_key_exists($package, $packages)) {
                return $package;
            }
            throw new WHMCS\Exception\Module\NotServicable("Product attribute Package Name \"" . $package . "\" not found on server");
    }
}

function cpanel_ListPackages($params, $removeUsername = true)
{
    $result = cpanel_jsonrequest($params, "/json-api/listpkgs", "");
    if (array_key_exists("cpanelresult", $result) && array_key_exists("error", $result["cpanelresult"])) {
        throw new WHMCS\Exception\Module\NotServicable($result["cpanelresult"]["error"]);
    }
    $return = [];
    if (isset($result["package"])) {
        foreach ($result["package"] as $package) {
            $packageName = $params["serverusername"] == "root" || !$removeUsername ? $package["name"] : str_replace($params["serverusername"] . "_", "", $package["name"]);
            $return[$packageName] = ucwords($packageName);
        }
    }
    return $return;
}

function cpanel_AutoPopulateServerConfig($params)
{
    $cpanelResponse = cpanel_jsonrequest($params, "/json-api/gethostname", "api.version=1");
    $hostname = $cpanelResponse["data"]["hostname"];
    $name = explode(".", $hostname, 2);
    $name = $name[0];
    $primaryIp = "";
    $cpanelResponse = cpanel_jsonrequest($params, "/json-api/get_shared_ip", "api.version=1");
    if (array_key_exists("ip", $cpanelResponse["data"]) && $cpanelResponse["data"]["ip"]) {
        $primaryIp = trim($cpanelResponse["data"]["ip"]);
    }
    $assignedIps = [];
    $cpanelResponse = cpanel_jsonrequest($params, "/json-api/listips", "api.version=1");
    if (isset($cpanelResponse["data"]["ip"]) && is_array($cpanelResponse["data"]["ip"])) {
        foreach ($cpanelResponse["data"]["ip"] as $key => $data) {
            if (trim($data["public_ip"])) {
                if (!$primaryIp && $data["mainaddr"]) {
                    $primaryIp = $data["public_ip"];
                } else {
                    if ($primaryIp != $data["public_ip"]) {
                        $assignedIps[] = $data["public_ip"];
                    }
                }
            }
        }
    }
    $cpanelResponse = cpanel_jsonrequest($params, "/json-api/get_nameserver_config", "api.version=1");
    $nameservers = is_array($cpanelResponse["data"]["nameservers"]) ? $cpanelResponse["data"]["nameservers"] : [];
    return ["name" => $name, "hostname" => $hostname, "primaryIp" => $primaryIp, "assignedIps" => $assignedIps, "nameservers" => $nameservers];
}

function cpanel_GenerateCertificateSigningRequest($params)
{
    $certificate = $params["certificateInfo"];
    if (empty($certificate["city"]) || empty($certificate["state"]) || empty($certificate["country"])) {
        throw new WHMCS\Exception("A valid city, state and country are required to generate a Certificate Signing Request. Please set these values in the clients profile and try again.");
    }
    $command = "/json-api/cpanel";
    $postVars = ["keysize" => "2048", "friendly_name" => $certificate["domain"] . time(), "cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "3", "cpanel_jsonapi_module" => "SSL", "cpanel_jsonapi_func" => "generate_key"];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if ($response["result"]["errors"]) {
        $error = is_array($response["result"]["errors"]) ? implode(". ", $response["result"]["errors"]) : $response["result"]["errors"];
        throw new WHMCS\Exception("cPanel: Key Generation Failed: " . $error);
    }
    $keyId = $response["result"]["data"]["id"];
    $postVars = ["domains" => $certificate["domain"], "countryName" => $certificate["country"], "stateOrProvinceName" => $certificate["state"], "localityName" => $certificate["city"], "organizationName" => $certificate["orgname"] ?: "N/A", "organizationalUnitName" => $certificate["orgunit"], "emailAddress" => $certificate["email"], "key_id" => $keyId, "cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "3", "cpanel_jsonapi_module" => "SSL", "cpanel_jsonapi_func" => "generate_csr"];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if (isset($response["result"]["status"]) && $response["result"]["status"] == 1) {
        $csr = $response["result"]["data"]["text"];
        return $csr;
    }
    $errorMsg = isset($response["result"]["errors"]) ? is_array($response["result"]["errors"]) ? implode(". ", $response["result"]["errors"]) : $response["result"]["errors"] : json_encode($response);
    throw new WHMCS\Exception("cPanel: CSR Generation Failed: " . $errorMsg);
}

function cpanel_GetDocRoot($params)
{
    $command = "/json-api/cpanel";
    $postVars = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "DomainLookup", "cpanel_jsonapi_func" => "getdocroot", "domain" => $params["domain"]];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if (isset($response["cpanelresult"]["error"]) && $response["cpanelresult"]["error"]) {
        throw new WHMCS\Exception("cPanel: Unable to locate docroot: " . json_encode($response));
    }
    return $response["cpanelresult"]["data"][0]["docroot"];
}

function cpanel_CreateFileWithinDocRoot($params)
{
    $command = "/json-api/cpanel";
    $postVars = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "DomainLookup", "cpanel_jsonapi_func" => "getdocroot", "domain" => $params["certificateDomain"]];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if (isset($response["cpanelresult"]["error"]) && $response["cpanelresult"]["error"]) {
        throw new WHMCS\Exception("cPanel: Unable to locate docroot: " . json_encode($response));
    }
    $dir = array_key_exists("dir", $params) ? $params["dir"] : "";
    $basePath = $response["cpanelresult"]["data"][0]["reldocroot"];
    if ($dir) {
        $dirParts = explode("/", $dir);
        foreach ($dirParts as $dirPart) {
            $command = "/json-api/cpanel";
            $postVars = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "Fileman", "cpanel_jsonapi_func" => "mkdir", "path" => $basePath, "name" => $dirPart];
            try {
                cpanel_jsonrequest($params, $command, $postVars);
            } catch (Exception $e) {
                if (stripos($e->getMessage(), "file exists") === false) {
                    throw $e;
                }
            }
            $basePath .= "/" . $dirPart;
        }
    }
    $command = "/json-api/cpanel";
    $postVars = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "3", "cpanel_jsonapi_module" => "Fileman", "cpanel_jsonapi_func" => "save_file_content", "dir" => $basePath, "file" => $params["filename"], "from_charset" => "utf-8", "to_charset" => "utf-8", "content" => $params["fileContent"]];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if (isset($response["result"]["errors"]) && $response["result"]["errors"]) {
        throw new WHMCS\Exception("cPanel: Unable to create DV Auth File: " . json_encode($response));
    }
}

function cpanel_InstallSsl($params)
{
    $command = "/json-api/cpanel";
    $postVars = ["certificate" => $params["certificate"], "cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "3", "cpanel_jsonapi_module" => "SSL", "cpanel_jsonapi_func" => "fetch_key_and_cabundle_for_certificate"];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if ($response["result"]["status"] == 0) {
        throw new WHMCS\Exception($response["result"]["messages"]);
    }
    $key = $response["data"]["key"];
    $postVars = ["domain" => $params["certificateDomain"], "cert" => $params["certificate"], "key" => $key, "cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "3", "cpanel_jsonapi_module" => "SSL", "cpanel_jsonapi_func" => "install_ssl"];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if ($response["result"]["status"] == 0) {
        if ($response["result"]["messages"]) {
            if (is_array($response["result"]["messages"])) {
                $error = implode(" ", $response["result"]["messages"]);
            } else {
                $error = $response["result"]["messages"];
            }
        } else {
            if ($response["result"]["errors"]) {
                if (is_array($response["result"]["errors"])) {
                    $error = implode(" ", $response["result"]["errors"]);
                } else {
                    $error = $response["result"]["errors"];
                }
            } else {
                $error = "An unknown error occurred";
            }
        }
        throw new WHMCS\Exception($error);
    }
}

function cpanel_GetMxRecords($params)
{
    $domain = $params["mxDomain"];
    $command = "/json-api/cpanel";
    $postVars = ["domain" => $domain, "cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "Email", "cpanel_jsonapi_func" => "listmx"];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if (array_key_exists("error", $response["cpanelresult"]) && $response["cpanelresult"]["error"]) {
        $error = is_array($response["cpanelresult"]["error"]) ? implode(". ", $response["cpanelresult"]["error"]) : $response["cpanelresult"]["error"];
        throw new WHMCS\Exception("MX Retrieval Failed: " . $error);
    }
    return ["mxRecords" => $response["cpanelresult"]["data"][0]["entries"], "mxType" => $response["cpanelresult"]["data"][0]["detected"]];
}

function cpanel_DeleteMxRecords($params)
{
    $domain = $params["mxDomain"];
    foreach ($params["mxRecords"] as $mxDatum) {
        $mxRecord = $mxDatum["mx"];
        $priority = $mxDatum["priority"];
        $command = "/json-api/cpanel";
        $postVars = ["domain" => $domain, "exchange" => $mxRecord, "preference" => $priority, "cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "Email", "cpanel_jsonapi_func" => "delmx"];
        $response = cpanel_jsonrequest($params, $command, $postVars);
        if (array_key_exists("error", $response["cpanelresult"]) && $response["cpanelresult"]["error"]) {
            $error = is_array($response["cpanelresult"]["error"]) ? implode(". ", $response["cpanelresult"]["error"]) : $response["cpanelresult"]["error"];
            throw new WHMCS\Exception("Unable to Delete Record: " . $error);
        }
    }
}

function cpanel_AddMxRecords($params)
{
    $domain = $params["mxDomain"];
    foreach ($params["mxRecords"] as $mxRecord => $priority) {
        $command = "/json-api/cpanel";
        $postVars = ["alwaysaccept" => $params["alwaysAccept"], "domain" => $domain, "exchange" => $mxRecord, "preference" => $priority, "oldexchange" => "", "oldpreference" => "", "cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "Email", "cpanel_jsonapi_func" => "addmx"];
        $response = cpanel_jsonrequest($params, $command, $postVars);
        if (array_key_exists("error", $response["cpanelresult"]) && $response["cpanelresult"]["error"]) {
            $error = is_array($response["cpanelresult"]["error"]) ? implode(". ", $response["cpanelresult"]["error"]) : $response["cpanelresult"]["error"];
            throw new WHMCS\Exception("Unable to Add MX Record: " . $error);
        }
    }
}

function cpanel_GetSPFRecord($params)
{
    $apiData = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "SPFUI", "cpanel_jsonapi_func" => "get_raw_record"];
    $response = cpanel_jsonrequest($params, "json-api/cpanel", $apiData);
    if (array_key_exists("error", $response["cpanelresult"]) && $response["cpanelresult"]["error"]) {
        $error = is_array($response["cpanelresult"]["error"]) ? implode(". ", $response["cpanelresult"]["error"]) : $response["cpanelresult"]["error"];
        throw new WHMCS\Exception("Unable to Retrieve SPF Record: " . $error);
    }
    return ["spfRecord" => $response["cpanelresult"]["data"][0]["record"]];
}

function cpanel_SetSPFRecord($params)
{
    $domain = $params["spfDomain"];
    $record = $params["spfRecord"];
    $apiData = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "3", "cpanel_jsonapi_module" => "EmailAuth", "cpanel_jsonapi_func" => "install_spf_records", "domain" => $domain, "record" => $record];
    $response = cpanel_jsonrequest($params, "json-api/cpanel", $apiData);
    if ($response["result"]["status"] == 0) {
        throw new WHMCS\Exception(implode(". ", $response["result"]["messages"]));
    }
}

function cpanel_CreateFTPAccount($params)
{
    $command = "/json-api/cpanel";
    $postVars = ["user" => $params["ftpUsername"], "pass" => $params["ftpPassword"], "quota" => 0, "homedir" => "public_html", "cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "3", "cpanel_jsonapi_module" => "Ftp", "cpanel_jsonapi_func" => "add_ftp"];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if (array_key_exists("errors", $response["result"]) && $response["result"]["errors"]) {
        $error = is_array($response["result"]["errors"]) ? implode(". ", $response["result"]["errors"]) : $response["result"]["errors"];
        throw new WHMCS\Exception("Unable to Create FTP Account: " . $error);
    }
}

function cpanel_GetDns($params)
{
    $command = "/json-api/cpanel";
    $postVars = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "ZoneEdit", "cpanel_jsonapi_func" => "fetchzone_records", "domain" => $params["domain"]];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if (array_key_exists("error", $response["cpanelresult"]) && $response["cpanelresult"]["error"]) {
        $error = is_array($response["cpanelresult"]["error"]) ? implode(". ", $response["cpanelresult"]["error"]) : $response["cpanelresult"]["error"];
        throw new WHMCS\Exception("Unable to Get DNS: " . $error);
    }
    if (isset($response["cpanelresult"]["data"]) && is_array($response["cpanelresult"]["data"])) {
        return $response["cpanelresult"]["data"];
    }
    throw new WHMCS\Exception("Unexpected response for Get DNS: " . json_encode($response));
}

function cpanel_SetDnsRecord($params)
{
    $command = "/json-api/cpanel";
    $postVars = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "ZoneEdit", "cpanel_jsonapi_func" => "edit_zone_record", "domain" => $params["domain"]];
    $dnsRecord = is_array($params["dnsRecord"]) ? $params["dnsRecord"] : [];
    $postVars = array_merge($postVars, $dnsRecord);
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if (array_key_exists("error", $response["cpanelresult"]) && $response["cpanelresult"]["error"]) {
        $error = is_array($response["cpanelresult"]["error"]) ? implode(". ", $response["cpanelresult"]["error"]) : $response["cpanelresult"]["error"];
        throw new WHMCS\Exception("Unable to Modify DNS: " . $error);
    }
    if (isset($response["cpanelresult"]["data"][0]["result"]["status"]) && $response["cpanelresult"]["data"][0]["result"]["status"] == 0) {
        throw new WHMCS\Exception("Unable to Modify DNS: " . $response["cpanelresult"]["data"][0]["result"]["statusmsg"]);
    }
}

function cpanel_ModifyDns($params)
{
    $serverDnsRecords = cpanel_getdns($params);
    $recordsToCreate = [];
    $dnsRecordsToProvision = $params["dnsRecordsToProvision"];
    foreach ($dnsRecordsToProvision as $recordToProvision) {
        if (!$recordToProvision["name"] && !$recordToProvision["host"]) {
            if (0 < count($recordsToCreate)) {
                unset($params["dnsRecordsToProvision"]);
                $params["dnsRecords"] = $recordsToCreate;
                cpanel_AddDns($params);
            }
        } else {
            $recordToUpdate = NULL;
            $dnsHost = $recordToProvision["name"] ?: $recordToProvision["host"];
            foreach ($serverDnsRecords as $existingRecord) {
                if ($existingRecord["type"] == $recordToProvision["type"] && cpanel__normaliseHostname($existingRecord, $params["domain"]) == $dnsHost) {
                    $recordToUpdate = $existingRecord;
                    if (is_null($recordToUpdate)) {
                        $recordsToCreate[] = ["name" => $dnsHost, "type" => $recordToProvision["type"], "value" => $recordToProvision["value"]];
                    } else {
                        if (in_array($recordToProvision["type"], ["A"])) {
                            $recordToUpdate["address"] = $recordToProvision["value"];
                        } else {
                            if (in_array($recordToProvision["type"], ["CNAME"])) {
                                $recordToUpdate["cname"] = $recordToProvision["value"];
                            } else {
                                if (in_array($recordToProvision["type"], ["TXT", "SRV"])) {
                                    $recordToUpdate["txtdata"] = $recordToProvision["value"];
                                }
                            }
                        }
                        $params["dnsRecord"] = $recordToUpdate;
                        cpanel_setdnsrecord($params);
                        unset($params["dnsRecord"]);
                    }
                }
            }
        }
    }
}

function cpanel_create_api_token($params)
{
    $tokenName = "WHMCS" . App::getLicense()->getLicenseKey() . genRandomVal(5);
    $command = "/json-api/api_token_create";
    $postVars = ["api.version" => 1, "token_name" => $tokenName];
    try {
        $response = cpanel_jsonrequest($params, $command, $postVars);
    } catch (Throwable $e) {
        return ["success" => false, $e->getMessage()];
    }
    if ($response["metadata"]["result"] == 1) {
        return ["success" => true, "api_token" => $response["data"]["token"]];
    }
    return ["success" => false, "error" => $response["metadata"]["reason"]];
}

function cpanel_request_backup($params)
{
    $command = "/json-api/cpanel";
    switch ($params["dest"]) {
        case "passiveftp":
            $postVarsData = ["variant" => "passive", "username" => $params["user"], "password" => $params["pass"], "host" => $params["hostname"], "port" => $params["port"], "directory" => $params["rdir"], "email" => $params["email"]];
            $dest = "_to_ftp";
            break;
        case "scp":
            $postVarsData = ["username" => $params["user"], "password" => $params["pass"], "host" => $params["hostname"], "port" => $params["port"], "directory" => $params["rdir"], "email" => $params["email"]];
            $dest = "_to_scp_with_password";
            break;
        case "homedir":
            $postVarsData = ["email" => $params["email"]];
            $dest = "_to_homedir";
            break;
        default:
            $postVarsData = ["username" => $params["user"], "password" => $params["pass"], "host" => $params["hostname"], "port" => $params["port"], "directory" => $params["rdir"], "email" => $params["email"]];
            $dest = "_to_ftp";
            $postVarsConnData = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "3", "cpanel_jsonapi_module" => "Backup", "cpanel_jsonapi_func" => "fullbackup" . $dest];
            $postVars = array_merge($postVarsData, $postVarsConnData);
            $response = cpanel_jsonrequest($params, $command, $postVars);
            if (array_key_exists("errors", $response["result"]) && $response["result"]["errors"]) {
                $error = is_array($response["result"]["errors"]) ? implode(". ", $response["result"]["errors"]) : $response["result"]["errors"];
                throw new WHMCS\Exception("Unable to Request Backup: " . $error);
            }
    }
}

function cpanel_list_ssh_keys($params)
{
    $command = "/json-api/cpanel";
    $postVars = ["pub" => 0, "cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "SSH", "cpanel_jsonapi_func" => "listkeys"];
    if (array_key_exists("key_name", $params)) {
        $postVars["keys"] = $params["key_name"];
    }
    if (array_key_exists("key_encryption_type", $params) && in_array($params["key_encryption_type"], ["rsa", "dsa"])) {
        $postVars["types"] = $params["key_encryption_type"];
    }
    if (array_key_exists("public_key", $params) && $params["public_key"]) {
        $postVars["pub"] = 1;
    }
    $response = cpanel_jsonrequest($params, $command, $postVars);
    $response = $response["cpanelresult"];
    if (!$response["event"]["result"]) {
        throw new WHMCS\Exception("Unable to Request SSH Key List: " . $response["event"]["reason"]);
    }
    return $response;
}

function cpanel_generate_ssh_key($params)
{
    $command = "/json-api/cpanel";
    $bits = 2048;
    if (array_key_exists("bits", $params)) {
        $bits = $params["bits"];
    }
    $postVars = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "SSH", "cpanel_jsonapi_func" => "genkey", "name" => $params["key_name"], "bits" => $bits];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    $response = $response["cpanelresult"];
    if (!$response["event"]["result"]) {
        throw new WHMCS\Exception("Unable to Generate SSH Key: " . $response["event"]["reason"]);
    }
}

function cpanel_fetch_ssh_key($params)
{
    $command = "/json-api/cpanel";
    $postVars = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "SSH", "cpanel_jsonapi_func" => "fetchkey", "name" => $params["key_name"], "pub" => 0];
    if (array_key_exists("public_key", $params) && $params["public_key"]) {
        $postVars["pub"] = 1;
    }
    $response = cpanel_jsonrequest($params, $command, $postVars);
    $response = $response["cpanelresult"];
    if (!$response["event"]["result"]) {
        throw new WHMCS\Exception("Unable to Fetch SSH Key: " . $response["event"]["reason"]);
    }
    $keyData = $response["data"][0];
    $postVars = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "2", "cpanel_jsonapi_module" => "SSH", "cpanel_jsonapi_func" => "authkey", "key" => $keyData["name"], "action" => "authorize"];
    cpanel_jsonrequest($params, $command, $postVars);
    return $keyData;
}

function cpanel_get_ssh_port($params)
{
    $command = "/json-api/cpanel";
    $postVars = ["cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "3", "cpanel_jsonapi_module" => "SSH", "cpanel_jsonapi_func" => "get_port"];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    $response = $response["result"];
    if (!$response["status"]) {
        throw new WHMCS\Exception("Unable to Fetch SSH Port Number: " . $response["messages"]);
    }
    return $response["data"]["port"];
}

function cpanel_ListAccounts($params)
{
    $command = "/json-api/listaccts";
    $postVars = ["want" => "domain,user,plan,ip,unix_startdate,suspended,email,owner"];
    $accounts = [];
    try {
        $hasAllPerm = cpanel_hasEverythingPerm($params);
        $availablePackages = cpanel_listpackages($params);
        $response = cpanel_jsonrequest($params, $command, $postVars);
        if ($response["status"] == 1) {
            foreach ($response["acct"] as $userAccount) {
                if (!($userAccount["owner"] != $params["serverusername"] && $userAccount["owner"] != $userAccount["user"])) {
                    $status = WHMCS\Utility\Status::ACTIVE;
                    if ($userAccount["suspended"]) {
                        $status = WHMCS\Utility\Status::SUSPENDED;
                    }
                    $plan = $userAccount["plan"];
                    if ($params["serverusername"] != "root" && !stristr($plan, $params["serverusername"]) && !$hasAllPerm && in_array($plan, $availablePackages)) {
                        $plan = $params["serverusername"] . "_" . $plan;
                    }
                    $createdDate = NULL;
                    try {
                        $startDate = $userAccount["unix_startdate"];
                        if (is_numeric($startDate) && (int) $startDate !== 0) {
                            $startDateObject = WHMCS\Carbon::createFromTimestamp($startDate);
                            if ($startDateObject) {
                                $createdDate = $startDateObject->toDateTimeString();
                            }
                        }
                    } catch (Exception $e) {
                    }
                    if (!$createdDate) {
                        $createdDate = WHMCS\Carbon::today()->toDateTimeString();
                    }
                    $account = ["name" => $userAccount["user"], "email" => $userAccount["email"], "username" => $userAccount["user"], "domain" => $userAccount["domain"], "uniqueIdentifier" => $userAccount["domain"], "product" => $plan, "primaryip" => $userAccount["ip"], "created" => $createdDate, "status" => $status];
                    $accounts[] = $account;
                }
            }
            return ["success" => true, "accounts" => $accounts];
        } else {
            return ["success" => false, "accounts" => $accounts, "error" => $response["metadata"]["reason"]];
        }
    } catch (Exception $e) {
        return ["success" => false, "accounts" => $accounts, "error" => $e->getMessage()];
    }
}

function cpanel_getUserData($params)
{
    $command = "/json-api/listaccts";
    $postVars = ["searchtype" => "user", "search" => $params["username"], "want" => "domain,user,plan,ip,suspended,email,owner"];
    $accountData = [];
    try {
        $results = cpanel_jsonrequest($params, $command, $postVars);
        if ($results["status"] == 1) {
            $userData = $results["acct"][0];
            $accountData = ["name" => $userData["user"], "email" => $userData["email"], "username" => $userData["user"], "domain" => $userData["domain"], "uniqueIdentifier" => $userData["domain"], "product" => $userData["plan"]];
            return ["success" => true, "userData" => $accountData];
        }
        return ["success" => false, "userData" => $accountData, "error" => $results["metadata"]["reason"]];
    } catch (Exception $e) {
        return ["success" => false, "userData" => $accountData, "error" => $e->getMessage()];
    }
}

function cpanel_GetUserCount($params)
{
    $command = "/json-api/listaccts";
    $postVars = ["want" => "user,owner"];
    try {
        $response = cpanel_jsonrequest($params, $command, $postVars);
        if ($response["status"] == 1) {
            $totalCount = count($response["acct"]);
            $ownedAccounts = 0;
            foreach ($response["acct"] as $userAccount) {
                if ($userAccount["owner"] == $params["serverusername"] || $userAccount["owner"] == $userAccount["user"]) {
                    $ownedAccounts++;
                }
            }
            return ["success" => true, "totalAccounts" => $totalCount, "ownedAccounts" => $ownedAccounts];
        } else {
            throw new Exception(!empty($response["statusmsg"]) ? $response["statusmsg"] : "An unknown error was encountered from the server");
        }
    } catch (Exception $e) {
        return ["success" => false, "error" => $e->getMessage()];
    }
}

function cpanel_GetRemoteMetaData($params)
{
    $errors = [];
    try {
        $apiData = urlencode(http_build_query(["api.version" => 1]));
        $commands[] = "command=version?" . $apiData;
        $commands[] = "command=systemloadavg?" . $apiData;
        $commands[] = "command=get_maximum_users?" . $apiData;
        $cpanelResponse = cpanel_jsonrequest($params, "/json-api/batch", "api.version=1&" . implode("&", $commands));
        if ($cpanelResponse["metadata"]["result"] == 0) {
            foreach ($cpanelResponse["data"]["result"] as $key => $values) {
                if ($values["metadata"]["result"] == 0) {
                    $reasonMsg = "";
                    if (isset($values["metadata"]["reason"])) {
                        $reasonMsg = $values["metadata"]["reason"];
                    }
                    if (substr($reasonMsg, 0, 11) !== "Unknown app") {
                        cpanel__adderrortolist($reasonMsg, $errors);
                    }
                }
            }
        }
        $errors = cpanel__formaterrorlist($errors);
        if (0 < count($errors)) {
            return ["success" => false, "error" => implode(", ", $errors)];
        }
        $version = "-";
        $loads = ["fifteen" => "0", "five" => "0", "one" => "0"];
        $maxUsers = "0";
        foreach ($cpanelResponse["data"]["result"] as $key => $values) {
            if (array_key_exists("data", $values)) {
                switch ($values["metadata"]["command"]) {
                    case "get_maximum_users":
                        $maxUsers = $values["data"]["maximum_users"];
                        break;
                    case "systemloadavg":
                        $loads = $values["data"];
                        break;
                    case "version":
                        $version = $values["data"]["version"];
                        break;
                }
            }
        }
        return ["version" => $version, "load" => $loads, "max_accounts" => $maxUsers];
    } catch (Exception $e) {
        return ["success" => false, "error" => $e->getMessage()];
    }
}

function cpanel_RenderRemoteMetaData($params)
{
    $remoteData = $params["remoteData"];
    if ($remoteData) {
        $metaData = $remoteData->metaData;
        $version = "Unknown";
        $loadOne = $loadFive = $loadFifteen = 0;
        $maxAccounts = "Unlimited";
        if (array_key_exists("version", $metaData)) {
            $version = $metaData["version"];
        }
        if (array_key_exists("load", $metaData)) {
            $loadOne = $metaData["load"]["one"];
            $loadFive = $metaData["load"]["five"];
            $loadFifteen = $metaData["load"]["fifteen"];
        }
        if (array_key_exists("max_accounts", $metaData) && 0 < $metaData["max_accounts"]) {
            $maxAccounts = $metaData["max_accounts"];
        }
        return "cPanel Version: " . $version . "<br>\nLoad Averages: " . $loadOne . " " . $loadFive . " " . $loadFifteen . "<br>\nLicense Max # of Accounts: " . $maxAccounts;
    }
    return "";
}

function cpanel_MetricItems()
{
    $transName = function ($key) {
        if (App::isAdminAreaRequest()) {
            return AdminLang::trans($key);
        }
        return Lang::trans($key);
    };
    if (!$items) {
        $items = [new WHMCS\UsageBilling\Metrics\Metric("diskusage", $transName("usagebilling.metric.diskSpace"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\GigaBytes()), new WHMCS\UsageBilling\Metrics\Metric("bandwidthusage", $transName("usagebilling.metric.bandwidth"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_PERIOD_MONTH, new WHMCS\UsageBilling\Metrics\Units\GigaBytes()), new WHMCS\UsageBilling\Metrics\Metric("emailaccounts", $transName("usagebilling.metric.emailAccounts"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Accounts("Email Accounts")), new WHMCS\UsageBilling\Metrics\Metric("addondomains", $transName("usagebilling.metric.addonDomains"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Domains("Addon Domains")), new WHMCS\UsageBilling\Metrics\Metric("parkeddomains", $transName("usagebilling.metric.parkedDomains"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Domains("Parked Domains")), new WHMCS\UsageBilling\Metrics\Metric("subdomains", $transName("usagebilling.metric.subDomains"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Domains("Subdomains")), new WHMCS\UsageBilling\Metrics\Metric("mysqldatabases", $transName("usagebilling.metric.mysqlDatabases"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\WholeNumber("MySQL Databases", "Database", "Databases")), new WHMCS\UsageBilling\Metrics\Metric("mysqldiskusage", $transName("usagebilling.metric.mysqlDiskUsage"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\GigaBytes()), new WHMCS\UsageBilling\Metrics\Metric("subaccounts", $transName("usagebilling.metric.subAccounts"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Accounts("Sub-Accounts"))];
    }
    return $items;
}

function cpanel_MetricProvider($params)
{
    $items = cpanel_metricitems();
    $serverUsage = function (WHMCS\UsageBilling\Contracts\Metrics\ProviderInterface $provider, $tenant = NULL) use($params) {
        $usage = [];
        try {
            $accounts = cpanel_listaccounts($params);
            $resellerList = cpanel_ListResellers($params);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        $resellers = [];
        if ($resellerList["success"]) {
            $resellers = $resellerList["data"];
        }
        if (empty($accounts["accounts"])) {
            return $usage;
        }
        $tenants = [];
        $usernames = [];
        foreach ($accounts["accounts"] as $account) {
            if (!empty($account["username"])) {
                $tenants[$account["username"]] = $account["domain"];
            }
        }
        $metrics = $provider->metrics();
        foreach ($tenants as $username => $domain) {
            if (!($tenant && $tenant != $domain)) {
                $usernames[] = $username;
            }
        }
        $useGetStats = false;
        try {
            $params["usernames"] = $usernames;
            $results = cpanel_GetStatsUAPI($params);
        } catch (WHMCS\Exception $e) {
            $useGetStats = true;
        }
        if ($useGetStats) {
            $results = [];
            foreach ($usernames as $username) {
                $params["username"] = $username;
                $results[$username] = cpanel_GetStats($params);
            }
        }
        if ($tenant && count($results) === 0) {
            throw new WHMCS\Exception\Module\NotServicable("Unable to refresh metrics. Please ensure you are the account owner.");
        }
        foreach ($results as $username => $data) {
            $domain = $tenants[$username];
            $isReseller = in_array($username, $resellers);
            $resellerData = NULL;
            if (!empty($data) && $isReseller) {
                $params["username"] = $username;
                $resellerData = cpanel_ResellerStats($params);
                $subAccounts = 0;
                if (isset($resellerData["accounts"])) {
                    $subAccounts = (int) $resellerData["accounts"];
                }
                $data[] = ["id" => "subaccounts", "_count" => $subAccounts];
            }
            foreach ($data as $stat) {
                $name = $stat["id"];
                if (isset($metrics[$name])) {
                    $metric = $metrics[$name];
                    $remoteValue = $stat["_count"];
                    if (!is_null($resellerData) && $isReseller) {
                        if ($name === "bandwidthusage") {
                            $remoteValue = $resellerData["bwusage"];
                        }
                        if ($name === "diskusage") {
                            $remoteValue = $resellerData["diskusage"];
                        }
                    }
                    if (isset($stat["units"]) && in_array($stat["units"], ["MB", "GB", "KB", "B"])) {
                        $units = $metric->units();
                        $to = $units->suffix();
                        if ($name == "mysqldiskusage") {
                            $from = "B";
                        } else {
                            $from = $stat["units"];
                        }
                        $remoteValue = $units::convert($remoteValue, $from, $to);
                    }
                    $usage[$domain][$name] = $metric->withUsage(new WHMCS\UsageBilling\Metrics\Usage($remoteValue));
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

function cpanel_GetStatsUAPI($params)
{
    $usernames = $params["usernames"];
    $apiData = ["api.version" => "1", "cpanel.module" => "StatsBar", "cpanel.function" => "get_stats", "cpanel.user" => strtolower($usernames[0] ?? "")];
    $response = cpanel_jsonrequest($params, "json-api/uapi_cpanel", $apiData);
    if ($response["metadata"]["result"] == 0) {
        throw new WHMCS\Exception($response["metadata"]["reason"]);
    }
    $commands = [];
    foreach ($usernames as $username) {
        $apiData = ["cpanel.module" => "StatsBar", "cpanel.function" => "get_stats", "cpanel.user" => strtolower($username), "display" => "addondomains|bandwidthusage|diskusage|emailaccounts|mysqldatabases|mysqldiskusage|parkeddomains|postgresqldatabases|postgresdiskusage|subdomains"];
        $commands[] = "command=uapi_cpanel?" . urlencode(http_build_query($apiData));
    }
    $response = cpanel_jsonrequest($params, "/json-api/batch", "api.version=1&" . implode("&", $commands));
    $data = [];
    foreach ($usernames as $key => $username) {
        $data[$username] = $response["data"]["result"][$key]["data"]["uapi"]["data"];
    }
    return $data;
}

function cpanel_GetStats($params)
{
    $command = "/json-api/cpanel";
    $postVars = ["display" => "addondomains|bandwidthusage|diskusage|emailaccounts|mysqldatabases|mysqldiskusage|parkeddomains|postgresqldatabases|postgresdiskusage|subdomains", "cpanel_jsonapi_user" => strtolower($params["username"]), "cpanel_jsonapi_apiversion" => "3", "cpanel_jsonapi_module" => "StatsBar", "cpanel_jsonapi_func" => "get_stats"];
    $response = cpanel_jsonrequest($params, $command, $postVars);
    if (!empty($response["result"]["errors"])) {
        $error = is_array($response["result"]["errors"]) ? implode(". ", $response["result"]["errors"]) : $response["result"]["errors"];
        throw new WHMCS\Exception("Unable to get stats: " . $error);
    }
    $data = !empty($response["result"]["data"]) && is_array($response["result"]["data"]) ? $response["result"]["data"] : [];
    return $data;
}

function cpanel_ListResellers($params)
{
    $command = "/json-api/listresellers";
    $postVars = ["user" => $params["username"] ?? NULL, "api.version" => 1];
    try {
        $response = cpanel_jsonrequest($params, $command, $postVars);
        if (!is_array($response)) {
            if (!empty($response)) {
                return ["success" => false, "error" => $response, "data" => []];
            }
            return ["success" => false, "error" => "An unknown error occurred", "data" => []];
        }
        $metadata = isset($response["metadata"]) ? $response["metadata"] : [];
        $resultCode = isset($metadata["result"]) ? $metadata["result"] : 0;
        if ($resultCode == 0 || !isset($response["data"]["reseller"])) {
            if (isset($metadata["reason"])) {
                return ["success" => false, "error" => $metadata["reason"], "data" => []];
            }
            return ["success" => false, "error" => "An unknown error occurred", "data" => []];
        }
        return ["success" => true, "error" => "", "data" => $response["data"]["reseller"]];
    } catch (Exception $e) {
        return ["success" => false, "error" => $e->getMessage(), "data" => []];
    }
}

function cpanel_ResellerStats($params)
{
    $command = "/json-api/resellerstats";
    if (isset($params["reseller"])) {
        $reseller = $params["reseller"];
    } else {
        $reseller = $params["username"];
    }
    $postVars = ["api.version" => "1", "user" => $reseller];
    $stats = [];
    $output = cpanel_jsonrequest($params, $command, $postVars);
    if (is_array($output) && isset($output["data"]["reseller"]) && is_array($output["data"]["reseller"])) {
        $data = $output["data"]["reseller"];
        $diskUsed = $data["diskused"];
        $diskLimit = $data["diskquota"];
        if (!$diskLimit) {
            $diskLimit = $data["totaldiskalloc"];
        }
        if (!$diskLimit) {
            $diskLimit = "Unlimited";
        }
        $bwUsed = $data["totalbwused"];
        $bwLimit = $data["bandwidthlimit"];
        if (!$bwLimit) {
            $bwLimit = $data["totalbwalloc"];
        }
        if (!$bwLimit) {
            $bwLimit = "Unlimited";
        }
        $accounts = 0;
        $isOwner = false;
        if (!empty($data["acct"])) {
            foreach ($data["acct"] as $acct) {
                if ($acct["user"] === $reseller) {
                    $isOwner = true;
                } else {
                    if (!$acct["deleted"]) {
                        $accounts++;
                    }
                }
            }
        }
        $stats = ["diskusage" => $diskUsed, "disklimit" => $diskLimit, "bwusage" => $bwUsed, "bwlimit" => $bwLimit, "accounts" => $accounts, "isOwner" => $isOwner, "lastupdate" => WHMCS\Carbon::now()->toDateTimeString()];
    }
    return $stats;
}

function cpanel_hasEverythingPerm($params)
{
    $command = "/json-api/myprivs";
    $postVars = ["api.version" => "1"];
    $output = cpanel_jsonrequest($params, $command, $postVars);
    if (is_array($output)) {
        $hasAllPerm = $output["data"]["privileges"][0]["all"];
        if ($hasAllPerm === 1) {
            return true;
        }
    }
    return false;
}

function cpanel_ListAddOnFeatures($params)
{
    $command = "/json-api/get_feature_names";
    $postVars = ["api.version" => 1];
    $output = cpanel_jsonrequest($params, $command, $postVars);
    $result = [];
    if (is_array($output)) {
        $supportedFeatures = ["wp-toolkit-deluxe"];
        foreach ($output["data"]["feature"] ?? [] as $feature) {
            if (count($result) === count($supportedFeatures)) {
                asort($result);
            } else {
                if (in_array($feature["id"], $supportedFeatures)) {
                    $result[$feature["id"]] = $feature["name"];
                }
            }
        }
    }
    return $result;
}

function cpanel_AddFeatureOverrides($params)
{
    $command = "/json-api/add_override_features_for_user";
    if (isset($params["reseller"])) {
        $reseller = $params["reseller"];
    } else {
        $reseller = $params["service"]["username"];
    }
    $featureOverrides = [];
    foreach ($params["features"] ?? [] as $featureId) {
        $featureOverrides[$featureId] = 1;
    }
    if (!$featureOverrides) {
        return ["success" => false, "error" => "No features to override", "data" => []];
    }
    $postVars = ["user" => $reseller, "api.version" => 1, "features" => json_encode($featureOverrides)];
    $output = cpanel_jsonrequest($params, $command, $postVars);
    $result = [];
    if (is_array($output)) {
        return $output;
    }
    return $result;
}

function cpanel_RemoveFeatureOverrides($params)
{
    $command = "/json-api/remove_override_features_for_user";
    if (isset($params["reseller"])) {
        $reseller = $params["reseller"];
    } else {
        $reseller = $params["service"]["username"];
    }
    $featureOverrides = $params["features"] ?? [];
    if (!$featureOverrides) {
        return ["success" => false, "error" => "No features to remove overrides for", "data" => []];
    }
    $postVars = ["user" => $reseller, "api.version" => 1, "features" => json_encode($featureOverrides)];
    $output = cpanel_jsonrequest($params, $command, $postVars);
    $result = [];
    if (is_array($output)) {
        return $output;
    }
    return $result;
}

function cpanel_ProvisionAddOnFeature($params)
{
    $params["features"] = [$params["configoption1"]];
    if ($params["configoption1"] === "wp-toolkit-deluxe") {
        $params["features"][] = "wp-toolkit";
    }
    $result = cpanel_addfeatureoverrides($params);
    if (is_array($result) && isset($result["metadata"]["result"]) && $result["metadata"]["result"] === 0) {
        return $result["metadata"]["reason"];
    }
    return "success";
}

function cpanel_DeprovisionAddOnFeature($params)
{
    $params["features"] = [$params["configoption1"]];
    $result = cpanel_removefeatureoverrides($params);
    if (is_array($result) && isset($result["metadata"]["result"]) && $result["metadata"]["result"] === 0) {
        return $result["metadata"]["reason"];
    }
    return "success";
}

function cpanel_SuspendAddOnFeature($params)
{
    return cpanel_deprovisionaddonfeature($params);
}

function cpanel_UnsuspendAddOnFeature($params)
{
    return cpanel_provisionaddonfeature($params);
}

function cpanel_AddOnFeatureSingleSignOn($params)
{
    $app = $params["configoption1"];
    if ($app === "wp-toolkit-deluxe") {
        $app = "wp-toolkit";
    }
    if (isset($params["reseller"])) {
        $user = $params["reseller"];
    } else {
        $user = $params["service"]["username"];
    }
    $response = cpanel_singlesignon($params, $user, "cpaneld", $app);
    if (!empty($response["success"]) && $app === "wp-toolkit") {
        $redirectTo = $response["redirectTo"];
        $redirectTo = explode("?", $redirectTo);
        $redirectTo = $redirectTo[0] . "?goto_uri=frontend/paper_lantern/wp-toolkit/index.live.php&" . $redirectTo[1];
        $response["redirectTo"] = $redirectTo;
    }
    return $response;
}

function cpanel_getProductTypesForAddOn($params)
{
    switch ($params["Feature Name"]) {
        case "wp-toolkit-deluxe":
            return ["hostingaccount"];
            break;
        default:
            return ["hostingsaccount", "reselleraccount", "server", "other"];
    }
}

function cpanel_InstallWordPress($params)
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
    $manager = new WHMCS\Module\Server\Cpanel\Cpanel\WordPress\WordPressManager();
    try {
        $response = $manager->callWpToolkitCli("install", $params, $cliParams);
        $serviceOrAddon = $params["model"];
        $serviceWpInstances = json_decode(WHMCS\Input\Sanitize::decode($serviceOrAddon->serviceProperties->get("WordPress Instances")), true) ?: [];
        $serviceWpInstances[] = ["blogTitle" => $response["site-title"], "instanceUrl" => $response["protocol"] . "://" . $response["domain"] . "/" . $response["path"]];
        $serviceOrAddon->serviceProperties->save(["WordPress Instances" => WHMCS\Input\Sanitize::encode(json_encode($serviceWpInstances))]);
    } catch (Throwable $e) {
        $error = $e instanceof WHMCS\Exception\Module\NotServicable ? $e->getMessage() : "An error occurred, please try again later.";
        return ["error" => $error, "jsonResponse" => ["error" => $error]];
    }
    $response["jsonResponse"] = ["success" => "WordPress has been successfully installed"];
    return $response;
}

function cpanel_ResetWordPressAdminPassword($params)
{
    $cliParams = ["instance-id" => $params["instance_id"]];
    if (isset($params["admin_user"]) && $params["admin_user"] !== "") {
        $cliParams["admin-login"] = $params["admin_user"];
    }
    $manager = new WHMCS\Module\Server\Cpanel\Cpanel\WordPress\WordPressManager();
    return $manager->callWpToolkitCli("site-admin-reset-password", $params, $cliParams);
}

function cpanel_GetWordPressInstanceInfo($params)
{
    $cliParams = ["instance-id" => $params["instance_id"]];
    $manager = new WHMCS\Module\Server\Cpanel\Cpanel\WordPress\WordPressManager();
    return $manager->callWpToolkitCli("info", $params, $cliParams);
}

function cpanel_AdminServicesTabFields($params)
{
    $serviceActionFields = [WHMCS\Table::EMPTY_ROW];
    if (!isset($params["model"]) || !$params["model"] instanceof WHMCS\Service\Service) {
        return $serviceActionFields;
    }
    $service = $params["model"];
    $productModuleActionSettings = json_decode($service->product->getModuleConfigurationSetting("moduleActions")->value, true);
    $moduleActions = cpanel_eventactions();
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
                $html .= "<script>\n(function(\$) {\n    \$(document).ready(function() {\n        \$('#btnPerformInstallWordPress').click(function() {\n            var self = this;\n            var extraVars = '&blog_title=' + escape(\$('#inputblog_title').val())\n                + '&blog_path=' + escape(\$('#inputblog_path').val())\n                + '&admin_pass=' + escape(\$('#inputadmin_pass').val());\n\n            \$(self).attr('disabled', 'disabled');\n\n            runModuleCommand('custom', 'InstallWordPress', extraVars);\n        });\n    });\n})(jQuery);\n</script>";
            }
        }
        if ($html) {
            $serviceActionFields[AdminLang::trans($actionData["FriendlyName"])] = $html;
        }
        $serviceActionFields[] = WHMCS\Table::EMPTY_ROW;
    }
    return $serviceActionFields;
}

function cpanel_AddDns($params)
{
    $requiredData = function ($dnsRecord) {
        $requiredData = [];
        switch ($dnsRecord["type"]) {
            case "A":
            case "AAAA":
                $requiredData["address"] = $dnsRecord["value"];
                break;
            case "CNAME":
                $requiredData["cname"] = $dnsRecord["value"];
                break;
            case "TXT":
            case "SRV":
                $requiredData["txtdata"] = $dnsRecord["value"];
                break;
            case "MX":
                $requiredData["exchange"] = $dnsRecord["value"];
                $requiredData["preference"] = $dnsRecord["opt"];
                break;
            default:
                unset($dnsRecord["value"]);
                return $requiredData;
        }
    };
    $dnsRecords = [];
    foreach ($params["dnsRecords"] as $dnsRecord) {
        if ($dnsRecord["type"] || $dnsRecord["value"]) {
            $dnsRecords[] = array_merge(["domain" => $params["domain"], "type" => $dnsRecord["type"], "name" => cpanel__normaliseHostname($dnsRecord, $params["domain"])], $requiredData($dnsRecord));
        }
    }
    unset($params["dnsRecords"]);
    foreach ($dnsRecords as $dnsRecord) {
        $command = "/json-api/addzonerecord";
        $response = cpanel_jsonrequest($params, $command, $dnsRecord);
        if (isset($response["result"][0]["status"]) && $response["result"][0]["status"] == 0) {
            throw new WHMCS\Exception("Unable to Add DNS Records: " . $response["result"][0]["statusmsg"]);
        }
    }
}

function cpanel__normaliseHostname($dnsRecord, $domain)
{
    if (!$dnsRecord["name"] && !$dnsRecord["host"]) {
        return false;
    }
    $dnsHost = $dnsRecord["name"] ?: $dnsRecord["host"];
    $dnsHost = trim($dnsHost, ".");
    $length = -1 * strlen($domain);
    if (substr($dnsHost, $length) == $domain) {
        $dnsHost = substr($dnsHost, 0, $length);
    }
    return trim($dnsHost, ".");
}

function cpanel_CustomActions($params)
{
    $serviceIsActive = $params["model"]->status === WHMCS\Service\Service::STATUS_ACTIVE;
    $customActionCollection = new WHMCS\Module\Server\CustomActionCollection();
    if ($params["model"]->product->type === WHMCS\Product\Product::TYPE_RESELLER) {
        $customActionCollection->add(WHMCS\Module\Server\CustomAction::factory("whm", "cpanelwhmlogin", "cpanel_SingleSignOn", [$params, $params["username"], "whostmgrd"], ["productsso"], $serviceIsActive));
    }
    $customActionCollection->add(WHMCS\Module\Server\CustomAction::factory("cpanel", "cpanellogin", "cpanel_SingleSignOn", [$params, $params["username"], "cpaneld"], ["productsso"], $serviceIsActive));
    $customActionCollection->add(WHMCS\Module\Server\CustomAction::factory("webmail", "cpanelwebmaillogin", function ($params) {
        return ["success" => true, "redirectTo" => $params["serverhttpprefix"] . "://" . ($params["serverhostname"] ?: $params["serverip"]) . ":" . ($params["serversecure"] ? "2096" : "2095")];
    }, [$params], [], $serviceIsActive));
    return $customActionCollection;
}
