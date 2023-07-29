<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$licensing = DI::make("license");
if (defined("DACONFPACKAGEADDONLICENSE")) {
    exit("License Hacking Attempt Detected");
}
define("DACONFPACKAGEADDONLICENSE", $licensing->isActiveAddon("Configurable Package Addon"));
function directadmin_MetaData()
{
    return ["DisplayName" => "DirectAdmin", "APIVersion" => "1.1", "DefaultNonSSLPort" => "2222", "DefaultSSLPort" => "2222", "ListAccountsUniqueIdentifierDisplayName" => "Domain", "ListAccountsUniqueIdentifierField" => "domain", "ListAccountsProductField" => "configoption1"];
}

function directadmin_ConfigOptions($params)
{
    $resellerSimpleMode = $params["producttype"] == "reselleraccount";
    return ["Package Name" => ["Type" => "text", "Size" => "25", "Loader" => function ($params) use($resellerSimpleMode) {
        $return = [];
        if ($resellerSimpleMode) {
            $command = "CMD_API_PACKAGES_RESELLER";
        } else {
            $command = "CMD_API_PACKAGES_USER";
        }
        $result = directadmin_req($command, [], $params);
        if ($result["error"] && $result["details"]) {
            throw new WHMCS\Exception\Module\NotServicable($result["details"]);
        }
        if (isset($result["list"])) {
            foreach ($result["list"] as $package) {
                $return[$package] = ucwords(str_replace("_", " ", $package));
            }
        }
        return $return;
    }, "SimpleMode" => true], "Reseller IP" => ["Type" => "dropdown", "Options" => ",shared,sharedreseller,assign", "SimpleMode" => $resellerSimpleMode], "Dedicated IP" => ["Type" => "yesno", "Description" => "Check to Auto-Assign Dedicated IP"], "Suspend at Limit" => ["Type" => "yesno", "Description" => "Check to Auto Suspend Users when reaching Bandwidth Limit"]];
}

function directadmin_ClientArea($params)
{
    global $_LANG;
    $host = $params["serverhostname"] ? $params["serverhostname"] : $params["serverip"];
    $form = sprintf("<form action=\"%s://%s:%s/CMD_LOGIN\" method=\"post\" target=\"_blank\"><input type=\"hidden\" name=\"username\" value=\"%s\" /><input type=\"hidden\" name=\"password\" value=\"%s\" /><input type=\"submit\" value=\"%s\" class=\"button\" /></form>", $params["serverhttpprefix"], WHMCS\Input\Sanitize::encode($host), WHMCS\Input\Sanitize::encode($params["serverport"]), WHMCS\Input\Sanitize::encode($params["username"]), WHMCS\Input\Sanitize::encode($params["password"]), $_LANG["directadminlogin"]);
    return $form;
}

function directadmin_AdminLink($params)
{
    $host = $params["serverhostname"] ? $params["serverhostname"] : $params["serverip"];
    $form = sprintf("<form action=\"%s://%s:%s/CMD_LOGIN\" method=\"post\" target=\"_blank\"><input type=\"hidden\" name=\"username\" value=\"%s\" /><input type=\"hidden\" name=\"password\" value=\"%s\" /><input type=\"submit\" value=\"%s\" /></form>", $params["serverhttpprefix"], WHMCS\Input\Sanitize::encode($host), WHMCS\Input\Sanitize::encode($params["serverport"]), WHMCS\Input\Sanitize::encode($params["serverusername"]), WHMCS\Input\Sanitize::encode($params["serverpassword"]), "DirectAdmin");
    return $form;
}

function directadmin_CreateAccount($params)
{
    $fields = [];
    $ip = $params["serverip"];
    if ($params["configoption3"] || DACONFPACKAGEADDONLICENSE && $params["configoption1"] === "Custom" && $params["configoptions"]["Dedicated IP"]) {
        $command = "CMD_API_SHOW_RESELLER_IPS";
        $params["getip"] = true;
        $fields["action"] = "all";
        $results = directadmin_req($command, $fields, $params);
        foreach ($results as $ipaddress => $details) {
            if ($details["status"] === "free") {
                $ip = $ipaddress;
                $params["model"]->serviceProperties->save(["dedicatedip" => $ip]);
            }
        }
    }
    $params["getip"] = "";
    if (DACONFPACKAGEADDONLICENSE && $params["configoption1"] === "Custom") {
        $command = "CMD_API_ACCOUNT_USER";
        $fields["action"] = "create";
        $fields["add"] = "Submit";
        $fields["username"] = $params["username"];
        $fields["email"] = $params["clientsdetails"]["email"];
        $fields["passwd"] = $params["password"];
        $fields["passwd2"] = $params["password"];
        $fields["domain"] = $params["domain"];
        $fields["ip"] = $ip;
        $fields["notify"] = "no";
        $customConfigOptions = directadmin_CustomConfigOptions($params);
        $fields = array_merge($fields, $customConfigOptions);
        $results = directadmin_req($command, $fields, $params);
        if ($results["error"]) {
            $result = $results["details"];
        } else {
            $result = "success";
        }
        return $result;
    }
    if ($params["type"] === "hostingaccount") {
        $fields["action"] = "create";
        $fields["add"] = "Submit";
        $fields["username"] = $params["username"];
        $fields["email"] = $params["clientsdetails"]["email"];
        $fields["passwd"] = $params["password"];
        $fields["passwd2"] = $params["password"];
        $fields["domain"] = $params["domain"];
        $fields["package"] = $params["configoption1"];
        $fields["ip"] = $ip;
        $fields["notify"] = "no";
        $command = "CMD_API_ACCOUNT_USER";
    } else {
        $fields["action"] = "create";
        $fields["add"] = "Submit";
        $fields["username"] = $params["username"];
        $fields["email"] = $params["clientsdetails"]["email"];
        $fields["passwd"] = $params["password"];
        $fields["passwd2"] = $params["password"];
        $fields["domain"] = $params["domain"];
        $fields["package"] = $params["configoption1"];
        if ($params["configoption2"] === "sharedreseller") {
            $fields["ip"] = "sharedreseller";
        } else {
            if ($params["configoption2"] === "assign") {
                $fields["ip"] = "assign";
            } else {
                $fields["ip"] = "shared";
            }
        }
        $fields["notify"] = "no";
        $command = "CMD_API_ACCOUNT_RESELLER";
    }
    $results = directadmin_req($command, $fields, $params);
    if ($results["error"]) {
        $result = $results["details"];
    } else {
        $result = "success";
    }
    return $result;
}

function directadmin_TerminateAccount($params)
{
    $fields = [];
    $fields["confirmed"] = "Confirm";
    $fields["delete"] = "yes";
    $fields["select0"] = $params["username"];
    $results = directadmin_req("CMD_SELECT_USERS", $fields, $params);
    if ($results["error"]) {
        $result = $results["details"];
    } else {
        $result = "success";
    }
    return $result;
}

function directadmin_SuspendAccount($params)
{
    $fields = [];
    $fields["action"] = "create";
    $fields["add"] = "Submit";
    $fields["user"] = $params["username"];
    $results = directadmin_req("CMD_API_SHOW_USER_CONFIG", $fields, $params);
    if ($results["suspended"] === "yes") {
        $result = "Account is already suspended";
    } else {
        $fields = [];
        $fields["suspend"] = "Suspend/Unsuspend";
        $fields["select0"] = $params["username"];
        $results = directadmin_req("CMD_SELECT_USERS", $fields, $params);
        if ($results["error"]) {
            $result = $results["details"];
        } else {
            $result = "success";
        }
    }
    return $result;
}

function directadmin_UnsuspendAccount($params)
{
    $fields = [];
    $fields["action"] = "create";
    $fields["add"] = "Submit";
    $fields["user"] = $params["username"];
    $results = directadmin_req("CMD_API_SHOW_USER_CONFIG", $fields, $params);
    if ($results["suspended"] === "no") {
        $result = "Account is not suspended";
    } else {
        $fields = [];
        $fields["suspend"] = "Suspend/Unsuspend";
        $fields["select0"] = $params["username"];
        $results = directadmin_req("CMD_SELECT_USERS", $fields, $params);
        if ($results["error"]) {
            $result = $results["details"];
        } else {
            $result = "success";
        }
    }
    return $result;
}

function directadmin_ChangePassword($params)
{
    $fields = [];
    $fields["username"] = $params["username"];
    $fields["passwd"] = $params["password"];
    $fields["passwd2"] = $params["password"];
    $results = directadmin_req("CMD_API_USER_PASSWD", $fields, $params, true);
    if ($results["error"]) {
        $result = $results["details"];
    } else {
        $result = "success";
    }
    return $result;
}

function directadmin_ChangePackage($params)
{
    $fields = [];
    $fields["action"] = "package";
    $fields["user"] = $params["username"];
    $fields["package"] = $params["configoption1"];
    if (DACONFPACKAGEADDONLICENSE && $params["action"] === "upgrade" && $fields["package"] === "Custom") {
        unset($fields["package"]);
        $fields["action"] = "customize";
        $customConfigOptions = directadmin_CustomConfigOptions($params);
        $fields = array_merge($fields, $customConfigOptions);
    }
    if ($params["type"] === "reselleraccount") {
        $results = directadmin_req("CMD_API_MODIFY_RESELLER", $fields, $params);
    } else {
        $results = directadmin_req("CMD_API_MODIFY_USER", $fields, $params);
    }
    if ($results["error"]) {
        $result = $results["details"];
    } else {
        $result = "success";
    }
    return $result;
}

function directadmin_UsageUpdate($params)
{
    $serverUsername = $params["serverusername"];
    $services = WHMCS\Service\Service::with("product")->where("server", "=", $params["serverid"])->whereIn("domainstatus", ["Active", "Suspended"])->get();
    $addons = WHMCS\Service\Addon::with("productAddon")->where("server", "=", $params["serverid"])->whereIn("status", ["Active", "Suspended"])->get();
    foreach ($services as $service) {
        $username = $service->username;
        $fields = ["user" => $username];
        $command1 = "CMD_API_SHOW_USER_USAGE";
        $command2 = "CMD_API_SHOW_USER_CONFIG";
        $fields1 = $fields;
        $fields2 = $fields;
        $params["serverusername"] = $serverUsername;
        if ($service->product->type === "reselleraccount") {
            $params["serverusername"] = $serverUsername . "|" . $username;
            $command1 = "CMD_API_RESELLER_STATS";
            $fields1["type"] = "usage";
            $command2 = "CMD_API_RESELLER_STATS";
        }
        $results = directadmin_req($command1, $fields1, $params);
        if (!$results["error"]) {
            $quota = urldecode($results["quota"]);
            $bandwidth = urldecode($results["bandwidth"]);
            $diskUsed = is_numeric($quota) ? round($quota) : $quota;
            $bwUsed = is_numeric($bandwidth) ? round($bandwidth) : $bandwidth;
            $results = directadmin_req($command2, $fields2, $params);
            if (!$results["error"]) {
                $quota = urldecode($results["quota"]);
                $bandwidth = urldecode($results["bandwidth"]);
                $quota = is_numeric($quota) ? round($quota) : $quota;
                $bandwidth = is_numeric($bandwidth) ? round($bandwidth) : $bandwidth;
                $diskLimit = $quota == "unlimited" ? "0" : $quota;
                $bwLimit = $bandwidth == "unlimited" ? "0" : $bandwidth;
                $service->diskUsage = $diskUsed;
                $service->diskLimit = $diskLimit;
                $service->bandwidthUsage = $bwUsed;
                $service->bandwidthLimit = $bwLimit;
                $service->lastUpdateDate = WHMCS\Carbon::now()->toDateTimeString();
                $service->save();
            }
        }
    }
    foreach ($addons as $addon) {
        $username = $addon->serviceProperties->get("username");
        $fields = ["user" => $username];
        $command1 = "CMD_API_SHOW_USER_USAGE";
        $command2 = "CMD_API_SHOW_USER_CONFIG";
        $fields1 = $fields;
        $fields2 = $fields;
        $params["serverusername"] = $serverUsername;
        if ($addon->productAddon->type == "reselleraccount") {
            $params["serverusername"] = $serverUsername . "|" . $username;
            $command1 = "CMD_API_RESELLER_STATS";
            $fields1["type"] = "usage";
            $command2 = "CMD_API_RESELLER_STATS";
        }
        $results = directadmin_req($command1, $fields1, $params);
        if (!$results["error"]) {
            $quota = urldecode($results["quota"]);
            $bandwidth = urldecode($results["bandwidth"]);
            $diskUsed = round($quota);
            $bwUsed = round($bandwidth);
            $results = directadmin_req($command2, $fields2, $params);
            if (!$results["error"]) {
                $quota = urldecode($results["quota"]);
                $bandwidth = urldecode($results["bandwidth"]);
                $diskLimit = $quota == "unlimited" ? "0" : round($quota);
                $bwLimit = $bandwidth == "unlimited" ? "0" : round($bandwidth);
                $addon->serviceProperties->save(["diskusage" => $diskUsed, "disklimit" => $diskLimit, "bwusage" => $bwUsed, "bwlimit" => $bwLimit, "lastupdate" => WHMCS\Carbon::now()->toDateTimeString()]);
            }
        }
    }
}

function directadmin_req($command, $fields, $params, $post = false)
{
    $host = $params["serverhostname"] ? $params["serverhostname"] : $params["serverip"];
    $user = $params["serverusername"];
    $pass = $params["serverpassword"];
    $httpprefix = $params["serverhttpprefix"];
    $port = $params["serverport"];
    $resultsarray = [];
    if (!$user || !$pass) {
        $resultsarray = ["error" => "1", "details" => "Login Details Missing"];
        return $resultsarray;
    }
    $fieldstring = "";
    $responseHeaders = [];
    foreach ($fields as $key => $value) {
        $fieldstring .= $key . "=" . urlencode($value) . "&";
    }
    $url = $httpprefix . "://" . $host . ":" . $port . "/" . $command;
    if (!$post) {
        $url .= "?" . $fieldstring;
    }
    $authstr = $user . ":" . $pass;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($post) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldstring);
    }
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $curlheaders[0] = "Authorization: Basic " . base64_encode($authstr);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlheaders);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use($responseHeaders) {
        $headerLength = strlen($header);
        if (strpos($header, ":") === false) {
            return $headerLength;
        }
        list($headerName, $headerValue) = explode(":", $header, 2);
        $responseHeaders[strtolower(trim($headerName))][] = trim($headerValue);
        return $headerLength;
    });
    $responseBody = curl_exec($ch);
    $httpStatusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlResponseError = curl_errno($ch);
    curl_close($ch);
    $checkedResponse = directadmin__checkResponse($httpStatusCode, $responseHeaders, $responseBody, $curlResponseError);
    if (!empty($checkedResponse["error"])) {
        $resultsArray = ["error" => "1", "details" => $checkedResponse["message"]];
    } else {
        $responseArray = $resultsArray = [];
        $cleanedResponse = directadmin_unhtmlentities($responseBody);
        if (!empty($params["getip"])) {
            parse_str($cleanedResponse, $responseArray);
            foreach ($responseArray as $primaryKey => $primaryValue) {
                $primaryKey = str_replace("_", ".", urldecode($primaryKey));
                $primaryValues = explode("&", urldecode($primaryValue));
                foreach ($primaryValues as $temp) {
                    list($key, $value) = explode("=", $temp, 2);
                    $resultsArray[urldecode($primaryKey)][$key] = $value;
                }
            }
        } else {
            if (in_array($command, ["CMD_API_PACKAGES_RESELLER", "CMD_API_PACKAGES_USER", "CMD_API_ADDITIONAL_DOMAINS", "CMD_API_SHOW_ALL_USERS", "CMD_API_SHOW_USERS", "CMD_API_SHOW_RESELLERS"])) {
                parse_str($cleanedResponse, $resultsArray);
            } else {
                if ($fields["json"] === "yes") {
                    $resultsArray = json_decode($cleanedResponse, true);
                } else {
                    $responseArray = explode("&", $responseBody);
                    foreach ($responseArray as $responseItem) {
                        list($key, $value) = explode("=", $responseItem, 2);
                        $key = urldecode($key);
                        $value = urldecode($value);
                        $resultsArray[$key] = $value;
                    }
                }
            }
        }
    }
    logModuleCall("directadmin", $command, $url, $responseBody, $resultsArray);
    return $resultsArray;
}

function directadmin_unhtmlentities($string)
{
    return preg_replace_callback("~&#([0-9][0-9])~", function ($match) {
        return chr($match[1]);
    }, $string);
}

function directadmin_TestConnection($params)
{
    $response = directadmin_req("CMD_API_SHOW_USERS", [], $params);
    if (array_key_exists("error", $response) && $response["error"] == "1") {
        return ["error" => $response["details"]];
    }
    return ["success" => true];
}

function directadmin_GenerateCertificateSigningRequest($params)
{
    $params["serverusername"] = $params["serverusername"] . "|" . $params["username"];
    $command = "CMD_API_SSL";
    $certificate = $params["certificateInfo"];
    $fields = ["action" => "save", "type" => "create", "request" => "yes", "domain" => $params["domain"], "name" => $certificate["domain"], "country" => $certificate["country"], "province" => $certificate["state"], "city" => $certificate["city"], "company" => $certificate["orgname"] ?: "NA", "division" => $certificate["orgunit"], "email" => $certificate["email"], "encryption" => "sha256", "keysize" => "2048"];
    $response = directadmin_req($command, $fields, $params, true);
    if (array_key_exists("error", $response) && $response["error"] === "1") {
        throw new Exception("DirectAdmin: CSR Generation Failed: " . $response["details"]);
    }
    $response = html_entity_decode(urldecode($response["request"]));
    $extractCsr = function ($blob) {
        $pattern = "/^-----BEGIN CERTIFICATE REQUEST-----(.*?)^-----END CERTIFICATE REQUEST-----/sm";
        $matches = NULL;
        if (preg_match($pattern, $blob, $matches) !== 1) {
            return "";
        }
        return $matches[0];
    };
    return $extractCsr($response);
}

function directadmin_InstallSsl($params)
{
    $serverUsername = $params["serverusername"];
    $params["serverusername"] = $serverUsername . "|" . $params["username"];
    $command = "CMD_API_SSL";
    $fields = ["domain" => $params["domain"]];
    $response = directadmin_req($command, $fields, $params);
    if (array_key_exists("error", $response) && $response["error"] === "1") {
        throw new Exception("DirectAdmin: Private Key Retrieval Failed: " . $response["details"]);
    }
    $key = html_entity_decode(urldecode($response["key"]));
    $fields = ["domain" => $params["certificateDomain"], "action" => "save", "type" => "paste", "certificate" => $params["certificate"] . "\n" . $key . "\n"];
    $response = directadmin_req($command, $fields, $params, true);
    if (array_key_exists("error", $response) && $response["error"] === "1") {
        throw new Exception("DirectAdmin: Certificate Installation Failed: " . $response["details"]);
    }
    $fields = ["domain" => $params["certificateDomain"], "action" => "view"];
    $response = directadmin_req("CMD_API_ADDITIONAL_DOMAINS", $fields, $params, true);
    if (array_key_exists("error", $response) && $response["error"] === "1") {
        throw new Exception("DirectAdmin: Account Information Retrieval Failed: " . $response["details"]);
    }
    $accountState = $response;
    $fields = ["action" => "modify", "domain" => $params["certificateDomain"], "ssl" => "ON", "php" => $accountState["php"], "cgi" => $accountState["cgi"]];
    if ($accountState["quota"] === "unlimited") {
        $fields["uquota"] = "ON";
    } else {
        $fields["quota"] = $accountState["quota"];
    }
    if ($accountState["bandwidth"] === "unlimited") {
        $fields["ubandwidth"] = "ON";
    } else {
        $fields["bandwidth"] = $accountState["bandwidth"];
    }
    directadmin_req("CMD_API_DOMAIN", $fields, $params, true);
    $fields = ["action" => "private_html", "domain" => $params["certificateDomain"], "val" => "symlink"];
    directadmin_req("CMD_API_DOMAIN", $fields, $params, true);
}

function directadmin_GetMxRecords($params)
{
    $domain = $params["domain"];
    $serverUsername = $params["serverusername"];
    $params["serverusername"] = $serverUsername . "|" . $params["username"];
    $command = "CMD_API_DNS_CONTROL";
    $fields = ["domain" => $domain];
    $response = directadmin_req($command, $fields, $params);
    if (array_key_exists("error", $response) && $response["error"] === "1") {
        throw new Exception("MX Retrieval Failed: " . $response["details"]);
    }
    list($dnsRecords) = array_keys($response);
    $mxRecords = NULL;
    preg_match_all("/^.*(MX).*\$/m", trim($dnsRecords), $mxRecords);
    $mxRecords = $mxRecords[0];
    $returnedRecords = [];
    foreach ($mxRecords as $key => $mxRecord) {
        $mxRecord = preg_split("/\\s+/", $mxRecord);
        $returnedRecords[] = ["id" => $key, "mx" => $mxRecord[5] . "." . $mxRecord[0], "priority" => $mxRecord[4]];
    }
    return ["mxRecords" => $returnedRecords];
}

function directadmin_DeleteMxRecords($params)
{
    $domain = $params["mxDomain"];
    $serverUsername = $params["serverusername"];
    $params["serverusername"] = $serverUsername . "|" . $params["username"];
    $command = "CMD_API_DNS_CONTROL";
    $fields = [];
    $fields["domain"] = $domain;
    $fields["action"] = "select";
    $fields["delete"] = "Delete Selected";
    foreach ($params["mxRecords"] as $mxRecord) {
        $priority = $mxRecord["priority"];
        $mxRecord = str_replace("." . $domain . ".", "", $mxRecord["mx"]);
        $fields["mxrecs0"] = "name=" . $domain . ".&value=" . $priority . "+" . $mxRecord;
        $response = directadmin_req($command, $fields, $params);
        if (array_key_exists("error", $response) && $response["error"] === "1") {
            throw new Exception("MX Deletion Failed: " . $response["details"]);
        }
    }
    $response = directadmin_req($command, $fields, $params);
    if (array_key_exists("error", $response) && $response["error"] === "1") {
        throw new Exception("MX Deletion Failed: " . $response["details"]);
    }
}

function directadmin_AddMxRecords($params)
{
    $domain = $params["mxDomain"];
    $serverUsername = $params["serverusername"];
    $params["serverusername"] = $serverUsername . "|" . $params["username"];
    $command = "CMD_API_DNS_CONTROL";
    $fields = [];
    $fields["action"] = "add";
    $fields["name"] = $domain . ".";
    $fields["domain"] = $domain;
    foreach ($params["mxRecords"] as $mxRecord => $priority) {
        $fields["type"] = "MX";
        $fields["value"] = $priority;
        $fields["mx_value"] = str_replace("." . $domain . ".", "", $mxRecord);
        $response = directadmin_req($command, $fields, $params);
        if (array_key_exists("error", $response) && $response["error"] === "1") {
            throw new Exception("MX Creation Failed: " . $response["details"]);
        }
    }
    if (array_key_exists("internal", $params)) {
        $fields = [];
        $fields["action"] = "internal";
        $fields["domain"] = $domain;
        $fields["internal"] = $params["internal"];
        directadmin_req("CMD_API_DNS_MX", $fields, $params);
    }
}

function directadmin_CreateFileWithinDocRoot($params)
{
    $basePath = "/public_html";
    $dir = array_key_exists("dir", $params) ? $params["dir"] : "";
    $serverUsername = $params["serverusername"];
    $params["serverusername"] = $serverUsername . "|" . $params["username"];
    if ($dir) {
        $dirParts = explode("/", $dir);
        foreach ($dirParts as $dirPart) {
            $command = "CMD_API_FILE_MANAGER";
            $fields = ["action" => "folder", "path" => $basePath, "name" => $dirPart];
            directadmin_req($command, $fields, $params, true);
            $basePath .= "/" . $dirPart;
        }
    }
    $command = "CMD_API_FILE_MANAGER";
    $fields = ["action" => "file", "path" => $basePath, "name" => $params["filename"], "file" => "Create"];
    directadmin_req($command, $fields, $params, true);
    $command = "CMD_API_FILE_MANAGER";
    $fields = ["action" => "edit", "path" => $basePath, "filename" => $params["filename"], "text" => $params["fileContent"]];
    $response = directadmin_req($command, $fields, $params, true);
    if (array_key_exists("error", $response) && $response["error"] === "1") {
        throw new Exception("DirectAdmin: Unable to create DV Auth File: " . $response["details"]);
    }
}

function directadmin_CreateFTPAccount($params)
{
    $serverUsername = $params["serverusername"];
    $params["serverusername"] = $serverUsername . "|" . $params["username"];
    $command = "CMD_API_FTP";
    $fields = ["action" => "create", "domain" => $params["domain"], "user" => $params["ftpUsername"], "passwd" => $params["ftpPassword"], "passwd2" => $params["ftpPassword"], "type" => "domain"];
    $response = directadmin_req($command, $fields, $params, true);
    if (array_key_exists("error", $response) && $response["error"] === "1") {
        throw new Exception("Unable to Create FTP Account: " . $response["details"]);
    }
}

function directadmin_ListAccounts($params)
{
    $commands = ["CMD_API_SHOW_USERS", "CMD_API_SHOW_RESELLERS"];
    $accounts = [];
    foreach ($commands as $command) {
        $response = directadmin_req($command, [], $params, true);
        if (array_key_exists("error", $response) && $response["error"] === "1") {
            if ($response["text"] !== "You cannot execute that command") {
                return ["error" => "Unable to Obtain Account List - " . $response["details"]];
            }
        } else {
            if (array_key_exists("list", $response)) {
                foreach ($response["list"] as $user) {
                    $command2 = "CMD_API_SHOW_USER_CONFIG";
                    $response2 = directadmin_req($command2, ["user" => $user], $params);
                    if (array_key_exists("error", $response2) && $response2["error"] === "1") {
                        return ["error" => "Unable to Obtain Account List - " . $response2["details"]];
                    }
                    $status = WHMCS\Service\Status::ACTIVE;
                    if ($response2["suspended"] !== "no") {
                        $status = WHMCS\Service\Status::SUSPENDED;
                    }
                    try {
                        $createdDate = WHMCS\Carbon::parse($response2["date_created"])->toDateTimeString();
                    } catch (Exception $e) {
                        $createdDate = WHMCS\Carbon::today()->toDateTimeString();
                    }
                    $account = ["name" => $response2["username"], "email" => $response2["email"], "username" => $response2["username"], "domain" => $response2["domain"], "uniqueIdentifier" => $response2["domain"], "product" => $response2["package"], "primaryip" => $response2["ip"], "created" => $createdDate, "status" => $status];
                    $accounts[] = $account;
                }
            }
        }
    }
    return ["success" => true, "accounts" => $accounts];
}

function directadmin_GetUserCount($params)
{
    $commands = ["CMD_API_SHOW_USERS", "CMD_API_SHOW_RESELLERS"];
    $totalCount = 0;
    $ownedAccounts = 0;
    foreach ($commands as $command) {
        $response = directadmin_req($command, [], $params, true);
        if (array_key_exists("error", $response) && $response["error"] === "1") {
            if ($response["text"] !== "You cannot execute that command") {
                return ["error" => "Unable to Obtain Account List - " . $response["details"]];
            }
        } else {
            if (array_key_exists("list", $response)) {
                $totalCount += count($response["list"]);
                $ownedAccounts += count($response["list"]);
                if ($command == "CMD_API_SHOW_RESELLERS") {
                    foreach ($response["list"] as $reseller) {
                        $response2 = directadmin_req("CMD_API_SHOW_USERS", ["reseller" => $reseller], $params, true);
                        if (array_key_exists("list", $response2)) {
                            $totalCount += count($response2["list"]);
                        }
                    }
                }
            }
        }
    }
    return ["success" => true, "totalAccounts" => $totalCount, "ownedAccounts" => $ownedAccounts];
}

function directadmin_GetRemoteMetaData($params)
{
    try {
        $version = "-";
        $loads = [];
        $maxUsers = 0;
        $response = directadmin_req("CMD_API_SYSTEM_INFO", [], $params, true);
        if (array_key_exists("directadmin", $response)) {
            $version = explode("|", $response["directadmin"]);
            $version = $version[0];
        }
        return ["version" => $version, "load" => $loads, "max_accounts" => $maxUsers];
    } catch (Exception $e) {
        return ["success" => false, "error" => $e->getMessage()];
    }
}

function directadmin_RenderRemoteMetaData($params)
{
    $remoteData = $params["remoteData"];
    if ($remoteData) {
        $metaData = $remoteData->metaData;
        $version = "Unknown";
        if (array_key_exists("version", $metaData)) {
            $version = $metaData["version"];
        }
        return "DirectAdmin Version: " . $version;
    }
    return "";
}

function directadmin_MetricItems()
{
    $transName = function ($key) {
        if (App::isAdminAreaRequest()) {
            return AdminLang::trans($key);
        }
        return Lang::trans($key);
    };
    if (!$items) {
        $items = [new WHMCS\UsageBilling\Metrics\Metric("quota", $transName("usagebilling.metric.diskSpace"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\GigaBytes()), new WHMCS\UsageBilling\Metrics\Metric("bandwidth", $transName("usagebilling.metric.bandwidth"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_PERIOD_MONTH, new WHMCS\UsageBilling\Metrics\Units\GigaBytes()), new WHMCS\UsageBilling\Metrics\Metric("nemails", $transName("usagebilling.metric.emailAccounts"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Accounts("Email Accounts")), new WHMCS\UsageBilling\Metrics\Metric("vdomains", $transName("usagebilling.metric.addonDomains"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Domains("Addon Domains")), new WHMCS\UsageBilling\Metrics\Metric("domainptr", $transName("usagebilling.metric.parkedDomains"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Domains("Parked Domains")), new WHMCS\UsageBilling\Metrics\Metric("nsubdomains", $transName("usagebilling.metric.subDomains"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Domains("Subdomains")), new WHMCS\UsageBilling\Metrics\Metric("mysql", $transName("usagebilling.metric.subDomains"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\WholeNumber("MySQL Databases", "Database", "Databases")), new WHMCS\UsageBilling\Metrics\Metric("db_quota", $transName("usagebilling.metric.mysqlDatabases"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\GigaBytes()), new WHMCS\UsageBilling\Metrics\Metric("nusers", $transName("usagebilling.metric.subAccounts"), WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT, new WHMCS\UsageBilling\Metrics\Units\Accounts("Sub-Accounts"))];
    }
    return $items;
}

function directadmin_MetricProvider($params)
{
    $items = directadmin_metricitems();
    $serverUsage = function (WHMCS\UsageBilling\Contracts\Metrics\ProviderInterface $provider, $tenant = NULL) use($params) {
        $usage = [];
        $accounts = directadmin_listaccounts($params);
        if (empty($accounts["accounts"])) {
            return $usage;
        }
        $tenants = [];
        foreach ($accounts["accounts"] as $account) {
            if (!empty($account["username"])) {
                $tenants[$account["username"]] = $account["domain"];
            }
        }
        $metrics = $provider->metrics();
        foreach ($tenants as $username => $domain) {
            if (!($tenant && $tenant != $domain)) {
                $params["username"] = $username;
                $data = directadmin_GetStats($params);
                foreach ($data as $name => $remoteValue) {
                    if (isset($metrics[$name])) {
                        $metric = $metrics[$name];
                        $units = $metric->units();
                        if ($units instanceof WHMCS\UsageBilling\Metrics\Units\Bytes) {
                            $to = $units->suffix();
                            $from = "MB";
                            if ($name == "db_quota") {
                                $from = "B";
                            }
                            $remoteValue = $units::convert($remoteValue, $from, $to);
                        }
                        $usage[$domain][$name] = $metric->withUsage(new WHMCS\UsageBilling\Metrics\Usage($remoteValue));
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
    return new WHMCS\UsageBilling\Metrics\Providers\CallbackUsage($items, $serverUsage, $tenantUsage);
}

function directadmin_GetStats($params)
{
    $command = "CMD_API_SHOW_USER_USAGE";
    $username = $params["username"];
    $postVars = ["user" => strtolower($username)];
    $service = WHMCS\Service\Service::with("product")->where("server", $params["serverid"])->where("username", $username)->first();
    if ($service->product->type == WHMCS\Product\Product::TYPE_RESELLER) {
        $command = "CMD_API_RESELLER_STATS";
        $params["serverusername"] = $params["serverusername"] . "|" . $username;
        $postVars["type"] = "usage";
    }
    $response = directadmin_req($command, $postVars, $params);
    if (!empty($response["error"])) {
        $error = $response["text"];
        throw new Exception("Unable to get stats: " . $error);
    }
    return $response;
}

function directadmin_GetSPFRecord($params)
{
    $command = "CMD_API_DNS_CONTROL";
    $postVars = ["domain" => $params["spfDomain"], "json" => "yes"];
    $serverUsername = $params["serverusername"];
    $params["serverusername"] = $serverUsername . "|" . $params["username"];
    $response = directadmin_req($command, $postVars, $params);
    if (!empty($response["error"])) {
        $error = $response["text"];
        throw new Exception("Unable to get SPF Record: " . $error);
    }
    $records = collect($response["records"]);
    $txtRecords = $records->where("type", "=", "TXT");
    $spfRecord = NULL;
    foreach ($txtRecords as $txtRecord) {
        if (stristr($txtRecord["value"], "spf") !== false) {
            $spfRecord = $txtRecord["value"];
            return ["spfRecord" => $spfRecord];
        }
    }
}

function directadmin_SetSPFRecord($params)
{
    $command = "CMD_API_DNS_CONTROL";
    $postVars = ["action" => "edit", "domain" => $params["spfDomain"], "type" => "TXT", "txtrecs0" => "name=" . $params["spfDomain"] . ".", "name" => $params["spfDomain"] . ".", "value" => $params["spfRecord"]];
    $serverUsername = $params["serverusername"];
    $params["serverusername"] = $serverUsername . "|" . $params["username"];
    $response = directadmin_req($command, $postVars, $params);
    if (!empty($response["error"])) {
        $error = $response["text"];
        throw new Exception("Unable to set SPF Record: " . $error);
    }
}

function directadmin_CustomConfigOptions($params)
{
    $fields = [];
    if (isset($params["configoption4"])) {
        $fields["suspend_at_limit"] = "ON";
    }
    if (isset($params["configoptions"]["Disk Space"])) {
        $fields["quota"] = $params["configoptions"]["Disk Space"];
    }
    if (isset($params["configoptions"]["Bandwidth"])) {
        $fields["bandwidth"] = $params["configoptions"]["Bandwidth"];
    }
    if (isset($params["configoptions"]["FTP Accounts"])) {
        $fields["ftp"] = $params["configoptions"]["FTP Accounts"];
    } else {
        $fields["uftp"] = "ON";
    }
    if (isset($params["configoptions"]["Email Accounts"])) {
        $fields["nemails"] = $params["configoptions"]["Email Accounts"];
    } else {
        $fields["unemails"] = "ON";
    }
    if (isset($params["configoptions"]["MySQL Databases"])) {
        $fields["mysql"] = $params["configoptions"]["MySQL Databases"];
    } else {
        $fields["umysql"] = "ON";
    }
    if (isset($params["configoptions"]["Subdomains"])) {
        $fields["nsubdomains"] = $params["configoptions"]["Subdomains"];
    } else {
        $fields["unsubdomains"] = "ON";
    }
    if (isset($params["configoptions"]["Parked Domains"])) {
        $fields["domainptr"] = $params["configoptions"]["Parked Domains"];
    } else {
        $fields["udomainptr"] = "ON";
    }
    if (isset($params["configoptions"]["Addon Domains"])) {
        $fields["vdomains"] = $params["configoptions"]["Addon Domains"];
    } else {
        $fields["uvdomains"] = "ON";
    }
    if (isset($params["configoptions"]["CGI Access"])) {
        $fields["cgi"] = "ON";
    }
    if (isset($params["configoptions"]["Shell Access"])) {
        $fields["ssh"] = "ON";
    }
    if (isset($params["configoptions"]["PHP"])) {
        $fields["php"] = "ON";
    }
    if (isset($params["configoptions"]["SSL"])) {
        $fields["ssl"] = "ON";
    }
    if (isset($params["configoptions"]["System Info"])) {
        $fields["sysinfo"] = "ON";
    }
    if (isset($params["configoptions"]["DNS Control"])) {
        $fields["dnscontrol"] = "ON";
    }
    if (isset($params["configoptions"]["Cron Jobs"])) {
        $fields["cron"] = "ON";
    }
    if (isset($params["configoptions"]["Catch All"])) {
        $fields["catchall"] = "ON";
    }
    if (isset($params["configoptions"]["Spam Assassin"])) {
        $fields["spam"] = "ON";
    }
    if (isset($params["configoptions"]["Anon FTP"])) {
        $fields["aftp"] = "ON";
    }
    if (isset($params["configoptions"]["Email Forwards"])) {
        if (is_numeric($params["configoptions"]["Email Forwards"])) {
            $fields["nemailf"] = $params["configoptions"]["Email Forwards"];
        } else {
            $fields["unemailf"] = "ON";
        }
    }
    if (isset($params["configoptions"]["Mailing Lists"])) {
        if (is_numeric($params["configoptions"]["Mailing Lists"])) {
            $fields["nemailml"] = $params["configoptions"]["Mailing Lists"];
        } else {
            $fields["nemailml"] = "ON";
        }
    }
    if (isset($params["configoptions"]["Auto Responders"])) {
        if (is_numeric($params["configoptions"]["Auto Responders"])) {
            $fields["nemailr"] = $params["configoptions"]["Auto Responders"];
        } else {
            $fields["unemailr"] = "ON";
        }
    }
    return $fields;
}

function directadmin_GetDns($params)
{
    $command = "CMD_API_DNS_CONTROL";
    $postVars = ["domain" => $params["domain"], "json" => "yes"];
    $serverUsername = $params["serverusername"];
    $params["serverusername"] = $serverUsername . "|" . $params["username"];
    $response = directadmin_req($command, $postVars, $params);
    if (array_key_exists("error", $response) && $response["error"] === "1") {
        throw new Exception("Unable to retrieve DNS Records: " . $response["details"]);
    }
    if (isset($response["records"]) && is_array($response["records"])) {
        return $response["records"];
    }
    throw new Exception("Unable to retrieve DNS Records");
}

function directadmin_ModifyDns($params)
{
    $serverDnsRecords = directadmin_getdns($params);
    $recordsToCreate = [];
    $dnsRecordsToProvision = $params["dnsRecordsToProvision"];
    foreach ($dnsRecordsToProvision as $recordToProvision) {
        if ($recordToProvision["name"] || $recordToProvision["host"]) {
            $recordToUpdate = NULL;
            $dnsHost = $recordToProvision["name"] ?: $recordToProvision["host"];
            foreach ($serverDnsRecords as $existingRecord) {
                if ($existingRecord["type"] == $recordToProvision["type"] && directadmin__normaliseHostname($existingRecord, $params["domain"]) == $dnsHost) {
                    $recordToUpdate = $existingRecord;
                    if (is_null($recordToUpdate)) {
                        $recordsToCreate[] = ["name" => $dnsHost, "type" => $recordToProvision["type"], "value" => $recordToProvision["value"]];
                    } else {
                        switch ($recordToProvision["type"]) {
                            case "A":
                                $type = "arecs0";
                                break;
                            case "CNAME":
                                $type = "cnamerecs0";
                                break;
                            case "TXT":
                            case "SRV":
                                $type = "txtrecs0";
                                break;
                            case "MX":
                                $type = "mxrecs0";
                                break;
                            default:
                                $recordToUpdate[$type] = $recordToUpdate["combined"];
                                unset($recordToUpdate["combined"]);
                                $recordToUpdate["value"] = $recordToProvision["value"];
                                $params["dnsRecord"] = $recordToUpdate;
                                directadmin_SetDnsRecord($params);
                                unset($params["dnsRecord"]);
                        }
                    }
                }
            }
        }
    }
    if (0 < count($recordsToCreate)) {
        unset($params["dnsRecordsToProvision"]);
        $params["dnsRecords"] = $recordsToCreate;
        directadmin_AddDns($params);
    }
}

function directadmin_AddDns($params)
{
    $params["serverusername"] = $params["serverusername"] . "|" . $params["username"];
    $command = "CMD_API_DNS_CONTROL";
    $dnsRecords = [];
    foreach ($params["dnsRecords"] as $dnsRecord) {
        if ($dnsRecord["type"] || $dnsRecord["value"]) {
            $dnsRecords[] = ["domain" => $params["domain"], "action" => "add", "type" => $dnsRecord["type"], "name" => directadmin__normaliseHostname($dnsRecord, $params["domain"]), "value" => $dnsRecord["value"]];
        }
    }
    unset($params["dnsRecords"]);
    foreach ($dnsRecords as $dnsRecord) {
        $response = directadmin_req($command, $dnsRecord, $params, true);
        if (array_key_exists("error", $response) && $response["error"] === "1") {
            throw new Exception("Unable to Add DNS Records: " . $response["details"]);
        }
    }
}

function directadmin_SetDnsRecord($params)
{
    $params["serverusername"] = $params["serverusername"] . "|" . $params["username"];
    $command = "CMD_API_DNS_CONTROL";
    $dnsRecord = $params["dnsRecord"];
    $dnsRecord["domain"] = $params["domain"];
    $dnsRecord["action"] = "edit";
    $response = directadmin_req($command, $dnsRecord, $params, true);
    if (array_key_exists("error", $response) && $response["error"] === "1") {
        throw new Exception("Unable to Modify DNS: " . $response["details"]);
    }
}

function directadmin__normaliseHostname($dnsRecord, $domain)
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

function directadmin__checkResponse($httpStatusCode, $responseHeaders, $responseBody, int $curlResponseError)
{
    if ($curlResponseError != 0) {
        return ["error" => true, "message" => sprintf("Connection Error: %d - %s", $curlResponseError, curl_strerror($curlResponseError))];
    }
    if (!empty($responseHeaders["x-use-https"])) {
        return ["error" => true, "code" => $httpStatusCode, "message" => "Secure Connection Is Required"];
    }
    if (!empty($responseHeaders["x-directadmin"])) {
        foreach ($responseHeaders["x-directadmin"] as $header) {
            if ($header == "Unauthorized") {
                return ["error" => true, "code" => $httpStatusCode, "message" => "Login Failed"];
            }
        }
    }
    if ($httpStatusCode == 403) {
        $responseArray = [];
        parse_str($responseBody, $responseArray);
        $message = "";
        if (!empty($responseArray["text"])) {
            $message .= $responseArray["text"];
        }
        if (!empty($responseArray["details"])) {
            $message .= $responseArray["details"];
        }
        return ["error" => true, "code" => $httpStatusCode, "message" => $message];
    }
    if (strpos($responseBody, "DirectAdmin Login") !== false || strpos($responseBody, "AUTH_METHOD: \"CMD_LOGIN\"") !== false) {
        return ["error" => true, "code" => $httpStatusCode, "message" => "Login Failed"];
    }
    if (strpos($responseBody, "Your IP is blacklisted") !== false) {
        return ["error" => true, "code" => $httpStatusCode, "message" => "WHMCS Host Server IP is Blacklisted"];
    }
    if (strtolower(trim($responseBody)) === "use https") {
        return ["error" => true, "code" => $httpStatusCode, "message" => "Secure Connection Is Required"];
    }
    if (strpos($responseBody, "License check failure") !== false) {
        return ["error" => true, "code" => $httpStatusCode, "message" => "Invalid License"];
    }
    return ["success" => true];
}
