<?php

$quoteid = App::getFromRequest("quoteid");
if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("addClient")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
if (!function_exists("updateInvoiceTotal")) {
    require ROOTDIR . "/includes/invoicefunctions.php";
}
if (!function_exists("convertQuotetoInvoice")) {
    require ROOTDIR . "/includes/quotefunctions.php";
}
$result = select_query("tblquotes", "", ["id" => $quoteid]);
$data = mysql_fetch_array($result);
$quoteid = $data["id"];
if (!$quoteid) {
    $apiresults = ["result" => "error", "message" => "Quote ID Not Found"];
} else {
    $invoiceid = convertQuotetoInvoice($quoteid);
    $apiresults = ["result" => "success", "invoiceid" => $invoiceid];
}
