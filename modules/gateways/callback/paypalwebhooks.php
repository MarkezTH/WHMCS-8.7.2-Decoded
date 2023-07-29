<?php

require "../../../init.php";
$whmcs->load_function("gateway");
$whmcs->load_function("invoice");
$GATEWAY = getGatewayVariables("paypalcheckout");
if (!$GATEWAY["type"]) {
    exit("Module Not Activated");
}
$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();
$rawPayload = $request->getContent();
try {
    if (empty($rawPayload)) {
        throw new Exception("No data received");
    }
    $payload = json_decode($rawPayload);
    if (!is_object($payload) || json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid data received");
    }
    $expectedProperties = ["resource_type" => "Resource type missing", "event_type" => "Event type missing", "event_version" => "Event version missing"];
    foreach ($expectedProperties as $property => $error) {
        if (!property_exists($payload, $property)) {
            throw new Exception($error);
        }
    }
    $gatewaySettings = WHMCS\Module\GatewaySetting::getForGateway("paypalcheckout");
    $signatureVerification = true;
    if (isset($gatewaySettings["SignatureVerification"]) && $gatewaySettings["SignatureVerification"] == "disable") {
        $signatureVerification = false;
    }
    if ($signatureVerification) {
        $expectedHeaders = ["PAYPAL-TRANSMISSION-SIG", "PAYPAL-TRANSMISSION-TIME", "PAYPAL-TRANSMISSION-ID", "PAYPAL-AUTH-ALGO", "PAYPAL-CERT-URL"];
        foreach ($expectedHeaders as $header) {
            if (!$request->headers->has($header)) {
                throw new Exception("Signature data missing");
            }
        }
        $webhookId = !empty($gatewaySettings["sandbox"]) ? WHMCS\Config\Setting::getValue("PayPalCheckoutSandboxWebhookId") : WHMCS\Config\Setting::getValue("PayPalCheckoutWebhookId");
        if (!(new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi())->verifyWebhookSignature($request->headers->get("PAYPAL-AUTH-ALGO"), $request->headers->get("PAYPAL-CERT-URL"), $request->headers->get("PAYPAL-TRANSMISSION-ID"), $request->headers->get("PAYPAL-TRANSMISSION-SIG"), $request->headers->get("PAYPAL-TRANSMISSION-TIME"), $webhookId, $payload)) {
            throw new Exception("Signature Verification Failed");
        }
    }
    try {
        $responseMsg = (new WHMCS\Module\Gateway\Paypalcheckout\PayPalWebhookHandler())->setFriendlyName($gatewaySettings["name"] ?? "")->execute(json_decode($rawPayload, true));
        logTransaction("PayPal Webhook", $payload, $responseMsg);
    } catch (Exception $e) {
        logTransaction("PayPal Webhook", $payload, $e->getMessage());
    }
} catch (Exception $e) {
    logTransaction("PayPal Webhook", empty($payload) ? $rawPayload : $payload, $e->getMessage());
    header("HTTP/1.0 406 Not Acceptable");
    exit;
}
