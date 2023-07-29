<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$credentialId = (int) $whmcs->getFromRequest("credentialId");
$client = WHMCS\ApplicationLink\Client::find($credentialId);
if (is_null($client)) {
    $apiresults = ["result" => "error", "message" => "Invalid Credential ID provided."];
} else {
    $client->delete();
    $apiresults = ["result" => "success", "credentialId" => $credentialId];
}
