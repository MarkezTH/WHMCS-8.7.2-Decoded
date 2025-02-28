<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function bluepayremote_config()
{
    $configarray = ["FriendlyName" => ["Type" => "System", "Value" => "BluePay Remote"], "bpaccountid" => ["FriendlyName" => "Account ID", "Type" => "text", "Size" => "20"], "bpuserid" => ["FriendlyName" => "User ID", "Type" => "text", "Size" => "20"], "bpsecretkey" => ["FriendlyName" => "Secret Key", "Type" => "text", "Size" => "30"], "testmode" => ["FriendlyName" => "Test Module", "Type" => "yesno"]];
    return $configarray;
}

function bluepayremote_capture($params)
{
    $url = "https://secure.bluepay.com/interfaces/bp20post";
    $postfields = [];
    $postfields["ACCOUNT_ID"] = $params["bpaccountid"];
    $postfields["USER_ID"] = $params["bpuserid"];
    $postfields["TRANS_TYPE"] = "SALE";
    $postfields["PAYMENT_TYPE"] = "CREDIT";
    $postfields["MODE"] = $params["testmode"] ? "TEST" : "LIVE";
    $postfields["AMOUNT"] = $params["amount"];
    $postfields["INVOICE_ID"] = $params["invoiceid"];
    $postfields["NAME1"] = $params["clientdetails"]["firstname"];
    $postfields["NAME2"] = $params["clientdetails"]["lastname"];
    $postfields["COMPANY_NAME"] = $params["clientdetails"]["companyname"];
    $postfields["ADDR1"] = $params["clientdetails"]["address1"];
    $postfields["ADDR2"] = $params["clientdetails"]["address2"];
    $postfields["CITY"] = $params["clientdetails"]["city"];
    $postfields["STATE"] = $params["clientdetails"]["state"];
    $postfields["ZIP"] = $params["clientdetails"]["postcode"];
    $postfields["COUNTRY"] = $params["clientdetails"]["country"];
    $postfields["PHONE"] = $params["clientdetails"]["phonenumber"];
    $postfields["EMAIL"] = $params["clientdetails"]["email"];
    if ($params["gatewayid"] && !$params["cardnum"]) {
        $postfields["MASTER_ID"] = $params["gatewayid"];
        $postfields["TAMPER_PROOF_SEAL"] = md5($params["bpsecretkey"] . $params["bpaccountid"] . $postfields["TRANS_TYPE"] . $postfields["AMOUNT"] . $postfields["MASTER_ID"] . $postfields["NAME1"] . $postfields["PAYMENT_ACCOUNT"]);
        $data = curlCall($url, $postfields);
        $result = explode("&", $data);
        foreach ($result as $res) {
            $res = explode("=", $res);
            $resultarray[$res[0]] = $res[1];
        }
        if ($resultarray["STATUS"] == "1") {
            return ["status" => "success", "transid" => $resultarray["TRANS_ID"], "rawdata" => $resultarray];
        }
        return ["status" => "error", "rawdata" => $resultarray];
    } else {
        $postfields["PAYMENT_ACCOUNT"] = $params["cardnum"];
        $postfields["CARD_CVV2"] = $params["cccvv"];
        $postfields["CARD_EXPIRE"] = $params["cardexp"];
        $postfields["TAMPER_PROOF_SEAL"] = md5($params["bpsecretkey"] . $params["bpaccountid"] . $postfields["TRANS_TYPE"] . $postfields["AMOUNT"] . $postfields["MASTER_ID"] . $postfields["NAME1"] . $postfields["PAYMENT_ACCOUNT"]);
        $data = curlCall($url, $postfields);
        $result = explode("&", $data);
        foreach ($result as $res) {
            $res = explode("=", $res);
            $resultarray[$res[0]] = $res[1];
        }
        if ($resultarray["STATUS"] == "1") {
            invoiceSetPayMethodRemoteToken($params["invoiceid"], $resultarray["TRANS_ID"]);
            return ["status" => "success", "transid" => $resultarray["TRANS_ID"], "rawdata" => $resultarray];
        }
        return ["status" => "error", "rawdata" => $resultarray];
    }
}

function bluepayremote_storeremote($params)
{
    $url = "https://secure.bluepay.com/interfaces/bp20post";
    $postfields = [];
    $postfields["ACCOUNT_ID"] = $params["bpaccountid"];
    $postfields["USER_ID"] = $params["bpuserid"];
    $postfields["TRANS_TYPE"] = "AUTH";
    $postfields["PAYMENT_TYPE"] = "CREDIT";
    $postfields["MODE"] = $params["testmode"] ? "TEST" : "LIVE";
    $postfields["AMOUNT"] = 0;
    $postfields["NAME1"] = $params["clientdetails"]["firstname"];
    $postfields["NAME2"] = $params["clientdetails"]["lastname"];
    $postfields["COMPANY_NAME"] = $params["clientdetails"]["companyname"];
    $postfields["ADDR1"] = $params["clientdetails"]["address1"];
    $postfields["ADDR2"] = $params["clientdetails"]["address2"];
    $postfields["CITY"] = $params["clientdetails"]["city"];
    $postfields["STATE"] = $params["clientdetails"]["state"];
    $postfields["ZIP"] = $params["clientdetails"]["postcode"];
    $postfields["COUNTRY"] = $params["clientdetails"]["country"];
    $postfields["PHONE"] = $params["clientdetails"]["phonenumber"];
    $postfields["EMAIL"] = $params["clientdetails"]["email"];
    $postfields["PAYMENT_ACCOUNT"] = $params["cardnum"];
    $postfields["CARD_EXPIRE"] = $params["cardexp"];
    $postfields["TAMPER_PROOF_SEAL"] = md5($params["bpsecretkey"] . $params["bpaccountid"] . $postfields["TRANS_TYPE"] . $postfields["AMOUNT"] . $postfields["MASTER_ID"] . $postfields["NAME1"] . $postfields["PAYMENT_ACCOUNT"]);
    $data = curlCall($url, $postfields);
    $result = explode("&", $data);
    foreach ($result as $res) {
        $res = explode("=", $res);
        $resultarray[$res[0]] = $res[1];
    }
    if ($resultarray["STATUS"] == "1") {
        return ["status" => "success", "gatewayid" => $resultarray["TRANS_ID"], "rawdata" => $resultarray];
    }
    return ["status" => "failed", "rawdata" => $resultarray];
}
