<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("RegRegisterDomain")) {
    require ROOTDIR . "/includes/registrarfunctions.php";
}
$domainid = (int) App::getFromRequest("domainid");
$idnLanguage = (int) App::getFromRequest("idnlanguage");
if ($domainid) {
    $result = select_query("tbldomains", "id", ["id" => $domainid]);
} else {
    $domain = App::getFromRequest("domain");
    $result = select_query("tbldomains", "id", ["domain" => $domain]);
}
$data = mysql_fetch_array($result);
$domainid = $data[0];
if (!$domainid) {
    $apiresults = ["result" => "error", "message" => "Domain Not Found"];
    return false;
}
if ($idnLanguage) {
    $idnLanguages = WHMCS\Domains\Idna::getLanguages();
    if (in_array($idnLanguage, $idnLanguages)) {
        $idnLanguage = $idnLanguages[$idnLanguage];
    }
    if (!array_key_exists($idnLanguage, $idnLanguages)) {
        $apiresults = ["result" => "error", "message" => "Invalid IDN Language. Must be one of: " . array_keys($idnLanguages)];
        return NULL;
    }
    $extraDetails = WHMCS\Domain\Extra::firstOrNew(["domain_id" => $domainid, "name" => "idnLanguage"]);
    $extraDetails->value = $idnLanguage;
    $extraDetails->save();
}
$params = ["domainid" => $domainid];
$values = RegRegisterDomain($params);
if (!empty($values["error"])) {
    $apiresults = ["result" => "error", "message" => "Registrar Error Message", "error" => $values["error"]];
    return false;
}
$apiresults = array_merge(["result" => "success"], $values);
