<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!$days) {
    $days = 7;
}
if (!$expires) {
    $expires = date("YmdHis", mktime(date("H"), date("i"), date("s"), date("m"), date("d") + $days, date("Y")));
}
$banid = insert_query("tblbannedips", ["ip" => $ip, "reason" => $reason, "expires" => $expires]);
$apiresults = ["result" => "success", "banid" => $banid];
