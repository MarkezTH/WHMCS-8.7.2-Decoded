<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("RegSaveRegistrarLock")) {
    require ROOTDIR . "/includes/registrarfunctions.php";
}
if ($domainid) {
    $where = ["id" => $domainid];
} else {
    $where = ["domain" => $domain];
}
$result = select_query("tbldomains", "id,domain,registrar,registrationperiod", $where);
$data = mysql_fetch_array($result);
$domainid = $data[0];
if (!$domainid) {
    $apiresults = ["result" => "error", "message" => "Domain Not Found"];
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
$params["transfertag"] = $newtag;
$values = RegReleaseDomain($params);
if ($values["error"]) {
    $apiresults = ["result" => "error", "message" => "Registrar Error Message", "error" => $values["error"]];
    return false;
}
$apiresults = array_merge(["result" => "success"], $values);
