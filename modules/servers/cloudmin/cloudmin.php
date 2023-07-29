<?php

function cloudmin_MetaData()
{
    return ["DisplayName" => "Cloudmin", "APIVersion" => "1.0"];
}

function cloudmin_ConfigOptions()
{
    global $packageconfigoption;
    $imagesresult = "";
    if ($packageconfigoption[6]) {
        $result = select_query("tblservers", "", ["type" => "cloudmin", "active" => "1"]);
        $data = mysql_fetch_array($result);
        $params["serverip"] = $data["ipaddress"];
        $params["serverhostname"] = $data["hostname"];
        $params["serverusername"] = $data["username"];
        $params["serverpassword"] = decrypt($data["password"]);
        $params["serveraccesshash"] = $data["accesshash"];
        $params["serversecure"] = $data["secure"];
        if ($params["serverusername"]) {
            $postfields = [];
            $postfields["program"] = "list-images";
            $imagesresult = cloudmin_req($params, $postfields);
        }
    }
    $configarray = ["Type" => ["Type" => "dropdown", "Options" => "xen,openvz,vservers,zones,real"], "Xen Host" => ["Type" => "text", "Size" => "30", "Description" => "(Optional)"], "Setup Type" => ["Type" => "dropdown", "Options" => "system,owner"], "Plan Name" => ["Type" => "text", "Size" => "20", "Description" => ""]];
    if (is_array($imagesresult)) {
        $configarray["Image"] = ["Type" => "dropdown", "Options" => implode(",", $imagesresult)];
    } else {
        $configarray["Image"] = ["Type" => "text", "Size" => "30"];
    }
    $configarray["Get From Server"] = ["Type" => "yesno", "Description" => "Check to load Image options from default server"];
    return $configarray;
}

function cloudmin_CreateAccount($params)
{
    if ($params["configoption3"] == "owner") {
        $postfields = [];
        $postfields["program"] = "create-owner";
        $postfields["name"] = $params["customfields"]["Username"];
        $postfields["email"] = $params["clientsdetails"]["email"];
        $postfields["pass"] = $params["password"];
        $postfields["plan"] = $params["configoption4"];
        $result = cloudmin_req($params, $postfields);
    } else {
        $postfields = [];
        $postfields["program"] = "create-system";
        $postfields["type"] = $params["configoption1"];
        if ($params["configoption2"]) {
            $postfields["xen-host"] = $params["configoption2"];
        }
        $postfields["host"] = $params["customfields"]["Hostname"];
        $postfields["ssh-pass"] = $params["password"];
        $postfields["image"] = $params["configoption5"];
        $postfields["desc"] = "WHMCS Service ID " . $params["serviceid"];
        $result = cloudmin_req($params, $postfields);
    }
    return $result;
}

function cloudmin_SuspendAccount($params)
{
    if ($params["configoption3"] == "owner") {
        $postfields = [];
        $postfields["program"] = "modify-owner";
        $postfields["name"] = $params["customfields"]["Username"];
        $postfields["lock"] = "1";
        $result = cloudmin_req($params, $postfields);
    } else {
        $postfields = [];
        $postfields["program"] = "pause-system";
        $postfields["host"] = $params["domain"];
        $result = cloudmin_req($params, $postfields);
    }
    return $result;
}

function cloudmin_UnsuspendAccount($params)
{
    if ($params["configoption3"] == "owner") {
        $postfields = [];
        $postfields["program"] = "modify-owner";
        $postfields["name"] = $params["customfields"]["Username"];
        $postfields["lock"] = "0";
        $result = cloudmin_req($params, $postfields);
    } else {
        $postfields = [];
        $postfields["program"] = "unpause-system";
        $postfields["host"] = $params["domain"];
        $result = cloudmin_req($params, $postfields);
    }
    return $result;
}

function cloudmin_TerminateAccount($params)
{
    if ($params["configoption3"] == "owner") {
        $postfields = [];
        $postfields["program"] = "delete-owner";
        $postfields["name"] = $params["customfields"]["Username"];
        $result = cloudmin_req($params, $postfields);
    } else {
        $postfields = [];
        $postfields["program"] = "delete-system";
        $postfields["host"] = $params["domain"];
        $result = cloudmin_req($params, $postfields);
    }
    return $result;
}

function cloudmin_AdminCustomButtonArray()
{
    $buttonarray = ["Reboot" => "reboot", "Startup" => "startup", "Shutdown" => "shutdown"];
    return $buttonarray;
}

function cloudmin_ClientAreaCustomButtonArray()
{
    $buttonarray = ["Reboot" => "reboot", "Startup" => "startup", "Shutdown" => "shutdown"];
    return $buttonarray;
}

function cloudmin_reboot($params)
{
    $postfields = [];
    $postfields["program"] = "reboot-system";
    $postfields["host"] = $params["domain"];
    $result = cloudmin_req($params, $postfields);
    return $result;
}

function cloudmin_startup($params)
{
    $postfields = [];
    $postfields["program"] = "startup-system";
    $postfields["host"] = $params["domain"];
    $result = cloudmin_req($params, $postfields);
    return $result;
}

function cloudmin_shutdown($params)
{
    $postfields = [];
    $postfields["program"] = "shutdown-system";
    $postfields["host"] = $params["domain"];
    $result = cloudmin_req($params, $postfields);
    return $result;
}

function cloudmin_req($params, $postfields)
{
    $domain = $params["serverhostname"] ? $params["serverhostname"] : $params["serverip"];
    $http = $params["serversecure"] ? "https" : "http";
    $url = $http . "://" . $domain . "/server-manager/remote.cgi?" . $fieldstring;
    $fieldstring = "";
    foreach ($postfields as $k => $v) {
        $fieldstring .= $k . "=" . urlencode($v) . "&";
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldstring);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $params["serverusername"] . ":" . $params["serverpassword"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    $data = curl_exec($ch);
    if (curl_errno($ch)) {
        $data = "Curl Error: " . curl_errno($ch) . " - " . curl_error($ch);
    }
    curl_close($ch);
    logModuleCall("cloudmin", $postfields["program"], $postfields, $data);
    if (strpos($data, "Unauthorized")) {
        return "Server Login Invalid";
    }
    $exitstatuspos = strpos($data, "Exit status:");
    $exitstatus = trim(substr($data, $exitstatuspos + 12));
    if ($exitstatus == "0") {
        $result = "success";
        if ($postfields["program"] == "create-system") {
            $pos1 = 0;
            $matchstring = "Creation of Xen system ";
            $pos1 = strpos($data, $matchstring);
            if (!$pos1) {
                $matchstring = "Creation of OpenVZ system ";
                $pos1 = strpos($data, $matchstring);
            }
            $pos2 = strpos($data, " is complete");
            $hostname = substr($data, $pos1 + strlen($matchstring), $pos2 - $pos1 - strlen($matchstring));
            if ($hostname) {
                $params["model"]->serviceProperties->save(["domain" => $hostname]);
            }
        } else {
            if ($postfields["program"] == "list-images") {
                $array = explode("------------------------------ ------------------------------------------------\n", $data);
                $array = $array[1];
                $array = explode("\n", $array);
                $result = [];
                foreach ($array as $line) {
                    if (!$line) {
                    } else {
                        $line = explode("    ", $line, 2);
                        $result[] = trim($line[0]);
                    }
                }
            }
        }
    } else {
        $dataarray = explode("\n", $data);
        $result = $dataarray[0];
    }
    return $result;
}
