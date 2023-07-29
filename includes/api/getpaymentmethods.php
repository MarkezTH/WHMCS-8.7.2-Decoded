<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getGatewaysArray")) {
    require ROOTDIR . "/includes/gatewayfunctions.php";
}
$paymentmethods = getGatewaysArray();
$apiresults = ["result" => "success", "totalresults" => count($paymentmethods)];
foreach ($paymentmethods as $module => $name) {
    $apiresults["paymentmethods"]["paymentmethod"][] = ["module" => $module, "displayname" => $name];
}
$responsetype = "xml";
