<?php

require "../../../init.php";
$whmcs->load_function("gateway");
$whmcs->load_function("invoice");
$GATEWAY = getGatewayVariables("protxvspform");
if (!$GATEWAY["type"]) {
    exit("Module Not Activated");
}
$strEncryptionPassword = $GATEWAY["xorencryptionpw"];
$strCrypt = $whmcs->get_req_var("crypt");
$cipher = new phpseclib\Crypt\AES();
$cipher->setKey($GATEWAY["xorencryptionpw"]);
$cipher->setIV($GATEWAY["xorencryptionpw"]);
$strDecoded = $cipher->decrypt(protxvspform_hex2bin(substr($strCrypt, 1)));
$values = [];
parse_str($strDecoded, $values);
if (!is_array($values) || !isset($values["Status"]) || !isset($values["VendorTxCode"]) || !isset($values["VPSTxId"])) {
    throw new Exception("Invalid Callback Response");
}
$strStatus = $values["Status"];
$strVendorTxCode = $values["VendorTxCode"];
$strVPSTxId = $values["VPSTxId"];
$invoiceId = (int) substr($strVendorTxCode, 14);
$invoiceId = checkCbInvoiceID($invoiceId, $GATEWAY["paymentmethod"]);
$transactionStatus = "Error";
$redirectUrl = "id=" . $invoiceId . "&paymentfailed=true";
if ($strStatus == "OK") {
    addInvoicePayment($invoiceId, $strVPSTxId, "", "", "protxvspform");
    $transactionStatus = "Successful";
    $redirectUrl = "id=" . $invoiceId . "&paymentsuccess=true";
}
logTransaction($GATEWAY["paymentmethod"], $values, $transactionStatus);
redirSystemURL($redirectUrl, "viewinvoice.php");
function protxvspform_hex2bin($hexInput)
{
    if (function_exists("hex2bin")) {
        return hex2bin($hexInput);
    }
    $len = strlen($hexInput);
    if ($len % 2 != 0) {
        return false;
    }
    if (strspn($hexInput, "0123456789abcdefABCDEF") != $len) {
        return false;
    }
    $output = "";
    $i = 0;
    while ($i < $len) {
        $output .= pack("H*", substr($hexInput, $i, 2));
        $i += 2;
    }
    return $output;
}
