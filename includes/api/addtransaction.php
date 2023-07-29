<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("addTransaction")) {
    require ROOTDIR . "/includes/invoicefunctions.php";
}
$whmcs = App::self();
$allowDuplicateTransId = (bool) App::getFromRequest("allowduplicatetransid");
if (isset($invoiceid) && $invoiceid) {
    $result = select_query("tblinvoices", "id,userid", ["id" => (int) $_POST["invoiceid"]]);
    $invoiceData = mysql_fetch_array($result);
    $invoiceid = $invoiceData["id"];
    if (!$invoiceid) {
        $apiresults = ["result" => "error", "message" => "Invoice ID Not Found"];
        return NULL;
    }
    if (!$userid) {
        $userid = $invoiceData["userid"];
    }
}
if (isset($userid) && $userid) {
    $result = select_query("tblclients", "id,currency", ["id" => $userid]);
    $clientData = mysql_fetch_array($result);
    if (!$clientData["id"]) {
        $apiresults = ["result" => "error", "message" => "Client ID Not Found"];
        return NULL;
    }
    if (empty($currencyid)) {
        $currencyid = $clientData["currency"];
    }
}
if (isset($userid) && $userid && isset($invoiceid) && $invoiceid && $invoiceData["userid"] != $userid) {
    $apiresults = ["result" => "error", "message" => "User ID does not own the given Invoice ID"];
} else {
    if ($currencyid) {
        if (!WHMCS\Billing\Currency::find($currencyid)) {
            $apiresults = ["result" => "error", "message" => "Currency ID Not Found"];
            return NULL;
        }
        if (isset($userid) && $userid && $currencyid != $clientData["currency"]) {
            $apiresults = ["result" => "error", "message" => "Currency ID does not match Client currency"];
            return NULL;
        }
    } else {
        if (!$userid && !$invoiceid) {
            $apiresults = ["result" => "error", "message" => "A Currency ID is required for non-customer related transactions"];
            return NULL;
        }
    }
    if (!$paymentmethod) {
        $apiresults = ["result" => "error", "message" => "Payment Method is required"];
    } else {
        if ($transid && !$allowDuplicateTransId && !isUniqueTransactionID($transid, $paymentmethod)) {
            $apiresults = ["result" => "error", "message" => "Transaction ID must be Unique"];
        } else {
            $date = $whmcs->get_req_var("date");
            if (empty($date)) {
                $date = fromMySQLDate(date("Y-m-d H:i:s"));
            }
            addTransaction($userid ?? NULL, $currencyid, $description, $amountin, $fees ?? NULL, $amountout ?? NULL, $paymentmethod, $transid, $invoiceid ?? NULL, $date, "", $rate ?? NULL);
            if (isset($userid) && $userid && isset($credit) && $credit && empty($invoiceid)) {
                if ($transid) {
                    $description .= " (Trans ID: " . $transid . ")";
                }
                insert_query("tblcredit", ["clientid" => $userid, "date" => toMySQLDate($date), "description" => $description, "amount" => $amountin]);
                update_query("tblclients", ["credit" => "+=" . $amountin], ["id" => (int) $userid]);
            }
            if (isset($invoiceid) && 0 < $invoiceid) {
                $invoice = WHMCS\Billing\Invoice::find($invoiceid);
                if ($invoice->balance <= 0 && ($invoice->status = WHMCS\Billing\Invoice::STATUS_UNPAID)) {
                    processPaidInvoice($invoiceid, "", $date);
                } else {
                    $invoice->touch();
                }
            }
            $apiresults = ["result" => "success"];
        }
    }
}
