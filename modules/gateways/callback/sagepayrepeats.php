<?php

require "../../../init.php";
App::load_function("gateway");
App::load_function("invoice");
$gatewayParams = getGatewayVariables("sagepayrepeats");
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
$response = sagepayrepeats_requestPost($url, $postData);
$baseStatus = $response["Status"];
$responseLog = "";
foreach ($response as $key => $value) {
    $responseLog .= $key . " => " . $value . "\n";
}
$storedInvoiceId = WHMCS\Module\Storage\EncryptedTransientStorage::forModule("sagepayrepeats")->getValue("sagePayRepeatsInvoiceId");
if (!is_null($storedInvoiceId)) {
    $invoiceId = $storedInvoiceId;
}
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams["paymentmethod"]);
$invoiceModel = WHMCS\Billing\Invoice::findOrFail($invoiceId);
$clientId = $invoiceModel->clientId;
$gatewayId = $invoiceModel->getPayMethodRemoteToken();
$callbackSuccess = false;
$email = "Credit Card Payment Failed";
switch ($response["Status"]) {
    case "OK":
        checkCbTransID($response["VPSTxId"]);
        try {
            $email = "Credit Card Payment Confirmation";
            $callbackSuccess = true;
            $resultStatus = "Successful";
            $invoiceModel->addPayment($invoiceModel->balance, $response["VPSTxId"], 0, "sagepayrepeats", true);
            $gatewayId .= "," . $response["VPSTxId"] . "," . $response["SecurityKey"] . "," . $response["TxAuthNo"];
            $invoiceModel->setPayMethodRemoteToken($gatewayId);
        } catch (Exception $e) {
        }
        break;
    case "NOTAUTHED":
        $resultStatus = "Not Authed";
        break;
    case "REJECTED":
        $resultStatus = "Rejected";
        break;
    case "FAIL":
        $resultStatus = "Failed";
        break;
    default:
        $resultStatus = "Error";
        if (!$callbackSuccess) {
            try {
                $invoiceModel->deletePayMethod();
            } catch (Exception $e) {
            }
        }
        sendMessage($email, $invoiceId);
        logTransaction($gatewayParams["paymentmethod"], $responseLog, $resultStatus);
        callback3DSecureRedirect($invoiceId, $callbackSuccess);
}
