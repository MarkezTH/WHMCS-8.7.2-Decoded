<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function protx_config()
{
    $configArray = ["FriendlyName" => ["Type" => "System", "Value" => "SagePay"], "vendorid" => ["FriendlyName" => "Vendor ID", "Type" => "text", "Size" => "20", "Description" => "Main Account Vendor ID used for First Payment"], "recurringvendorid" => ["FriendlyName" => "Recurring Vendor ID", "Type" => "text", "Size" => "20", "Description" => "Vendor ID of Continuous Authority Merchant Account used for Recurring Payments"], "testmode" => ["FriendlyName" => "Test Mode", "Type" => "yesno"]];
    return $configArray;
}

function protx_config_validate()
{
    (new WHMCS\Module\Gateway\Protx\Protx())->createTable();
}

function protx_3dsecure($params)
{
    $whmcs = DI::make("app");
    $TargetURL = "https://live.sagepay.com/gateway/service/vspdirect-register.vsp";
    if ($params["testmode"] == "on") {
        $TargetURL = "https://test.sagepay.com/gateway/service/vspdirect-register.vsp";
    }
    $data = [];
    $data["VPSProtocol"] = "4.00";
    $data["BrowserJavascriptEnabled"] = 0;
    $data["BrowserAcceptHeader"] = $_SERVER["HTTP_ACCEPT"];
    $browserLanguage = $_SERVER["HTTP_ACCEPT_LANGUAGE"];
    $browserLanguage = explode(",", $browserLanguage);
    $data["BrowserLanguage"] = $browserLanguage[0] ?: "en-US";
    $data["BrowserUserAgent"] = $_SERVER["HTTP_USER_AGENT"];
    $data["ChallengeWindowSize"] = "05";
    $data["ThreeDSNotificationURL"] = sprintf("%s/modules/gateways/callback/protxthreedsecure.php?invoiceid=%s", rtrim($params["systemurl"], "/"), $params["invoiceid"]);
    $data["TxType"] = "PAYMENT";
    $data["Vendor"] = $params["vendorid"];
    $data["VendorTxCode"] = date("YmdHis") . $params["invoiceid"];
    $data["Amount"] = $params["amount"];
    $data["Currency"] = $params["currency"];
    $data["Description"] = $params["companyname"] . " - Invoice #" . $params["invoiceid"];
    $cardType = protx_getcardtype($params["cardtype"]);
    $data["CardHolder"] = $params["clientdetails"]["fullname"];
    $data["CardType"] = $cardType;
    $data["CardNumber"] = $params["cardnum"];
    $data["ExpiryDate"] = $params["cardexp"];
    $data["COFUsage"] = "FIRST";
    $data["InitiatedType"] = "CIT";
    $data["MITType"] = "UNSCHEDULED";
    if (!empty($params["cccvv"])) {
        $data["CV2"] = $params["cccvv"];
    }
    $data["BillingSurname"] = $params["clientdetails"]["lastname"];
    $data["BillingFirstnames"] = $params["clientdetails"]["firstname"];
    $data["BillingAddress1"] = substr($params["clientdetails"]["address1"], 0, 50);
    $data["BillingAddress2"] = substr($params["clientdetails"]["address2"], 0, 50);
    $data["BillingCity"] = $params["clientdetails"]["city"];
    if ($params["clientdetails"]["country"] == "US") {
        $data["BillingState"] = $params["clientdetails"]["state"];
    }
    $data["BillingPostCode"] = $params["clientdetails"]["postcode"];
    $data["BillingCountry"] = $params["clientdetails"]["country"];
    $data["BillingPhone"] = $params["clientdetails"]["phonenumber"];
    $data["DeliverySurname"] = $params["clientdetails"]["lastname"];
    $data["DeliveryFirstnames"] = $params["clientdetails"]["firstname"];
    $data["DeliveryAddress1"] = $data["BillingAddress1"];
    $data["DeliveryAddress2"] = $data["BillingAddress2"];
    $data["DeliveryCity"] = $params["clientdetails"]["city"];
    if ($params["clientdetails"]["country"] == "US") {
        $data["DeliveryState"] = $params["clientdetails"]["state"];
    }
    $data["DeliveryPostCode"] = $params["clientdetails"]["postcode"];
    $data["DeliveryCountry"] = $params["clientdetails"]["country"];
    $data["DeliveryPhone"] = $params["clientdetails"]["phonenumber"];
    $data["CustomerEMail"] = $params["clientdetails"]["email"];
    $ipv4Address = WHMCS\Utility\Environment\CurrentRequest::getIP();
    if (filter_var($ipv4Address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $data["ClientIPAddress"] = $ipv4Address;
    }
    $response = protx_requestPost($TargetURL, $data);
    $baseStatus = $response["Status"];
    if (isset($response["SchemeTraceID"])) {
        protx_storeExtraData($params["payMethod"]->id, $response);
    }
    switch ($baseStatus) {
        case "NOTENROLLED":
        case "3DAUTH":
            logTransaction($params["paymentmethod"], $response, "3D Auth Required");
            WHMCS\Session::set("protxinvoiceid", $params["invoiceid"]);
            $termUrl = sprintf("%s/modules/gateways/callback/protxthreedsecure.php?invoiceid=%s", rtrim($params["systemurl"], "/"), $params["invoiceid"]);
            if (isset($response["CReq"])) {
                $termUrl = "";
                $challenge = "<input type=\"hidden\" name=\"creq\" value=\"" . $response["CReq"] . "\">";
                $sessionData = "<input type=\"hidden\" name=\"threeDSSessionData\" value=\"" . trim($response["VPSTxId"], "{}") . "\">";
            } else {
                $challenge = "<input type=\"hidden\" name=\"PaReq\" value=\"" . $response["PAReq"] . "\">";
                $sessionData = "<input type=\"hidden\" name=\"MD\" value=\"" . $response["MD"] . "\">";
                $termUrl = "<input type=\"hidden\" name=\"TermUrl\" value=\"" . $termUrl . "\">";
            }
            return "<form method=\"post\" action=\"" . $response["ACSURL"] . "\" name=\"paymentfrm\">\n    " . $challenge . "\n    " . $termUrl . "\n    " . $sessionData . "\n    <noscript>\n        <div class=\"errorbox\">\n            <strong>\n                JavaScript is currently disabled or is not supported by your browser.\n            </strong>\n            <br />\n            Please click the continue button to proceed with the processing of your transaction.\n        </div>\n        <p align=\"center\">\n            <input type=\"submit\" value=\"Continue >>\" />\n        </p>\n    </noscript>\n</form>";
            break;
        case "OK":
            addInvoicePayment($params["invoiceid"], $response["VPSTxId"], "", "", "protx", "on");
            logTransaction($params["paymentmethod"], $response, "Successful");
            sendMessage("Credit Card Payment Confirmation", $params["invoiceid"]);
            $result = "success";
            return $result;
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
            logTransaction($params["paymentmethod"], $response, $resultText);
            sendMessage("Credit Card Payment Failed", $params["invoiceid"]);
            $result = "declined";
            return $result;
    }
}

function protx_capture($params)
{
    $whmcs = DI::make("app");
    $TargetURL = "https://live.sagepay.com/gateway/service/vspdirect-register.vsp";
    if ($params["testmode"] == "on") {
        $TargetURL = "https://test.sagepay.com/gateway/service/vspdirect-register.vsp";
    }
    $data = [];
    $data["VPSProtocol"] = "4.00";
    $data["TxType"] = "PAYMENT";
    $data["Vendor"] = $params["recurringvendorid"];
    $data["VendorTxCode"] = date("YmdHis") . $params["invoiceid"];
    $data["Amount"] = $params["amount"];
    $data["Currency"] = $params["currency"];
    $data["Description"] = $params["companyname"] . " - Invoice #" . $params["invoiceid"];
    $cardType = protx_getcardtype($params["cardtype"]);
    $data["CardHolder"] = $params["clientdetails"]["firstname"] . " " . $params["clientdetails"]["lastname"];
    $data["CardType"] = $cardType;
    $data["CardNumber"] = $params["cardnum"];
    $data["ExpiryDate"] = $params["cardexp"];
    $gatewayData = NULL;
    if ($params["payMethod"] && $params["payMethod"]->id) {
        $gatewayData = protx_getGatewayData($params["payMethod"]->id);
    }
    $data["InitiatedType"] = $whmcs->isClientAreaRequest() ? "CIT" : "MIT";
    $data["COFUsage"] = "SUBSEQUENT";
    if (isset($gatewayData["SchemeTraceID"])) {
        $data["SchemeTraceID"] = $gatewayData["SchemeTraceID"];
    }
    if (isset($gatewayData["ACSTransID"])) {
        $data["ACSTransID"] = $gatewayData["ACSTransID"];
    }
    if (!empty($params["cccvv"])) {
        $data["CV2"] = $params["cccvv"];
    }
    if (!isset($data["CV2"]) && !isset($data["SchemeTraceID"])) {
        $data["SchemeTraceID"] = "SP999999999";
    }
    $data["MITType"] = "UNSCHEDULED";
    $data["BillingSurname"] = $params["clientdetails"]["lastname"];
    $data["BillingFirstnames"] = $params["clientdetails"]["firstname"];
    $data["BillingAddress1"] = substr($params["clientdetails"]["address1"], 0, 50);
    $data["BillingAddress2"] = substr($params["clientdetails"]["address2"], 0, 50);
    $data["BillingCity"] = $params["clientdetails"]["city"];
    if ($params["clientdetails"]["country"] == "US") {
        $data["BillingState"] = $params["clientdetails"]["state"];
    }
    $data["BillingPostCode"] = $params["clientdetails"]["postcode"];
    $data["BillingCountry"] = $params["clientdetails"]["country"];
    $data["BillingPhone"] = $params["clientdetails"]["phonenumber"];
    $data["DeliverySurname"] = $params["clientdetails"]["lastname"];
    $data["DeliveryFirstnames"] = $params["clientdetails"]["firstname"];
    $data["DeliveryAddress1"] = $params["clientdetails"]["address1"];
    $data["DeliveryAddress2"] = $params["clientdetails"]["address2"];
    $data["DeliveryCity"] = $params["clientdetails"]["city"];
    if ($params["clientdetails"]["country"] == "US") {
        $data["DeliveryState"] = $params["clientdetails"]["state"];
    }
    $data["DeliveryPostCode"] = $params["clientdetails"]["postcode"];
    $data["DeliveryCountry"] = $params["clientdetails"]["country"];
    $data["DeliveryPhone"] = $params["clientdetails"]["phonenumber"];
    $data["CustomerEMail"] = $params["clientdetails"]["email"];
    $ipAddress = $whmcs->getRemoteIp();
    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        $data["ClientIPAddress"] = $ipAddress;
    }
    $data["ApplyAVSCV2"] = "2";
    $response = protx_requestPost($TargetURL, $data);
    if (isset($response["SchemeTraceID"]) && !isset($gatewayData["SchemeTraceID"]) && isset($params["payMethod"])) {
        protx_storeExtraData($params["payMethod"]->id, $response);
    }
    $baseStatus = $response["Status"];
    $result = [];
    switch ($baseStatus) {
        case "OK":
            $result["status"] = "success";
            $result["transid"] = $response["VPSTxId"];
            break;
        case "NOTAUTHED":
            $result["status"] = "Not Authorised";
            break;
        case "REJECTED":
            $result["status"] = "Rejected";
            break;
        case "FAIL":
            $result["status"] = "Failed";
            break;
        default:
            $result["status"] = "Error";
            $result["rawdata"] = $response;
            $result["fee"] = 0;
            if ($params["cardtype"] == "Maestro") {
                invoiceDeletePayMethod($params["invoiceid"]);
            }
            return $result;
    }
}

function protx_requestPost($url, $data)
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

function protx_getcardtype($cardType)
{
    switch ($cardType) {
        case "EnRoute":
        case "Visa":
            $cardType = "VISA";
            break;
        case "MasterCard":
            $cardType = "MC";
            break;
        case "Debit MasterCard":
            $cardType = "MCDEBIT";
            break;
        case "American Express":
            $cardType = "AMEX";
            break;
        case "Diners Club":
        case "Discover":
            $cardType = "DC";
            break;
        case "JCB":
            $cardType = "JCB";
            break;
        case "Visa Debit":
            $cardType = "DELTA";
            break;
        case "Maestro":
            $cardType = "MAESTRO";
            break;
        case "Visa Electron":
            $cardType = "UKE";
            break;
        default:
            return $cardType;
    }
}

function protx_storeExtraData(int $payMethodId, $data)
{
    $gatewayData = protx_getGatewayData($payMethodId);
    if (!isset($gatewayData["SchemeTraceID"])) {
        protx_storeGatewayData($payMethodId, ["SchemeTraceID" => $data["SchemeTraceID"], "ACSTransID" => $data["ACSTransID"]]);
    }
}

function protx_storeGatewayData(int $payMethodId, $data)
{
    $gatewayData = WHMCS\Module\Gateway\Protx\Protx::where("pay_method_id", $payMethodId)->firstOrNew();
    $gatewayData->payMethodId = $payMethodId;
    $gatewayData->gatewayData = $data;
    $gatewayData->save();
}

function protx_getGatewayData(int $payMethodId)
{
    $gatewayData = WHMCS\Module\Gateway\Protx\Protx::where("pay_method_id", $payMethodId)->first();
    if ($gatewayData) {
        return $gatewayData->gatewayData;
    }
    return false;
}
