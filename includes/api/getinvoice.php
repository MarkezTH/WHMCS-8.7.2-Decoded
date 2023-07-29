<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$invoiceid = (int) App::getFromRequest("invoiceid");
$result = select_query("tblinvoices", "", ["id" => $invoiceid]);
$data = mysql_fetch_array($result);
if (!is_array($data) || empty($data["id"])) {
    $apiresults = ["status" => "error", "message" => "Invoice ID Not Found"];
} else {
    $userid = $data["userid"];
    $invoicenum = $data["invoicenum"];
    $date = $data["date"];
    $duedate = $data["duedate"];
    $datepaid = $data["datepaid"];
    $lastCaptureAttempt = $data["last_capture_attempt"];
    $subtotal = $data["subtotal"];
    $credit = $data["credit"];
    $tax = $data["tax"];
    $tax2 = $data["tax2"];
    $total = $data["total"];
    $taxrate = $data["taxrate"];
    $taxrate2 = $data["taxrate2"];
    $status = $data["status"];
    $paymentmethod = $data["paymentmethod"];
    $notes = $data["notes"];
    $result = select_query("tblaccounts", "SUM(amountin)-SUM(amountout)", ["invoiceid" => $invoiceid]);
    $data = mysql_fetch_array($result);
    $amountpaid = $data[0];
    $balance = $total - $amountpaid;
    $balance = format_as_currency($balance);
    $gatewaytype = WHMCS\Module\GatewaySetting::getTypeFor($paymentmethod);
    $ccgateway = false;
    if ($gatewaytype === WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD) {
        $ccgateway = true;
    }
    $apiresults = ["result" => "success", "invoiceid" => $invoiceid, "invoicenum" => $invoicenum, "userid" => $userid, "date" => $date, "duedate" => $duedate, "datepaid" => $datepaid, "lastcaptureattempt" => $lastCaptureAttempt, "subtotal" => $subtotal, "credit" => $credit, "tax" => $tax, "tax2" => $tax2, "total" => $total, "balance" => $balance, "taxrate" => $taxrate, "taxrate2" => $taxrate2, "status" => $status, "paymentmethod" => $paymentmethod, "notes" => $notes, "ccgateway" => $ccgateway];
    $result = select_query("tblinvoiceitems", "", ["invoiceid" => $invoiceid]);
    while ($data = mysql_fetch_array($result)) {
        $apiresults["items"]["item"][] = ["id" => $data["id"], "type" => $data["type"], "relid" => $data["relid"], "description" => $data["description"], "amount" => $data["amount"], "taxed" => $data["taxed"]];
    }
    $apiresults["transactions"] = [];
    $transactions = WHMCS\Database\Capsule::table("tblaccounts")->where("invoiceid", $invoiceid)->orderBy("date")->get();
    foreach ($transactions as $data) {
        $apiresults["transactions"]["transaction"][] = (array) $data;
    }
    if (empty($apiresults["transactions"])) {
        $apiresults["transactions"] = "";
    }
    $responsetype = "xml";
}
