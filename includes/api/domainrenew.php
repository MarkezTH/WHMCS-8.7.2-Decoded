<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("RegRenewDomain")) {
    require ROOTDIR . "/includes/registrarfunctions.php";
}
$domainid = App::getFromRequest("domainid");
$domain = App::getFromRequest("domain");
if (!empty($domainid)) {
    $result = select_query("tbldomains", "id", ["id" => $domainid]);
} else {
    $result = select_query("tbldomains", "id", ["domain" => $domain]);
}
$data = mysql_fetch_array($result);
if (!is_array($data) || empty($data[0])) {
    $apiresults = ["result" => "error", "message" => "Domain Not Found"];
    return false;
}
$domainid = $data[0];
if (!empty($regperiod)) {
    update_query("tbldomains", ["registrationperiod" => $regperiod], ["id" => $domainid]);
}
$params = ["domainid" => $domainid];
$values = RegRenewDomain($params);
if (!empty($values["error"])) {
    $apiresults = ["result" => "error", "message" => "Registrar Error Message", "error" => $values["error"]];
    return false;
}
$apiresults = array_merge(["result" => "success"], $values);
