<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getTLDPriceList")) {
    require_once ROOTDIR . "/includes/domainfunctions.php";
}
if (!function_exists("recalcPromoAmount")) {
    require_once ROOTDIR . "/includes/clientfunctions.php";
}
$domainid = App::getFromRequest("domainid");
$domain = App::getFromRequest("domain");
$query = WHMCS\Database\Capsule::table("tbldomains");
if (!empty($domainid)) {
    $query->where("id", $domainid);
} else {
    $query->where("domain", $domain);
}
$domainId = $query->value("id");
if (!$domainId) {
    $apiresults = ["result" => "error", "message" => "Domain ID Not Found"];
    return false;
}
$domainObj = WHMCS\Domain\Domain::find($domainId);
$whmcs = WHMCS\Application::getInstance();
$dnsManagement = $whmcs->get_req_var("dnsmanagement");
$emailForwarding = $whmcs->get_req_var("emailforwarding");
$idProtection = $whmcs->get_req_var("idprotection");
$doNotRenew = $whmcs->get_req_var("donotrenew");
$updateDomain = WHMCS\Database\Capsule::table("tbldomains");
$updateVals = [];
if (!empty($type)) {
    $updateVals["type"] = $type;
}
if (!empty($regdate)) {
    $updateVals["registrationdate"] = $regdate;
}
if (!empty($domain)) {
    $updateVals["domain"] = $domain;
}
if (!empty($firstpaymentamount)) {
    $updateVals["firstpaymentamount"] = $firstpaymentamount;
}
if (!empty($recurringamount)) {
    $updateVals["recurringamount"] = $recurringamount;
}
if (!empty($registrar)) {
    $activeRegistrars = new WHMCS\Module\Registrar();
    $registrarsAvailable = $activeRegistrars->getActiveModules();
    $registrarToCheck = (string) str_replace(" ", "", strtolower($registrar));
    if (in_array($registrarToCheck, $registrarsAvailable)) {
        $updateVals["registrar"] = $registrarToCheck;
    } else {
        $apiresults = ["result" => "error", "message" => "The Registrar (" . $registrar . ") is not active"];
        return false;
    }
}
if (!empty($regperiod)) {
    $updateVals["registrationperiod"] = $regperiod;
}
if (!empty($expirydate)) {
    $updateVals["expirydate"] = $expirydate;
}
if (!empty($nextduedate)) {
    $updateVals["nextduedate"] = $nextduedate;
    $updateVals["nextinvoicedate"] = $nextduedate;
}
if (!empty($paymentmethod)) {
    $updateVals["paymentmethod"] = $paymentmethod;
}
if (!empty($subscriptionid)) {
    $updateVals["subscriptionid"] = $subscriptionid;
}
if (!empty($status)) {
    $updateVals["status"] = $status;
}
if (!empty($notes)) {
    $updateVals["additionalnotes"] = $notes;
}
if (isset($_REQUEST["dnsmanagement"])) {
    $dnsManagement = empty($dnsManagement) ? "" : "1";
    $updateVals["dnsmanagement"] = $dnsManagement;
}
if (isset($_REQUEST["emailforwarding"])) {
    $emailForwarding = empty($emailForwarding) ? "" : "1";
    $updateVals["emailforwarding"] = $emailForwarding;
}
if (isset($_REQUEST["idprotection"])) {
    $idProtection = empty($idProtection) ? "" : "1";
    $updateVals["idprotection"] = $idProtection;
}
if (isset($_REQUEST["donotrenew"])) {
    $doNotRenew = empty($doNotRenew) ? "" : "1";
    $updateVals["donotrenew"] = $doNotRenew;
}
if (!empty($promoid)) {
    $updateVals["promoid"] = $promoid;
}
if (!empty($updateVals)) {
    $updateDomain->where("id", $domainObj->id)->update($updateVals);
}
if (isset($autorecalc)) {
    if (!function_exists("getCurrency")) {
        require_once ROOTDIR . "/includes/functions.php";
    }
    $domainObj->refresh();
    $domainObj->recalculateRecurringPrice()->save();
}
$apiresults = ["result" => "success", "domainid" => $domainObj->id];
if (isset($updatens)) {
    if (!function_exists("RegSaveNameservers")) {
        require_once ROOTDIR . "/includes/registrarfunctions.php";
    }
    $ns1 = App::getFromRequest("ns1");
    $ns2 = App::getFromRequest("ns2");
    $ns3 = App::getFromRequest("ns3");
    $ns4 = App::getFromRequest("ns4");
    $ns5 = App::getFromRequest("ns5");
    if (!($ns1 && $ns2)) {
        $apiresults = ["result" => "error", "message" => "ns1 and ns2 required"];
        return false;
    }
    $params = [];
    $params["domainid"] = $domainObj->id;
    $params["sld"] = $domainObj->getDomainObject()->getUnicodeSecondLevel();
    $params["tld"] = $domainObj->tld;
    $params["regperiod"] = $domainObj->registrationPeriod;
    $params["registrar"] = $domainObj->registrarModuleName;
    $params["ns1"] = $ns1;
    $params["ns2"] = $ns2;
    $params["ns3"] = $ns3;
    $params["ns4"] = $ns4;
    $params["ns5"] = $ns5;
    $values = RegSaveNameservers($params);
    if ($values["error"]) {
        $apiresults = ["result" => "error", "message" => "Registrar Error Message", "error" => $values["error"]];
        return false;
    }
}
