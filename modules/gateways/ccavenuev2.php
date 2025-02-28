<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function ccavenuev2_config()
{
    return ["FriendlyName" => ["Type" => "System", "Value" => "CCAvenue API v2"], "MerchantId" => ["FriendlyName" => "Merchant Id", "Type" => "text", "Description" => "Obtained from CCAvenue M.A.R.S Account under Settings -> API Keys"], "AccessCode" => ["FriendlyName" => "Access Code", "Type" => "password"], "WorkingKey" => ["FriendlyName" => "Working Key", "Type" => "password"], "TestMode" => ["FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Check to use CCAvenue’s Test Environment - requires a separate Test Account"]];
}

function ccavenuev2_link($params)
{
    $url = "https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction";
    if ($params["TestMode"]) {
        $url = "https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction";
    }
    $values = [];
    $values["merchant_id"] = (int) $params["MerchantId"];
    $values["order_id"] = $params["invoiceid"];
    $values["currency"] = $params["currency"];
    $values["amount"] = $params["amount"];
    $values["redirect_url"] = $params["systemurl"] . "modules/gateways/callback/ccavenuev2.php";
    $values["cancel_url"] = $params["systemurl"] . "modules/gateways/callback/ccavenuev2.php";
    $values["language"] = "EN";
    $values["billing_name"] = $params["clientdetails"]["fullname"];
    $values["billing_address"] = $params["clientdetails"]["address1"];
    $values["billing_city"] = $params["clientdetails"]["city"];
    $values["billing_state"] = $params["clientdetails"]["state"];
    $values["billing_zip"] = preg_replace("/[^a-zA-Z0-9]/", "", $params["clientdetails"]["postcode"]);
    $values["billing_country"] = $params["clientdetails"]["countryname"];
    $values["billing_tel"] = $params["clientdetails"]["phonenumber"];
    $values["billing_email"] = $params["clientdetails"]["email"];
    $data = "";
    foreach ($values as $key => $value) {
        $data .= $key . "=" . $value . "&";
    }
    try {
        $encryptedData = WHMCS\Module\Gateway\CCAvenue\CCAvenue::factory($params["WorkingKey"])->encrypt($data);
        $payNow = Lang::trans("invoicespaynow");
        return "<form action=\"" . $url . "\" id=\"ccAvenuePaymentForm\" method=\"POST\">\n    <input type=\"hidden\" name=\"encRequest\" value=\"" . $encryptedData . "\" />\n    <input type=\"hidden\" name=\"access_code\" value=\"" . $params["AccessCode"] . "\" />\n    <input type=\"submit\" value=\"" . $payNow . "\">\n</form>";
    } catch (Exception $e) {
        logTransaction("ccavenuev2", $e->getMessage(), "Error", $params);
        return "<div class=\"alert alert-danger\">An Error Occurred - Please Contact Support</div>";
    }
}
