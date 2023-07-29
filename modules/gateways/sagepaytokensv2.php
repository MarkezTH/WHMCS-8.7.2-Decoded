<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function sagepaytokensv2_MetaData()
{
    return ["DisplayName" => "SagePay Tokens v2", "TokenWorkflow" => true];
}

function sagepaytokensv2_config()
{
    $configarray = ["FriendlyName" => ["Type" => "System", "Value" => "SagePay Tokens v2"], "vendorid" => ["FriendlyName" => "Vendor ID", "Type" => "text", "Size" => "20"], "testmode" => ["FriendlyName" => "Test Mode", "Type" => "yesno"]];
    return $configarray;
}

function sagepaytokensv2_3dsecure($params)
{
    $subdomain = $params["testmode"] ? "test" : "live";
    $url = "https://" . $subdomain . ".sagepay.com/gateway/service/vspdirect-register.vsp";
    if (!$params["cardnum"]) {
        if (!empty($params["gatewayid"])) {
            $results = sagepaytokensv2_capture($params);
            if ($results["status"] == "success") {
                addInvoicePayment($params["invoiceid"], $results["transid"], "", "", "sagepaytokensv2", true);
                logTransaction($params["paymentmethod"], $results["rawdata"], "Successful");
                sendMessage("Credit Card Payment Confirmation", $params["invoiceid"]);
                return "success";
            }
            logTransaction($params["paymentmethod"], $results["rawdata"], "Capture Failed");
            return "declined";
        }
        logTransaction($params["paymentmethod"], "", "Malformed Remote Token");
        return "declined";
    }
    $browserLanguage = explode(",", $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
    $transmitData = ["VPSProtocol" => "4.00", "TxType" => "PAYMENT", "BrowserJavascriptEnabled" => 0, "BrowserAcceptHeader" => $_SERVER["HTTP_ACCEPT"], "BrowserLanguage" => $browserLanguage[0] ?: "en-US", "BrowserUserAgent" => $_SERVER["HTTP_USER_AGENT"], "ChallengeWindowSize" => "05", "ThreeDSNotificationURL" => sprintf("%s/modules/gateways/callback/sagepaytokensv2.php?invoiceid=%s", rtrim($params["systemurl"], "/"), $params["invoiceid"]), "Vendor" => $params["vendorid"], "VendorTxCode" => $params["invoiceid"] . "-" . date("YmdHis"), "Amount" => $params["amount"], "Currency" => $params["currency"], "Description" => $params["companyname"] . " - Invoice #" . $params["invoiceid"], "CardHolder" => substr($params["clientdetails"]["firstname"] . " " . $params["clientdetails"]["lastname"], 0, 45), "CardNumber" => $params["cardnum"], "ExpiryDate" => $params["cardexp"], "CardType" => sagepaytokensv2_getcardtype($params["cardtype"]), "StoreToken" => "1", "CreateToken" => "1", "BillingSurname" => $params["clientdetails"]["lastname"], "BillingFirstnames" => $params["clientdetails"]["firstname"], "BillingAddress1" => substr($params["clientdetails"]["address1"], 0, 50), "BillingAddress2" => substr($params["clientdetails"]["address2"], 0, 50), "BillingCity" => $params["clientdetails"]["city"], "BillingPostCode" => $params["clientdetails"]["postcode"], "BillingCountry" => $params["clientdetails"]["country"], "BillingPhone" => $params["clientdetails"]["phonenumber"], "DeliverySurname" => $params["clientdetails"]["lastname"], "DeliveryFirstnames" => $params["clientdetails"]["firstname"], "DeliveryAddress1" => substr($params["clientdetails"]["address1"], 0, 50), "DeliveryAddress2" => substr($params["clientdetails"]["address2"], 0, 50), "DeliveryCity" => $params["clientdetails"]["city"], "DeliveryPostcode" => $params["clientdetails"]["postcode"], "DeliveryCountry" => $params["clientdetails"]["country"], "DeliveryPhone" => $params["clientdetails"]["phonenumber"], "CustomerEMail" => substr($params["clientdetails"]["email"], 0, 80), "COFUsage" => "FIRST", "InitiatedType" => "CIT", "Apply3DSecure" => "1", "MITType" => "UNSCHEDULED"];
    if ($params["clientdetails"]["country"] == "US") {
        $transmitData["BillingState"] = $params["clientdetails"]["state"];
        $transmitData["DeliveryState"] = $params["clientdetails"]["state"];
    }
    if ($params["cccvv"]) {
        $transmitData["CV2"] = $params["cccvv"];
    }
    if (filter_var(WHMCS\Utility\Environment\CurrentRequest::getIP(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $transmitData["ClientIPAddress"] = WHMCS\Utility\Environment\CurrentRequest::getIP();
    }
    $response = sagepaytokensv2_call($url, $transmitData);
    $baseStatus = $response["Status"];
    $responseLog = "";
    foreach ($response as $key => $value) {
        $responseLog .= $key . " => " . $value . "\n";
    }
    switch ($baseStatus) {
        case "3DAUTH":
            logTransaction($params["paymentmethod"], $responseLog, "OK");
            WHMCS\Module\Storage\EncryptedTransientStorage::forModule("sagepaytokensv2")->setValue("sagePayTokensInvoiceId", $params["invoiceid"]);
            if (isset($response["CReq"])) {
                $challenge = "<input type=\"hidden\" name=\"creq\" value=\"" . $response["CReq"] . "\">";
                $sessionData = "<input type=\"hidden\" name=\"threeDSSessionData\" value=\"" . trim($response["VPSTxId"], "{}") . "\">";
                $termUrl = "";
            } else {
                $challenge = "<input type=\"hidden\" name=\"PaReq\" value=\"" . $response["PAReq"] . "\">";
                $sessionData = "<input type=\"hidden\" name=\"MD\" value=\"" . $response["MD"] . "\">";
                $termUrl = "<input type=\"hidden\" name=\"TermUrl\" value=\"" . $transmitData["ThreeDSNotificationURL"] . "\">";
            }
            return "<form method=\"post\" action=\"" . $response["ACSURL"] . "\">\n    " . $challenge . "\n    " . $termUrl . "\n    " . $sessionData . "\n    <noscript>\n    <div class=\"errorbox\">\n        <b>JavaScript is currently disabled or is not supported by your browser.</b><br />\n        Please click the continue button to proceed with the processing of your transaction.\n    </div>\n    <p style=\"text-align: center;\"><input type=\"submit\" value=\"Continue >>\" /></p>\n    </noscript>\n</form>";
            break;
        case "OK":
            addInvoicePayment($params["invoiceid"], $response["VPSTxId"], "", "", "sagepaytokensv2", true);
            invoiceSetPayMethodRemoteToken($params["invoiceid"], $response["Token"]);
            logTransaction($params["paymentmethod"], $responseLog, "Successful");
            sendMessage("Credit Card Payment Confirmation", $params["invoiceid"]);
            return "success";
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
            logTransaction($params["paymentmethod"], $responseLog, $transactionStatus);
            sendMessage("Credit Card Payment Failed", $params["invoiceid"]);
            return "declined";
    }
}

function sagepaytokensv2_capture($params)
{
    $subdomain = $params["testmode"] ? "test" : "live";
    $url = "https://" . $subdomain . ".sagepay.com/gateway/service/vspdirect-register.vsp";
    $gatewayId = $params["gatewayid"];
    if (!$gatewayId) {
        return ["status" => "No Token Stored", "rawdata" => ""];
    }
    $browserLanguage = explode(",", $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
    $transmitData = ["VPSProtocol" => "4.00", "TxType" => "PAYMENT", "BrowserJavascriptEnabled" => 0, "BrowserAcceptHeader" => $_SERVER["HTTP_ACCEPT"], "BrowserLanguage" => $browserLanguage[0] ?: "en-US", "BrowserUserAgent" => $_SERVER["HTTP_USER_AGENT"], "ChallengeWindowSize" => "05", "ThreeDSNotificationURL" => sprintf("%s/modules/gateways/callback/sagepaytokensv2.php?invoiceid=%s", rtrim($params["systemurl"], "/"), $params["invoiceid"]), "Vendor" => $params["vendorid"], "VendorTxCode" => $params["invoiceid"] . "-" . date("YmdHis"), "Amount" => $params["amount"], "Currency" => $params["currency"], "Description" => $params["companyname"] . " - Invoice #" . $params["invoiceid"], "CardHolder" => substr($params["clientdetails"]["firstname"] . " " . $params["clientdetails"]["lastname"], 0, 45), "Token" => $params["gatewayid"], "StoreToken" => "1", "BillingSurname" => $params["clientdetails"]["lastname"], "BillingFirstnames" => $params["clientdetails"]["firstname"], "BillingAddress1" => substr($params["clientdetails"]["address1"], 0, 50), "BillingAddress2" => substr($params["clientdetails"]["address2"], 0, 50), "BillingCity" => $params["clientdetails"]["city"], "BillingPostCode" => $params["clientdetails"]["postcode"], "BillingCountry" => $params["clientdetails"]["country"], "BillingPhone" => $params["clientdetails"]["phonenumber"], "DeliverySurname" => $params["clientdetails"]["lastname"], "DeliveryFirstnames" => $params["clientdetails"]["firstname"], "DeliveryAddress1" => substr($params["clientdetails"]["address1"], 0, 50), "DeliveryAddress2" => substr($params["clientdetails"]["address2"], 0, 50), "DeliveryCity" => $params["clientdetails"]["city"], "DeliveryPostcode" => $params["clientdetails"]["postcode"], "DeliveryCountry" => $params["clientdetails"]["country"], "DeliveryPhone" => $params["clientdetails"]["phonenumber"], "CustomerEMail" => substr($params["clientdetails"]["email"], 0, 80), "COFUsage" => "SUBSEQUENT", "InitiatedType" => "MIT", "MITType" => "UNSCHEDULED"];
    if ($params["clientdetails"]["country"] == "US") {
        $transmitData["BillingState"] = $params["clientdetails"]["state"];
        $transmitData["DeliveryState"] = $params["clientdetails"]["state"];
    }
    if (filter_var(WHMCS\Utility\Environment\CurrentRequest::getIP(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $transmitData["ClientIPAddress"] = WHMCS\Utility\Environment\CurrentRequest::getIP();
    }
    $results = sagepaytokensv2_call($url, $transmitData);
    if ($results["Status"] == "OK") {
        return ["status" => "success", "rawdata" => $results, "transid" => $results["VPSTxId"]];
    }
    return ["status" => "error", "rawdata" => $results];
}

function sagepaytokensv2_call($url, $fields)
{
    $output = [];
    try {
        $response = curlCall($url, $fields, [], false, true);
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            $line = explode("=", $line, 2);
            $output[trim($line[0])] = trim($line[1]);
        }
    } catch (Exception $e) {
        $output["Status"] = "FAIL";
        $output["StatusDetail"] = $e->getMessage();
    }
    return $output;
}

function sagepaytokensv2_getcardtype($cardType)
{
    switch ($cardType) {
        case "Visa":
        case "Visa Debit":
        case "EnRoute":
            $cardType = "VISA";
            break;
        case "MasterCard":
            $cardType = "MC";
            break;
        case "American Express":
            $cardType = "AMEX";
            break;
        case "Diners Club":
        case "Discover":
            $cardType = "DC";
            break;
        case "Delta":
        case "Visa Delta":
            $cardType = "DELTA";
            break;
        case "Solo":
            $cardType = "SOLO";
            break;
        case "Switch":
            $cardType = "SWITCH";
            break;
        case "Maestro":
            $cardType = "MAESTRO";
            break;
        case "Electron":
        case "Visa Electron":
            $cardType = "UKE";
            break;
        default:
            return $cardType;
    }
}
