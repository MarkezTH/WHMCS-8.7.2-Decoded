<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly.");
}
include_once ROOTDIR . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . "gateways" . DIRECTORY_SEPARATOR . "mollieideal" . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";
function mollieideal_config()
{
    return ["FriendlyName" => ["Type" => "System", "Value" => "iDeal via Mollie"], "apiKey" => ["FriendlyName" => "API Key", "Type" => "text", "Size" => "35", "Description" => "The API key can be found within the Mollie dashboard:  <a href=\"https://go.whmcs.com/1673/find-your-mollie-api-key\" target=\"_blank\">help.mollie.com</a>."], "customDescription" => ["FriendlyName" => "Transaction Description", "Type" => "text", "Size" => "30", "Description" => "If you leave this blank, your customers will see: <i>Your Company Name - Invoice #{invoice ID}</i>"]];
}

function mollieideal_link($params)
{
    $apiKey = $params["apiKey"];
    $description = empty($params["customDescription"]) ? $params["description"] : $params["customDescription"];
    $redirectUrl = $params["returnurl"];
    $webhookUrl = $params["systemurl"] . "modules/gateways/callback/mollieideal.php?invoiceid=" . urlencode($params["invoiceid"]) . "&amount=" . urlencode($params["amount"]) . "&fee=" . urlencode($params["fee"]);
    if (!in_array("ssl", stream_get_transports())) {
        return "<h1>Foutmelding</h1>\n<p>Uw PHP installatie heeft geen SSL ondersteuning. SSL is nodig voor de communicatie met de Mollie iDEAL API.</p>";
    }
    try {
        $mollie = new Mollie\Api\MollieApiClient();
        $mollie->setApiKey($apiKey);
    } catch (Mollie\Api\Exceptions\ApiException $e) {
        return "<p>De betaling kon niet aangemaakt worden.</p>\n<p><strong>Foutmelding:</strong> " . $e->getMessage() . "</p>";
    }
    if (isset($_POST["selectedIssuerId"]) && !empty($_POST["selectedIssuerId"])) {
        try {
            $payment = $mollie->payments->create(["amount" => ["currency" => $params["currency"], "value" => $params["amount"]], "description" => $description, "redirectUrl" => $redirectUrl, "webhookUrl" => $webhookUrl, "method" => Mollie\Api\Types\PaymentMethod::IDEAL, "issuer" => $_POST["selectedIssuerId"]]);
            header("Location: " . $payment->getCheckoutUrl(), true, 303);
            exit;
        } catch (Mollie\Api\Exceptions\ApiException $e) {
            return "<p>De betaling kon niet aangemaakt worden.</p>\n<p><strong>Foutmelding:</strong> " . $e->getMessage() . "</p>";
        }
    }
    try {
        $method = $mollie->methods->get(Mollie\Api\Types\PaymentMethod::IDEAL, ["include" => "issuers"]);
    } catch (Mollie\Api\Exceptions\ApiException $e) {
        return "<p>Er is een fout opgetreden bij het ophalen van de banklijst: " . $e->getMessage() . "</p>";
    }
    $issuerOptions = "";
    foreach ($method->issuers as $issuer) {
        $issuerOptions .= "<option value=\"" . $issuer->id . "\">" . $issuer->name . "</option>";
    }
    return "<form method=\"post\">\n    <select name=\"selectedIssuerId\">\n        <option value=\"\">Kies uw bank</option>\n        " . $issuerOptions . "\n    </select>\n    <input type=\"submit\" name=\"submit\" value=\"Betaal via iDEAL\" />\n</form>";
}
