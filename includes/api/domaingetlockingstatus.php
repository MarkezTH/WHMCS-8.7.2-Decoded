<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("RegGetRegistrarLock")) {
    require ROOTDIR . "/includes/registrarfunctions.php";
}
$result = select_query("tbldomains", "id,domain,registrar,registrationperiod", ["id" => $domainid]);
$data = mysql_fetch_array($result);
$domainid = $data[0];
if (!$domainid) {
    $apiresults = ["result" => "error", "message" => "Domain ID Not Found"];
    return false;
}
$domain = $data["domain"];
$registrar = $data["registrar"];
$regperiod = $data["registrationperiod"];
$domainparts = explode(".", $domain, 2);
$params = [];
$params["domainid"] = $domainid;
list($params["sld"], $params["tld"]) = $domainparts;
$params["regperiod"] = $regperiod;
$params["registrar"] = $registrar;
$params["lockenabled"] = $lockenabled;
$lockstatus = RegGetRegistrarLock($params);
if (!$lockstatus) {
    $lockstatus = "Unknown";
}
$apiresults = ["result" => "success", "lockstatus" => $lockstatus];
