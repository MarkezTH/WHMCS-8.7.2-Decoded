<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("addClient")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
if (!function_exists("updateInvoiceTotal")) {
    require ROOTDIR . "/includes/invoicefunctions.php";
}
if (!function_exists("saveQuote")) {
    require ROOTDIR . "/includes/quotefunctions.php";
}
$subject = App::getFromRequest("subject");
$stage = App::getFromRequest("stage");
$validUntil = App::getFromRequest("validuntil");
$dateCreated = App::getFromRequest("datecreated");
$lineItems = App::getFromRequest("lineitems");
$userId = App::getFromRequest("userid");
if (!$subject) {
    $apiresults = ["result" => "error", "message" => "Subject is required"];
} else {
    if (!in_array($stage, ["Draft", "Delivered", "On Hold", "Accepted", "Lost", "Dead"])) {
        $apiresults = ["result" => "error", "message" => "Invalid Stage"];
    } else {
        if (!$validUntil) {
            $apiresults = ["result" => "error", "message" => "Valid Until is required"];
        } else {
            if (!$dateCreated) {
                $dateCreated = date("Y-m-d");
            }
            $lineItemsArray = [];
            if ($lineItems) {
                $lineItems = base64_decode($lineItems);
                $lineItemsArray = safe_unserialize($lineItems);
            }
            $clientType = !$userId ? "new" : "";
            $newQuoteId = saveQuote("", $subject, $stage, $dateCreated, $validUntil, $clientType, $userId, App::getFromRequest("firstname"), App::getFromRequest("lastname"), App::getFromRequest("companyname"), App::getFromRequest("email"), App::getFromRequest("address1"), App::getFromRequest("address2"), App::getFromRequest("city"), App::getFromRequest("state"), App::getFromRequest("postcode"), App::getFromRequest("country"), App::getFromRequest("phonenumber"), App::getFromRequest("currency"), $lineItemsArray, App::getFromRequest("proposal"), App::getFromRequest("customernotes"), App::getFromRequest("adminnotes"), false, App::getFromRequest("tax_id"));
            $apiresults = ["result" => "success", "quoteid" => $newQuoteId];
        }
    }
}
