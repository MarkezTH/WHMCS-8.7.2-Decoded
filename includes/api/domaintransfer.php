<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("RegTransferDomain")) {
    require ROOTDIR . "/includes/registrarfunctions.php";
}
if ($domainid) {
    $result = select_query("tbldomains", "id", ["id" => $domainid]);
} else {
    $result = select_query("tbldomains", "id", ["domain" => $domain]);
}
$data = mysql_fetch_array($result);
$domainid = $data[0];
if (!$domainid) {
    $apiresults = ["result" => "error", "message" => "Domain Not Found"];
    return false;
}
$params = ["domainid" => $domainid];
if ($eppcode) {
    $params["transfersecret"] = $eppcode;
}
$values = RegTransferDomain($params);
if ($values["error"]) {
    $apiresults = ["result" => "error", "message" => "Registrar Error Message", "error" => $values["error"]];
    return false;
}
$apiresults = array_merge(["result" => "success"], $values);
