<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$clientId = (int) App::getFromRequest("clientid");
if (!$clientId) {
    $clientId = (int) App::getFromRequest("userid");
}
logActivity($description, $clientId);
$apiresults = ["result" => "success"];
