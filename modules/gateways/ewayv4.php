<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!defined("EWAY_TOKENS_PARTNER_ID")) {
    define("EWAY_TOKENS_PARTNER_ID", "311f3706123f4a93bc92841cd3b9e970");
}

function ewayv4_MetaData()
{
    return ["DisplayName" => "eWAY", "APIVersion" => "1.1"];
}

function ewayv4_config()
{
    return ["FriendlyName" => ["Type" => "System", "Value" => "eWAY"], "apiKey" => ["FriendlyName" => "API Key", "Type" => "text", "Size" => 20], "apiPass" => ["FriendlyName" => "API Password", "Type" => "password", "Size" => 20], "publicApiKey" => ["FriendlyName" => "eWAY Public API Key", "Type" => "text", "Size" => 20], "testmode" => ["FriendlyName" => "Test Mode", "Type" => "yesno"]];
}

function ewayv4_credit_card_input($params)
{
    $js = (new WHMCS\Module\Gateway\EwayV4\SecureFieldsJsClient())->setLanguage(["creditCardName" => addslashes(Lang::trans("creditCardHolderName")), "creditCardInput" => addslashes(Lang::trans("creditcardcardnumber")), "creditCardExpiry" => addslashes(Lang::trans("creditcardcardexpires")), "creditCardCvc" => addslashes(Lang::trans("creditcardcvvnumbershort")), "newCardInformation" => addslashes(Lang::trans("creditcardenternewcard")), "or" => addslashes(Lang::trans("or"))])->setPublicApiKey($params["publicApiKey"]);
    $renderFunction = "render";
    if (defined("ADMINAREA")) {
        $js->addLanguageKey("creditCardName", AdminLang::trans("fields.cardName"));
        $renderFunction = "renderAdmin";
    }
    return $js->{$renderFunction}();
}

function ewayv4_cc_validation($params)
{
    if (App::isInRequest("remoteStorageToken")) {
        WHMCS\Session::set("remoteStorageToken", (string) App::getFromRequest("remoteStorageToken"));
    }
}

function ewayv4_storeremote($params)
{
    $sandbox = "";
    if ($params["testmode"]) {
        $sandbox = ".sandbox";
    }
    $url = "https://api" . $sandbox . ".ewaypayments.com/Transaction";
    switch ($params["action"]) {
        case "create":
            $remoteStorageToken = WHMCS\Session::getAndDelete("remoteStorageToken");
            if (!$remoteStorageToken) {
                $remoteStorageToken = App::getFromRequest("remoteStorageToken");
            }
            $customer = [];
            $customer["Reference"] = $params["clientdetails"]["id"];
            $customer["Title"] = "";
            $customer["FirstName"] = $params["clientdetails"]["firstname"];
            $customer["LastName"] = $params["clientdetails"]["lastname"];
            if ($params["clientdetails"]["company"]) {
                $customer["CompanyName"] = $params["clientdetails"]["company"];
            }
            $customer["Street1"] = $params["clientdetails"]["address1"];
            if ($params["clientdetails"]["address2"]) {
                $customer["Street2"] = $params["clientdetails"]["address2"];
            }
            $customer["City"] = $params["clientdetails"]["city"];
            $customer["State"] = $params["clientdetails"]["state"];
            $customer["PostalCode"] = $params["clientdetails"]["postcode"];
            $customer["Email"] = $params["clientdetails"]["email"];
            $customer["Phone"] = $params["clientdetails"]["phonenumber"];
            $customer["Country"] = $params["clientdetails"]["country"];
            $data = ["Method" => "CreateTokenCustomer", "RedirectUrl" => App::getSystemURL(), "CancelUrl" => App::getSystemURL(), "TransactionType" => "Purchase", "PartnerID" => EWAY_TOKENS_PARTNER_ID, "Customer" => $customer, "Payment" => ["TotalAmount" => 0], "SecuredCardData" => $remoteStorageToken];
            $response = curlCall($url, json_encode($data), ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
            $response = json_decode($response, true);
            if ($response["ResponseCode"] == "00") {
                $cardDetails = $response["Customer"]["CardDetails"];
                $cardNumber = $cardDetails["Number"];
                $cardNumber = preg_replace("/[^0-9]/", "0", $cardNumber);
                $cardLastFour = substr($cardDetails["Number"], -4);
                $cardExpiry = $cardDetails["ExpiryMonth"] . "" . $cardDetails["ExpiryYear"];
                $cardType = getCardTypeByCardNumber($cardNumber);
                return ["cardnumber" => $cardNumber, "cardlastfour" => $cardLastFour, "cardexpiry" => $cardExpiry, "cardtype" => $cardType, "gatewayid" => $response["Customer"]["TokenCustomerID"], "status" => "success"];
            }
            $error = !empty($response["Payment"]) && !empty($response["Payment"]["Errors"]) ? $response["Payment"]["Errors"] : $response["Errors"];
            return ["status" => "error", "rawdata" => ["error" => $error]];
            break;
        case "delete":
            return ["status" => "success"];
            break;
        case "update":
            $response = curlCall("https://api" . $sandbox . ".ewaypayments.com/Customer/" . $params["remoteStorageToken"], [], ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
            $response = json_decode($response, true);
            if (is_null($response["Errors"])) {
                $customer = $response["Customers"][0];
                $customer["CardDetails"]["ExpiryMonth"] = $params["cardExpiryMonth"];
                $customer["CardDetails"]["ExpiryYear"] = substr($params["cardExpiryYear"], -2);
                $data = ["Method" => "UpdateTokenCustomer", "RedirectUrl" => App::getSystemURL(), "CancelUrl" => App::getSystemURL(), "TransactionType" => "Recurring", "PartnerID" => EWAY_TOKENS_PARTNER_ID, "Customer" => $customer, "Payment" => ["TotalAmount" => 0]];
                $response = curlCall($url, json_encode($data), ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
                $response = json_decode($response, true);
                if ($response["ResponseCode"] == "00") {
                    return ["gatewayid" => $response["Customer"]["TokenCustomerID"], "status" => "success"];
                }
                return ["status" => "error", "rawdata" => ["error" => $response["Errors"], "response" => $response]];
            }
            break;
        default:
            return ["status" => "error", "rawdata" => "Invalid Action Request"];
    }
}

function ewayv4_capture($params)
{
    if (!$params["gatewayid"]) {
        return ["status" => "failed", "rawdata" => "No Remote Card Stored for this Client"];
    }
    $whmcs = App::self();
    $sandbox = "";
    if ($params["testmode"]) {
        $sandbox = ".sandbox";
    }
    $url = "https://api" . $sandbox . ".ewaypayments.com/Transaction";
    try {
        $payment = [];
        $payment["InvoiceNumber"] = $params["invoiceid"];
        $payment["InvoiceDescription"] = "Invoice #" . $params["invoiceid"];
        $payment["InvoiceReference"] = $params["invoiceid"];
        $payment["TotalAmount"] = round($params["amount"] * 100);
        $payment["CurrencyCode"] = $params["currency"];
        $parameters = [];
        $parameters["Method"] = "TokenPayment";
        $parameters["RedirectUrl"] = $params["systemurl"];
        $parameters["CancelUrl"] = $params["returnurl"] . "&paymentfailed=true";
        $parameters["CustomerIP"] = $whmcs->getRemoteIp();
        $parameters["TransactionType"] = "Recurring";
        $parameters["Payment"] = $payment;
        $parameters["Customer"] = ["TokenCustomerID" => $params["gatewayid"]];
        $parameters["PartnerID"] = EWAY_TOKENS_PARTNER_ID;
        $payment = curlCall($url, json_encode($parameters), ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
        $payment = json_decode($payment, true);
        if ($payment["TransactionStatus"]) {
            return ["status" => "success", "transid" => $payment["TransactionID"], "rawdata" => $payment];
        }
        return ["status" => "declined", "rawdata" => $payment];
    } catch (Exception $e) {
        return ["status" => "error", "rawdata" => $e->getMessage()];
    }
}

function ewayv4_refund($params)
{
    $sandbox = "";
    if ($params["testmode"]) {
        $sandbox = ".sandbox";
    }
    $url = "https://api" . $sandbox . ".ewaypayments.com/Transaction/" . $params["transid"] . "/Refund";
    $parameters = [];
    $parameters["PartnerID"] = EWAY_TOKENS_PARTNER_ID;
    $refund = [];
    $refund["TotalAmount"] = round($params["amount"] * 100);
    $refund["CurrencyCode"] = $params["currency"];
    $parameters["Refund"] = $refund;
    $refund = curlCall($url, json_encode($parameters), ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
    $refund = json_decode($refund, true);
    if ($refund["TransactionStatus"]) {
        return ["status" => "success", "transid" => $refund["TransactionID"], "rawdata" => $refund];
    }
    return ["status" => "declined", "rawdata" => $refund];
}

function ewayv4_adminstatusmsg($params)
{
    $gatewayId = $params["gatewayid"];
    if ($gatewayId) {
        return ["type" => "info", "title" => "eWay Remote Token", "msg" => "This customer has an eWay Token storing their card details for automated recurring billing with ID " . $gatewayId];
    }
    return [];
}
