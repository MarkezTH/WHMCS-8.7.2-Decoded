<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("addInvoicePayment")) {
    require ROOTDIR . "/includes/invoicefunctions.php";
}
$whmcs = WHMCS\Application::getInstance();
$id = (int) $whmcs->get_req_var("invoiceid");
$where = ["id" => $id];
$result = select_query("tblinvoices", "id", $where);
$data = mysql_fetch_array($result);
$invoiceid = NULL;
if (is_array($data)) {
    $invoiceid = $data["id"];
}
if (!$invoiceid) {
    $apiresults = ["result" => "error", "message" => "Invoice ID Not Found"];
} else {
    $invoice = new WHMCS\Invoice($invoiceid);
    $invoiceStatus = $invoice->getData("status");
    switch ($invoiceStatus) {
        case "Cancelled":
            $apiresults = ["result" => "error", "message" => "It is not possible to add a payment to an invoice that is Cancelled"];
            break;
        case "Draft":
            $apiresults = ["result" => "error", "message" => "It is not possible to add a payment to an invoice that is a Draft"];
            break;
        default:
            $date = $whmcs->get_req_var("date");
            $userAgent = WHMCS\Http\Message\ServerRequest::fromGlobals()->getHeader("user-agent");
            $iWHMCSAgentFingerPrint = "/iWHMCS/";
            if (preg_grep($iWHMCSAgentFingerPrint, $userAgent) && strpos($date, "/") !== false) {
                $date = str_replace(["\\", "/"], ["", "-"], $date);
                $parts = explode("-", $date);
                $date = $parts[2] . "-" . $parts[1] . "-" . $parts[0];
            }
            try {
                $date = $date ? WHMCS\Carbon::parse($date) : "";
                $date2 = $whmcs->get_req_var("date2");
                if ($date2) {
                    $date = WHMCS\Carbon::parse($date2);
                }
            } catch (Throwable $e) {
                $apiresults = ["result" => "error", "message" => "Invalid Date Format - Expected: 'YYYY-MM-DD HH:mm:ss'"];
                return NULL;
            }
            $transid = $whmcs->get_req_var("transid");
            $amount = $whmcs->get_req_var("amount");
            $fees = $whmcs->get_req_var("fees");
            $gateway = $whmcs->get_req_var("gateway");
            $noemail = $whmcs->get_req_var("noemail");
            addInvoicePayment($invoiceid, $transid, $amount, $fees, $gateway, $noemail, $date);
            $apiresults = ["result" => "success"];
    }
}
