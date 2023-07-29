<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$contactid = App::getFromRequest("contactid");
try {
    $contact = WHMCS\User\Client\Contact::findOrFail($contactid);
} catch (Exception $e) {
    $apiresults = ["result" => "error", "message" => "Contact ID Not Found"];
    return NULL;
}
$client = $contact->client;
$legacyClient = new WHMCS\Client($client);
$legacyClient->deleteContact($contactid);
$apiresults = ["result" => "success", "message" => $contactid];
