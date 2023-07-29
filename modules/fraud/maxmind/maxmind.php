<?php

use WHMCS\Application\Support\Facades\Lang;

function maxmind_MetaData()
{
    return ["DisplayName" => "MaxMind", "SupportsRechecks" => true, "APIVersion" => "1.2"];
}

function maxmind_getConfigArray()
{
    return ["Enable" => ["FriendlyName" => "Enable MaxMind", "Type" => "yesno", "Description" => "Check to enable MaxMind Fraud Checking for Orders"], "userId" => ["FriendlyName" => "MaxMind User ID", "Type" => "text", "Size" => "30", "Description" => "Don't have an account? <a href=\"http://go.whmcs.com/78/maxmind\" class=\"autoLinked\">Click here to sign up &raquo;</a>"], "licenseKey" => ["FriendlyName" => "MaxMind License Key", "Type" => "text", "Size" => "30"], "serviceType" => ["FriendlyName" => "Service Type", "Default" => "Insights", "Type" => "dropdown", "Options" => implode(",", ["Score", "Insights", "Factors"]), "Description" => "Determines the level of checks that are performed. Default is <strong>Score</strong>. <a href=\"http://go.whmcs.com/1349/maxmind-compare\" class=\"autoLinked\">Learn more</a>"], "riskScore" => ["FriendlyName" => "MaxMind Fraud Risk Score", "Type" => "text", "Size" => "2", "Default" => 20, "Description" => "Higher than this value and the order will be blocked (0.01 -> 99)"], "ignoreAddressValidation" => ["FriendlyName" => "Do Not Validate Address Information", "Type" => "yesno", "Description" => "Check to ignore warnings related to address information validation failing."], "rejectFreeEmail" => ["FriendlyName" => "Reject Free Email Service", "Type" => "yesno", "Description" => "Block orders from free email addresses such as Hotmail & Yahoo!<sup>*</sup>"], "rejectCountryMismatch" => ["FriendlyName" => "Reject Country Mismatch", "Type" => "yesno", "Description" => "Block orders where order address is different from IP Location<sup>*</sup>"], "rejectAnonymousNetwork" => ["FriendlyName" => "Reject Anonymous Networks", "Type" => "yesno", "Description" => "Block orders where the user is ordering through an anonymous network<sup>*</sup>"], "rejectHighRiskCountry" => ["FriendlyName" => "Reject High Risk Country", "Type" => "yesno", "Description" => "Block orders from high risk countries<sup>*</sup>"], "customRules" => ["FriendlyName" => "Custom Rules", "Type" => "System", "Description" => "Additional rules can be created within your MaxMind account to apply automated fraud check filtering based on rules and criteria you define.<br>For more information about custom rules, visit the <a href=\"http://go.whmcs.com/1353/maxmind-custom-rules\" class=\"autoLinked\">MaxMind website</a>"], "<div class=\"pull-right\">*</div>" => ["Type" => "System", "Description" => "Only Available for Insights & Factors"]];
}

function maxmind_activate($params = [])
{
    (new WHMCS\Module\Fraud\MaxMind\Payment())->createTable();
}

function maxmind_doFraudCheck($params, $checkOnly = false)
{
    $emailDomain = explode("@", $params["clientsdetails"]["email"], 2);
    $emailDomain = isset($emailDomain[1]) ? $emailDomain[1] : "";
    $billing = [];
    $billing["first_name"] = $params["clientsdetails"]["firstname"];
    $billing["last_name"] = $params["clientsdetails"]["lastname"];
    if ($params["clientsdetails"]["companyname"]) {
        $billing["company"] = $params["clientsdetails"]["companyname"];
    }
    if ($params["clientsdetails"]["address1"]) {
        $billing["address"] = $params["clientsdetails"]["address1"];
    }
    if ($params["clientsdetails"]["city"]) {
        $billing["city"] = $params["clientsdetails"]["city"];
    }
    if ($params["clientsdetails"]["state"]) {
        $billing["region"] = $params["clientsdetails"]["state"];
    }
    if ($params["clientsdetails"]["postcode"]) {
        $billing["postal"] = $params["clientsdetails"]["postcode"];
    }
    $billing["country"] = $params["clientsdetails"]["country"];
    $phoneCountryCode = $params["clientsdetails"]["phonecc"];
    $phoneNumber = $params["clientsdetails"]["phonenumber"];
    if ($phoneNumber) {
        $billing["phone_number"] = $phoneNumber;
        if ($phoneCountryCode) {
            $billing["phone_country_code"] = $phoneCountryCode;
        }
    }
    $model = $params["clientsdetails"]["model"];
    if ($model instanceof WHMCS\User\Client) {
        $currencyCode = $model->currencyrel->code;
    } else {
        $currencyCode = $model->client->currencyrel->code;
    }
    $request = ["device" => ["ip_address" => $params["ip"]], "event" => ["transaction_id" => $params["order"]["order_number"], "type" => "purchase"], "account" => ["user_id" => $params["clientsdetails"]["userid"], "username_md5" => md5($params["clientsdetails"]["userid"])], "email" => ["address" => $params["clientsdetails"]["email"], "domain" => $emailDomain], "billing" => $billing, "payment" => ["processor" => WHMCS\Module\Fraud\MaxMind\Payment::getPaymentModule($params["order"]["payment_method"])], "order" => ["amount" => $params["order"]["amount"], "currency" => $currencyCode, "discount_code" => $params["order"]["promo_code"]]];
    $ccEncryptionHash = App::get_hash();
    $ccHash = md5($ccEncryptionHash . $params["clientsdetails"]["userid"]);
    $cardNumber = get_query_val("tblclients", "AES_DECRYPT(cardnum,'" . $ccHash . "') as cardnum", ["id" => $params["clientsdetails"]["userid"]]);
    if ($cardNumber) {
        $cardDetails = ["issuer_id_number" => substr($cardNumber, 0, 6), "last_4_digits" => substr($cardNumber, -4), "token" => $params["clientsdetails"]["userid"] . generateFriendlyPassword(16)];
        $request["credit_card"] = $cardDetails;
    }
    if (array_key_exists("sessionId", $params) && $params["sessionId"]) {
        $request["device"]["session_id"] = $params["sessionId"];
    }
    if (array_key_exists("userAgent", $params) && $params["userAgent"]) {
        $request["device"]["user_agent"] = $params["userAgent"];
    }
    if (array_key_exists("acceptLanguage", $params) && $params["acceptLanguage"]) {
        $request["device"]["accept_language"] = $params["acceptLanguage"];
    }
    $errorResponse = NULL;
    try {
        $response = (new WHMCS\Module\Fraud\MaxMind\Request())->setAccountId($params["userId"])->setLicenseKey($params["licenseKey"])->setServiceType($params["serviceType"])->call($request);
        if ($response->isSuccessful()) {
            if (!$checkOnly) {
                (new WHMCS\Module\Fraud\MaxMind\Maxmind())->validateRules($params, $response);
            }
        } else {
            $errorCode = $response->get("code");
            $error = $response->get("error");
            logActivity("MaxMind Fraud Check - Error Occurred: " . $errorCode . " - " . $error);
            switch ($errorCode) {
                case "IP_ADDRESS_INVALID":
                case "IP_ADDRESS_REQUIRED":
                case "IP_ADDRESS_RESERVED":
                case "JSON_INVALID":
                case "AUTHORIZATION_INVALID":
                case "LICENSE_KEY_REQUIRED":
                case "USER_ID_REQUIRED":
                case "INSUFFICIENT_FUNDS":
                case "PERMISSION_REQUIRED":
                default:
                    $errorResponse = Lang::trans("maxmind_checkconfiguration");
            }
        }
    } catch (WHMCS\Exception\Fraud\FraudCheckException $e) {
        $errorResponse = $e->getMessage();
    } catch (WHMCS\Exception\Http\ConnectionError $e) {
        logActivity("MaxMind Fraud Check - Connection Error: " . $e->getMessage());
        $errorResponse = Lang::trans("maxmind_checkconfiguration");
    } catch (Exception $e) {
        logActivity("MaxMind Fraud Check - General Error: " . $e->getMessage());
        $errorResponse = Lang::trans("maxmind_checkconfiguration");
    }
    $returnData = [];
    if (!empty($response) && $response instanceof WHMCS\Module\Fraud\MaxMind\Response) {
        $returnData["data"] = $response->toArray();
        $httpResponseCode = $response->getHttpCode();
        if (401 <= $httpResponseCode && $httpResponseCode < 500) {
            $errorResponse = NULL;
        }
    }
    if (!is_null($errorResponse)) {
        $returnData["error"] = ["title" => Lang::trans("maxmind_title") . " " . Lang::trans("maxmind_error"), "description" => $errorResponse];
    }
    return $returnData;
}

function maxmind_processResultsForDisplay($params)
{
    $maxMindInterface = new WHMCS\Module\Fraud\MaxMind\Maxmind();
    $response = new WHMCS\Module\Fraud\MaxMind\Response($params["data"]);
    if ($response->isEmpty()) {
        $response = $maxMindInterface->legacyResultsFormatHandler($params["data"]);
        if (count($response) !== 0) {
            return $response;
        }
        $response = new WHMCS\Module\Fraud\MaxMind\Response(json_encode(["code" => 500, "error" => "Invalid MaxMind API Response: " . $params["data"]]), 500);
    }
    return $maxMindInterface->formatResponse($response);
}
