<?php

require "../../../init.php";
App::load_function("invoice");
App::load_function("gateway");
$passedInvoiceId = (int) App::getFromRequest("orderNo");
$gatewayParams = getGatewayVariables("ccavenuev2", $passedInvoiceId);
if (!$gatewayParams["type"]) {
    WHMCS\Terminus::getInstance()->doDie("Module Not Activated");
}
$encodedResponse = App::getFromRequest("encResp");
try {
    $decryptedResponse = WHMCS\Module\Gateway\CCAvenue\CCAvenue::factory($gatewayParams["WorkingKey"])->decrypt($encodedResponse);
    $returnedVariables = [];
    parse_str($decryptedResponse, $returnedVariables);
    $currency = $returnedVariables["currency"];
    $transactionId = $returnedVariables["tracking_id"];
    $amount = $returnedVariables["amount"];
    $orderStatus = $returnedVariables["order_status"];
    $invoiceId = $returnedVariables["order_id"];
    if ($invoiceId != $passedInvoiceId) {
        WHMCS\Terminus::getInstance()->doDie("Invalid Access Attempt");
    }
    $currency = WHMCS\Database\Capsule::table("tblcurrencies")->where("code", $currency)->first();
    if (!$currency) {
        logTransaction($gatewayParams["paymentmethod"], $returnedVariables, "Invalid Currency", $gatewayParams);
        WHMCS\Terminus::getInstance()->doDie("Invalid Currency");
    }
    $currency = $currency->id;
} catch (Exception $e) {
    $orderStatus = "invalid";
    $returnedVariables = ["error" => $e->getMessage()];
    $amount = $currency = $invoiceId = $transactionId = 0;
}
$shutdownHandler = new WHMCS\Module\Gateway\CCAvenueV2\ShutdownHandler(function () use($returnedVariables, $gatewayParams) {
    if (!headers_sent()) {
        logTransaction($gatewayParams["paymentmethod"], $returnedVariables, "Duplicate tracking_id", $gatewayParams);
        (new Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit(new Laminas\Diactoros\Response\HtmlResponse("Duplicate tracking_id", 500));
    }
});
(new Whoops\Util\SystemFacade())->registerShutdownFunction([$shutdownHandler, "handle"]);
checkCbTransID($transactionId);
$shutdownHandler->unregister();
strtolower($orderStatus);
switch (strtolower($orderStatus)) {
    case "success":
        logTransaction($gatewayParams["paymentmethod"], $returnedVariables, "Successful", $gatewayParams);
        $clientCurrencyId = $gatewayParams["clientdetails"]["currency"];
        $amount = convertCurrency($amount, $currency, $clientCurrencyId, 1);
        addInvoicePayment($invoiceId, $transactionId, $amount, 0, $gatewayParams["paymentmethod"]);
        callback3DSecureRedirect($invoiceId, true);
        break;
    case "failure":
        logTransaction($gatewayParams["paymentmethod"], $returnedVariables, "Failed", $gatewayParams);
        callback3DSecureRedirect($invoiceId, false);
        break;
    case "aborted":
        logTransaction($gatewayParams["paymentmethod"], $returnedVariables, "Aborted", $gatewayParams);
        callback3DSecureRedirect($invoiceId, false);
        break;
    default:
        logTransaction($gatewayParams["paymentmethod"], $returnedVariables, "Invalid", $gatewayParams);
}
