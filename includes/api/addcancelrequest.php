<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("updateInvoiceTotal")) {
    require ROOTDIR . "/includes/invoicefunctions.php";
}
if (!function_exists("createCancellationRequest")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
$serviceid = (int) App::getFromRequest("serviceid");
$type = (string) App::getFromRequest("type");
$reason = (string) App::getFromRequest("reason");
$result = select_query("tblhosting", "id,userid", ["id" => $serviceid]);
$data = mysql_fetch_array($result);
list($serviceid, $userid) = $data;
if (!$serviceid) {
    $apiresults = ["result" => "error", "message" => "Service ID Not Found"];
    return false;
}
$validtypes = ["Immediate", "End of Billing Period"];
if (!in_array($type, $validtypes)) {
    $type = "End of Billing Period";
}
if (!$reason) {
    $reason = "None Specified (API Submission)";
}
$result = createCancellationRequest($userid, $serviceid, $reason, $type);
if ($result == "success") {
    $apiresults = ["result" => "success", "serviceid" => $serviceid, "userid" => $userid];
} else {
    $apiresults = ["result" => "error", "message" => $result, "serviceid" => $serviceid, "userid" => $userid];
}
