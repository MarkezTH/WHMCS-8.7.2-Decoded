<?php

require "../../../init.php";
$whmcs->load_function("gateway");
$whmcs->load_function("invoice");
require "../protx.php";
$GATEWAY = $params = getGatewayVariables("protx");
if (!$GATEWAY["type"]) {
    exit("Module Not Activated");
}
$url = "https://live.sagepay.com/gateway/service/direct3dcallback.vsp";
if ($params["testmode"] == "on") {
    $url = "https://test.sagepay.com/gateway/service/direct3dcallback.vsp";
}
$invoiceId = (int) App::getFromRequest("invoiceid");
$postData = [];
$postData["VPSTxId"] = App::getFromRequest("threeDSSessionData");
if (App::getFromRequest("cres")) {
    $postData["CRes"] = App::getFromRequest("cres");
} else {
    if (App::getFromRequest("PaRes")) {
        $postData["PARes"] = App::getFromRequest("PaRes");
        $postData["MD"] = App::getFromRequest("MD");
    } else {
        callback3DSecureRedirect($invoiceId, false);
    }
}
$response = protx_requestPost($url, $postData);
$baseStatus = $response["Status"];
if (!$invoiceId && WHMCS\Session::get("protxinvoiceid")) {
    $invoiceId = (int) WHMCS\Session::getAndDelete("protxinvoiceid");
}
checkCbInvoiceID($invoiceId, "protx");
$response["Invoice ID"] = $invoiceId;
if ($params["cardtype"] == "Maestro") {
    invoiceDeletePayMethod($invoiceid);
}
$callbackSuccess = false;
$email = "Credit Card Payment Failed";
switch ($response["Status"]) {
    case "OK":
        addInvoicePayment($invoiceId, $response["VPSTxId"], "", "", "protx", "on");
        $resultText = "Successful";
        $email = "Credit Card Payment Confirmation";
        $callbackSuccess = true;
        if (isset($response["SchemeTraceID"])) {
            $invoice = WHMCS\Billing\Invoice::find($invoiceId);
            if ($invoice && $invoice->payMethod) {
                protx_storeExtraData($invoice->payMethod->id, $response);
            }
        }
        break;
    case "NOTAUTHED":
        $resultText = "Not Authorised";
        break;
    case "REJECTED":
        $resultText = "Rejected";
        break;
    case "FAIL":
        $resultText = "Failed";
        break;
    default:
        $resultText = "Error";
        logTransaction($GATEWAY["paymentmethod"], $response, $resultText);
        sendMessage($email, $invoiceId);
        callback3DSecureRedirect($invoiceId, $callbackSuccess);
}
