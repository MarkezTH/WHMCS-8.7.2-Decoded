<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getRegistrarConfigOptions")) {
    require ROOTDIR . "/includes/registrarfunctions.php";
}
if (!function_exists("ModuleBuildParams")) {
    require ROOTDIR . "/includes/modulefunctions.php";
}
if (!function_exists("deleteOrder")) {
    require ROOTDIR . "/includes/orderfunctions.php";
}
$result = select_query("tblorders", "", ["id" => (int) $orderid]);
$data = mysql_fetch_array($result);
if (!is_array($data) || empty($data["id"])) {
    $apiresults = ["result" => "error", "message" => "Order ID not found"];
} else {
    if (canOrderBeDeleted($orderid)) {
        deleteOrder($orderid);
        $apiresults = ["result" => "success"];
    } else {
        $apiresults = ["result" => "error", "message" => "The order status must be in Cancelled or Fraud to be deleted"];
    }
}
