<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("RegGetNameservers")) {
    require ROOTDIR . "/includes/registrarfunctions.php";
}
$result = select_query("tbldomains", "id,domain,registrar,registrationperiod", ["id" => $domainid]);
$data = mysql_fetch_array($result);
if (!is_array($data) || empty($data[0])) {
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
$values = RegGetNameservers($params);
if (isset($values["na"])) {
    $apiresults = ["result" => "error", "message" => "Registrar Function Not Supported"];
    return false;
}
if (!empty($values["error"])) {
    $apiresults = ["result" => "error", "message" => "Registrar Error Message", "error" => $values["error"]];
    return false;
}
$apiresults = array_merge(["result" => "success"], $values);
