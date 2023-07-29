<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getClientsDetails")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
$orderId = App::getFromRequest("orderid");
$order = new WHMCS\Order();
$order->setID($orderId);
$fraudModule = $order->getActiveFraudModule();
$orderId = $order->getData("id");
if (!$orderId) {
    $apiresults = ["result" => "error", "message" => "Order ID Not Found"];
    return false;
}
if (!$fraudModule) {
    $apiresults = ["result" => "error", "message" => "No Active Fraud Module"];
    return false;
}
$userId = $order->getData("userid");
$ipAddress = $order->getData("ipaddress");
$invoiceId = $order->getData("invoiceid");
if (App::isInRequest("ipaddress")) {
    $ipAddress = App::getFromRequest("ipaddress");
}
$results = $fraudResults = "";
$fraud = new WHMCS\Module\Fraud();
if ($fraud->load($fraudModule)) {
    $results = $fraud->doFraudCheck($orderId, $userId, $ipAddress);
    $fraudResults = $fraud->processResultsForDisplay($orderId, $results["fraudoutput"]);
}
if (!is_array($results)) {
    $results = [];
}
$error = $results["error"] ?? NULL;
if (!empty($results["userinput"])) {
    $status = "User Input Required";
} else {
    if (!empty($results["error"])) {
        $status = "Fail";
        WHMCS\Database\Capsule::table("tblorders")->where("id", "=", $orderId)->update(["status" => "Fraud"]);
        WHMCS\Database\Capsule::table("tblhosting")->where("orderid", "=", $orderId)->where("domainstatus", "=", "Pending")->update(["domainstatus" => "Fraud"]);
        WHMCS\Database\Capsule::table("tblhostingaddons")->where("orderid", "=", $orderId)->where("status", "=", "Pending")->update(["status" => "Fraud"]);
        WHMCS\Database\Capsule::table("tbldomains")->where("orderid", "=", $orderId)->where("status", "=", "Pending")->update(["status" => "Fraud"]);
        WHMCS\Database\Capsule::table("tblinvoices")->where("id", "=", $invoiceId)->where("status", "=", "Unpaid")->update(["status" => "Cancelled"]);
    } else {
        $status = "Pass";
        WHMCS\Database\Capsule::table("tblorders")->where("id", "=", $orderId)->update(["status" => "Pending"]);
        WHMCS\Database\Capsule::table("tblhosting")->where("orderid", "=", $orderId)->where("domainstatus", "=", "Fraud")->update(["domainstatus" => "Pending"]);
        WHMCS\Database\Capsule::table("tblhostingaddons")->where("orderid", "=", $orderId)->where("status", "=", "Fraud")->update(["status" => "Pending"]);
        WHMCS\Database\Capsule::table("tbldomains")->where("orderid", "=", $orderId)->where("status", "=", "Fraud")->update(["status" => "Pending"]);
        WHMCS\Database\Capsule::table("tblinvoices")->where("id", "=", $invoiceId)->where("status", "=", "Cancelled")->update(["status" => "Unpaid"]);
    }
}
$apiresults = ["result" => "success", "status" => $status, "module" => $fraudModule, "results" => safe_serialize($fraudResults)];
$responsetype = "xml";
