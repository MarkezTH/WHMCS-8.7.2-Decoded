<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$fetchStatus = App::getFromRequest("fetchStatus");
$serviceId = App::getFromRequest("serviceId");
$addonId = App::getFromRequest("addonId");
$servers = [];
$query = WHMCS\Database\Capsule::table("tblservers");
if (!empty($serviceId) && is_numeric($serviceId)) {
    $productServiceServerType = WHMCS\Service\Service::find($serviceId);
    if (!$productServiceServerType instanceof WHMCS\Service\Service) {
        $apiresults = ["result" => "error", "message" => "ServiceID not found"];
        return NULL;
    }
    if (empty($productServiceServerType->serverModel->type)) {
        $apiresults = ["result" => "error", "message" => "ServiceID not assigned to a module type"];
        return NULL;
    }
    $query->where("type", $productServiceServerType->serverModel->type);
}
if (!empty($addonId) && is_numeric($addonId)) {
    $addonServiceServerType = WHMCS\Service\Addon::find($addonId);
    if (!$addonServiceServerType instanceof WHMCS\Service\Addon) {
        $apiresults = ["result" => "error", "message" => "AddonID not found"];
        return NULL;
    }
    if (empty($addonServiceServerType->serverModel->type)) {
        $apiresults = ["result" => "error", "message" => "AddonID not assigned to a module type"];
        return NULL;
    }
    $query->where("type", $addonServiceServerType->serverModel->type);
}
$queryData = $query->where("disabled", "=", 0)->orderBy("name")->get()->all();
foreach ($queryData as $server) {
    $id = $server->id;
    $name = $server->name;
    $hostName = $server->hostname;
    $ipAddress = $server->ipaddress;
    $maxAllowedServices = $server->maxaccounts ?: 1;
    $statusAddress = $server->statusaddress;
    $active = $server->active;
    $moduleType = $server->type;
    $hostingCounts = WHMCS\Database\Capsule::table("tblhosting")->whereIn("domainstatus", ["Active", "Suspended"])->where("server", $id)->count("id");
    $hostingAddonsCount = WHMCS\Database\Capsule::table("tblhostingaddons")->whereIn("status", ["Active", "Suspended"])->where("server", $id)->count("id");
    $activeServices = $hostingCounts + $hostingAddonsCount;
    $percentUsed = @round($activeServices / $maxAllowedServices * 100);
    $http = $serverLoad = $upTime = "";
    if ($fetchStatus) {
        $http = @fsockopen($ipAddress, 80, $errno, $errstr, 5);
        if ($statusAddress) {
            if (strpos($statusAddress, "index.php") === false) {
                if (substr($statusAddress, -1, 1) != "/") {
                    $statusAddress .= "/";
                }
                $statusAddress .= "index.php";
            }
            $fileContents = curlCall($statusAddress, false, [CURLOPT_TIMEOUT => 5]);
            $serverLoad = WHMCS\Input\Sanitize::encode(preg_match("/<load>(.*?)<\\/load>/i", $fileContents, $matches) ? $matches[1] : "-");
            $upTime = WHMCS\Input\Sanitize::encode(preg_match("/<uptime>(.*?)<\\/uptime>/i", $fileContents, $matches) ? $matches[1] : "-");
        }
    }
    $servers[] = ["id" => $id, "name" => $name, "hostname" => $hostName, "ipaddress" => $ipAddress, "active" => (bool) $active, "activeServices" => $activeServices, "maxAllowedServices" => $maxAllowedServices, "percentUsed" => $percentUsed, "module" => $moduleType, "status" => ["http" => (bool) $http, "load" => $serverLoad, "uptime" => $upTime]];
}
$apiresults = ["result" => "success", "servers" => $servers, "fetchStatus" => $fetchStatus];
