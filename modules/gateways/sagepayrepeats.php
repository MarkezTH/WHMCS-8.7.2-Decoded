<?php

if (!defined("WHMCS")) {
    exit("The system cannot access this file directly.");
}

function sagepayrepeats_MetaData()
{
    return ["DisplayName" => "SagePay Repeat Payments", "TokenWorkflow" => true];
}

function sagepayrepeats_config()
{
    return ["FriendlyName" => ["Type" => "System", "Value" => "SagePay Repeat Payments"], "vendorid" => ["FriendlyName" => "Vendor ID", "Type" => "text", "Size" => "20"], "testmode" => ["FriendlyName" => "Test Mode", "Type" => "yesno"]];
}

function sagepayrepeats_3dsecure($params)
{
    $gatewayIds = explode(",", $params["gatewayid"]);
    if (count($gatewayIds) === 3 && preg_match("/^([\\d]+)({[a-z\\d\\-]+})\$/i", $gatewayIds[0], $matches)) {
        $gatewayIds[0] = $matches[2];
        array_unshift($gatewayIds, $matches[1]);
    }
    if (!$params["cardnum"]) {
        if (count($gatewayIds) == 4) {
            $results = sagepayrepeats_capture($params);
            if ($results["status"] == "success") {
                addInvoicePayment($params["invoiceid"], $results["transid"], "", "", "sagepayrepeats", true);
                logTransaction($params["paymentmethod"], $results["rawdata"], "Repeat Capture Success");
                sendMessage("Credit Card Payment Confirmation", $params["invoiceid"]);
                return "success";
            }
            logTransaction($params["paymentmethod"], $results["rawdata"], "Repeat Capture Failure");
            return "declined";
        }
        logTransaction($params["paymentmethod"], "", "Malformed Remote Token");
        return "declined";
    }
    if ($params["testmode"]) {
        $targetUrl = "https://test.sagepay.com/gateway/service/vspdirect-register.vsp";
    } else {
        $targetUrl = "https://live.sagepay.com/gateway/service/vspdirect-register.vsp";
    }
    $browserLanguage = $_SERVER["HTTP_ACCEPT_LANGUAGE"];
    $browserLanguage = explode(",", $browserLanguage);
    $transmitData = ["VPSProtocol" => "4.00", "TxType" => "PAYMENT", "BrowserJavascriptEnabled" => 0, "BrowserAcceptHeader" => $_SERVER["HTTP_ACCEPT"], "BrowserLanguage" => $browserLanguage[0] ?: "en-US", "BrowserUserAgent" => $_SERVER["HTTP_USER_AGENT"], "ChallengeWindowSize" => "05", "ThreeDSNotificationURL" => sprintf("%s/modules/gateways/callback/sagepayrepeats.php?invoiceid=%s", rtrim($params["systemurl"], "/"), $params["invoiceid"]), "Vendor" => $params["vendorid"], "VendorTxCode" => date("YmdHis") . $params["invoiceid"], "Amount" => $params["amount"], "Currency" => $params["currency"], "Description" => $params["companyname"] . " - Invoice #" . $params["invoiceid"], "CardHolder" => $params["clientdetails"]["firstname"] . " " . $params["clientdetails"]["lastname"], "CardType" => sagepayrepeats_getcardtype($params["cardtype"]), "CardNumber" => $params["cardnum"], "ExpiryDate" => $params["cardexp"], "StartDate" => $params["cardstart"], "IssueNumber" => $params["cardissuenum"], "BillingSurname" => $params["clientdetails"]["lastname"], "BillingFirstnames" => $params["clientdetails"]["firstname"], "BillingAddress1" => substr($params["clientdetails"]["address1"], 0, 50), "BillingAddress2" => substr($params["clientdetails"]["address2"], 0, 50), "BillingCity" => $params["clientdetails"]["city"], "BillingPostcode" => $params["clientdetails"]["postcode"], "BillingCountry" => $params["clientdetails"]["country"], "BillingPhone" => $params["clientdetails"]["phonenumber"], "DeliverySurname" => $params["clientdetails"]["lastname"], "DeliveryFirstnames" => $params["clientdetails"]["firstname"], "DeliveryAddress1" => substr($params["clientdetails"]["address1"], 0, 50), "DeliveryAddress2" => substr($params["clientdetails"]["address2"], 0, 50), "DeliveryCity" => $params["clientdetails"]["city"], "DeliveryPostcode" => $params["clientdetails"]["postcode"], "DeliveryCountry" => $params["clientdetails"]["country"], "DeliveryPhone" => $params["clientdetails"]["phonenumber"], "CustomerEMail" => substr($params["clientdetails"]["email"], 0, 80), "COFUsage" => "FIRST", "InitiatedType" => "CIT", "MITType" => "UNSCHEDULED"];
    if (!empty($params["cccvv"])) {
        $transmitData["CV2"] = $params["cccvv"];
    }
    if ($params["clientdetails"]["country"] == "US") {
        $transmitData["BillingState"] = $params["clientdetails"]["state"];
        $transmitData["DeliveryState"] = $params["clientdetails"]["state"];
    }
    if (filter_var(WHMCS\Utility\Environment\CurrentRequest::getIP(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $transmitData["ClientIPAddress"] = WHMCS\Utility\Environment\CurrentRequest::getIP();
    }
    $response = sagepayrepeats_requestPost($targetUrl, $transmitData);
    $baseStatus = $response["Status"];
    $responseLog = "";
    foreach ($response as $key => $value) {
        $responseLog .= $key . " => " . $value . "\n";
    }
    invoiceSetPayMethodRemoteToken($params["invoiceid"], $transmitData["VendorTxCode"]);
    switch ($baseStatus) {
        case "3DAUTH":
            logTransaction($params["paymentmethod"], $responseLog, "OK");
            WHMCS\Module\Storage\EncryptedTransientStorage::forModule("sagepayrepeats")->setValue("sagePayRepeatsInvoiceId", $params["invoiceid"]);
            if (isset($response["CReq"])) {
                $challenge = "<input type=\"hidden\" name=\"creq\" value=\"" . $response["CReq"] . "\">";
                $termUrl = "";
                $sessionData = "<input type=\"hidden\" name=\"threeDSSessionData\" value=\"" . trim($response["VPSTxId"], "{}") . "\">";
            } else {
                $challenge = "<input type=\"hidden\" name=\"PaReq\" value=\"" . $response["PAReq"] . "\">";
                $termUrl = "<input type=\"hidden\" name=\"TermUrl\" value=\"" . $transmitData["ThreeDSNotificationURL"] . "\">";
                $sessionData = "<input type=\"hidden\" name=\"MD\" value=\"" . $response["MD"] . "\">";
            }
            return "<form method=\"post\" action=\"" . $response["ACSURL"] . "\">\n    " . $challenge . "\n    " . $termUrl . "\n    " . $sessionData . "\n    <noscript>\n    <div class=\"errorbox\">\n        <b>JavaScript is currently disabled or your browser does not support it.</b><br />\n        Click Continue to proceed with processing your transaction.\n    </div>\n    <p style=\"text-align: center\"><input type=\"submit\" value=\"Continue >>\" /></p>\n    </noscript>\n</form>";
            break;
        case "OK":
            addInvoicePayment($params["invoiceid"], $response["VPSTxId"], "", "", "sagepayrepeats", true);
            invoiceSetPayMethodRemoteToken($params["invoiceid"], $transmitData["VendorTxCode"] . "," . $response["VPSTxId"] . "," . $response["SecurityKey"] . "," . $response["TxAuthNo"]);
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

function sagepayrepeats_capture($params)
{
    if ($params["testmode"]) {
        $url = "https://test.sagepay.com/gateway/service/repeat.vsp";
    } else {
        $url = "https://live.sagepay.com/gateway/service/repeat.vsp";
    }
    $gatewayId = $params["gatewayid"];
    if (!$gatewayId) {
        return ["status" => "No Repeat Details Stored", "rawdata" => ""];
    }
    $gatewayId = explode(",", $gatewayId);
    if (count($gatewayId) != 4) {
        invoiceDeletePayMethod($params["invoiceid"]);
        return ["status" => "Incomplete Remote Token", "rawdata" => implode(",", $gatewayId)];
    }
    $transmitFields = ["VPSProtocol" => "4.00", "TxType" => "REPEAT", "Vendor" => $params["vendorid"], "VendorTxCode" => date("YmdHis") . $params["invoiceid"], "Amount" => $params["amount"], "Currency" => $params["currency"], "Description" => $params["companyname"] . " - Invoice #" . $params["invoiceid"], "RelatedVendorTxCode" => $gatewayId[0], "RelatedVPSTxId" => $gatewayId[1], "RelatedSecurityKey" => $gatewayId[2], "RelatedTxAuthNo" => $gatewayId[3], "InitiatedType" => "MIT", "COFUsage" => "SUBSEQUENT", "MITType" => "UNSCHEDULED"];
    if (!empty($params["cccvv"])) {
        $transmitFields["CV2"] = $params["cccvv"];
    }
    $output = sagepayrepeats_requestPost($url, $transmitFields);
    if ($output["Status"] == "OK") {
        return ["status" => "success", "transid" => $output["VPSTxId"], "rawdata" => $output];
    }
    return ["status" => $output["Status"], "rawdata" => $output];
}

function sagepayrepeats_requestPost($url, $data)
{
    $output = [];
    try {
        $response = curlCall($url, $data, [], false, true);
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

function sagepayrepeats_getcardtype($cardType)
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
