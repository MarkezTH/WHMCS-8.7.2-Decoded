<?php

require "../../../init.php";
App::load_function("gateway");
App::load_function("invoice");
$gatewayParams = getGatewayVariables("sagepaytokensv2");
if (!$gatewayParams["type"]) {
    exit("Module Not Activated");
}
if ($gatewayParams["testmode"]) {
    $url = "https://test.sagepay.com/gateway/service/direct3dcallback.vsp";
} else {
    $url = "https://live.sagepay.com/gateway/service/direct3dcallback.vsp";
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
$response = sagepaytokensv2_call($url, $postData);
$baseStatus = $response["Status"];
$responseLog = "";
foreach ($response as $key => $value) {
    $responseLog .= $key . " => " . $value . "\n";
}
$storedInvoiceId = WHMCS\Module\Storage\EncryptedTransientStorage::forModule("sagepaytokensv2")->getValue("sagePayTokensInvoiceId");
if (!is_null($storedInvoiceId)) {
    $invoiceId = $storedInvoiceId;
}
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams["paymentmethod"]);
$callbackSuccess = false;
$email = "Credit Card Payment Failed";
switch ($response["Status"]) {
    case "OK":
        checkCbTransID($response["VPSTxId"]);
        addInvoicePayment($invoiceId, $response["VPSTxId"], "", "", "sagepaytokensv2", true);
        invoiceSetPayMethodRemoteToken($invoiceId, $response["Token"]);
        $transactionStatus = "Successful";
        $email = "Credit Card Payment Confirmation";
        $callbackSuccess = true;
        break;
    case "NOTAUTHED":
        $transactionStatus = "Not Authed";
        break;
    case "REJECTED":
        $transactionStatus = "Rejected";
        break;
    case "FAIL":
        $transactionStatus = "Failed";
        break;
    default:
        $transactionStatus = "Error";
        logTransaction($gatewayParams["paymentmethod"], $response, $transactionStatus);
        sendMessage($email, $invoiceId);
        if (!$callbackSuccess) {
            invoiceDeletePayMethod($invoiceId);
        }
        callback3DSecureRedirect($invoiceId, $callbackSuccess);
}
