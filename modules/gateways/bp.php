<?php

function bp_config($params = [])
{
    $pairingKeyMsg = "<div class=\"alert alert-success\" style=\"margin:0;\">To link WHMCS with your BitPay account, you must login to your BitPay account and enter the following pairing code under <em>Payment Tools > Manage API Tokens</em>. Your Pairing Code is: <strong>" . WHMCS\Input\Sanitize::makeSafeForOutput($params["pairingCode"] ?? "") . "</strong></div>";
    $apiKeyReset = "<a href=\"#\" onclick=\"bitPayResetApiKey();return false\" class=\"btn btn-success btn-sm\">Generate New API Key and Pairing Code</a><script>function bitPayResetApiKey() { \$('input[name=\"field[apiKey]\"]').val('').closest('form').submit(); }</script>";
    return ["FriendlyName" => ["Type" => "System", "Value" => "BitPay"], "pairingKey" => ["FriendlyName" => "", "Type" => "html", "Description" => $pairingKeyMsg], "apiKey" => ["FriendlyName" => "API Key", "Type" => "text", "Description" => $apiKeyReset, "ReadOnly" => true], "transactionSpeed" => ["FriendlyName" => "Transaction Speed", "Type" => "dropdown", "Options" => ["low" => "Low", "medium" => "Medium", "high" => "High"], "Default" => "low", "Description" => "Choose the transaction speed you desire. <a href=\"https://docs.whmcs.com/BitPay#Transaction_Speed\" target=\"_blank\">Learn more</a>"], "testMode" => ["FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Check to use the BitPay Test System"]];
}

function bp_post_activation($params)
{
    new WHMCS\Module\Gateway\BP\BitPay($params);
}

function bp_config_post_save($params)
{
    if ($params["testMode"] != $params["existing"]["testMode"]) {
        unset($params["apiKey"]);
    }
    new WHMCS\Module\Gateway\BP\BitPay($params);
}

function bp_link($params)
{
    $bitPay = new WHMCS\Module\Gateway\BP\BitPay($params);
    $bitPayClient = $bitPay->getConnectionClient();
    $bitPayClient->setToken((new Bitpay\Token())->setToken($params["apiKey"])->setFacade("merchant"));
    $makePayment = Lang::trans("makepayment");
    $pleaseWait = Lang::trans("pleasewait");
    try {
        $invoiceId = WHMCS\TransientData::getInstance()->retrieve("BitPay" . $params["invoiceid"]);
        $invoice = NULL;
        if ($invoiceId) {
            $invoice = $bitPayClient->getInvoice($invoiceId);
            $invoiceExpiry = $invoice->getExpirationTime();
            if (!in_array($invoice->getStatus(), ["complete", "confirmed", "paid"]) && $invoiceExpiry < WHMCS\Carbon::now($invoiceExpiry->getTimezone())) {
                $invoice = NULL;
                WHMCS\TransientData::getInstance()->delete("BitPay" . $params["invoiceid"]);
            } else {
                if ($invoice->getStatus() === "complete") {
                    $bitPayClient->resendIpnNotifications($invoice->getId());
                    return "<div class=\"alert alert-info\">Payment is being approved.</div>";
                }
                if ($invoice->getStatus() === "confirmed") {
                    return "<div class=\"alert alert-info\">A payment being processed for this invoice.</div>";
                }
                if ($invoice->getStatus() === "paid") {
                    return "<div class=\"alert alert-info\">A payment is pending on this invoice.</div>";
                }
            }
        }
        if (is_null($invoice) && App::isInRequest("make_payment") && App::getFromRequest("make_payment") == 1) {
            $transactionSpeed = "low";
            if (array_key_exists("transactionSpeed", $params) && $params["transactionSpeed"]) {
                $transactionSpeed = $params["transactionSpeed"];
            }
            $invoice = new Bitpay\Invoice($transactionSpeed);
            $item = new Bitpay\Item();
            $item->setCode(NULL)->setDescription($params["description"])->setPrice($params["amount"]);
            $invoice->setItem($item);
            $invoice->setFullNotifications(true);
            $invoice->setNotificationUrl($params["systemurl"] . "modules/gateways/callback/bp.php");
            $invoice->setOrderId((string) (string) $params["invoiceid"]);
            $invoice->setRedirectUrl($params["returnurl"]);
            $invoice->setCurrency(new Bitpay\Currency($params["currency"]));
            $buyer = new Bitpay\Buyer();
            $buyer->setEmail($params["clientdetails"]["email"]);
            $buyer->setFirstName($params["clientdetails"]["firstname"]);
            $buyer->setLastName($params["clientdetails"]["lastname"]);
            $buyer->setAddress([$params["clientdetails"]["address1"], $params["clientdetails"]["address2"]]);
            $buyer->setCity($params["clientdetails"]["city"]);
            $buyer->setState($params["clientdetails"]["state"]);
            $buyer->setZip($params["clientdetails"]["postcode"]);
            $buyer->setCountry($params["clientdetails"]["country"]);
            $invoice->setBuyer($buyer);
            $invoice = $bitPayClient->createInvoice($invoice);
            WHMCS\TransientData::getInstance()->store("BitPay" . $params["invoiceid"], $invoice->getId(), 3600);
        }
        if ($invoice && App::isInRequest("make_payment") && App::getFromRequest("make_payment") == 1) {
            $enableTestMode = "";
            if ($params["testMode"]) {
                $enableTestMode = "bitpay.enableTestMode();";
            }
            $invoiceId = $invoice->getId();
            return "<script src=\"https://bitpay.com/bitpay.js\"></script>\n<script>\n    bitpay.onModalWillLeave(function() {\n        window.location='viewinvoice.php?id=" . $params["invoiceid"] . "';\n    });\n    " . $enableTestMode . "\n    bitpay.showInvoice('" . $invoiceId . "');\n</script>\n<form action=\"viewinvoice.php?id=" . $params["invoiceid"] . "\" method=\"post\">\n</form>\n" . $pleaseWait;
        }
    } catch (Exception $e) {
        logTransaction("BitPay", ["error" => $e->getMessage()], "Configuration Error");
        return "<div class=\"alert alert-error\">" . Lang::trans("errors.badRequestTryAgain") . "</div>";
    }
    return "<form action=\"viewinvoice.php?id=" . $params["invoiceid"] . "\" method=\"post\">\n<input type=\"hidden\" name=\"make_payment\" value=\"1\">\n<button type=\"submit\" class=\"btn btn-primary\">\n    " . $makePayment . "\n</button>\n</form>";
}
