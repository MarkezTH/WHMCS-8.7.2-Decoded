<?php

require "../../../init.php";
$whmcs->load_function("gateway");
$whmcs->load_function("invoice");
$GATEWAY = getGatewayVariables("cashu");
if (!$GATEWAY["type"]) {
    exit("Module Not Activated");
}
$amount = $_REQUEST["amount"];
$currency = $_REQUEST["currency"];
$trn_id = $_REQUEST["trn_id"];
$session_id = (int) $_REQUEST["session_id"];
$verificationString = $_REQUEST["verificationString"];
$invoiceid = checkCbInvoiceID($session_id, $GATEWAY["paymentmethod"]);
$verstr = [strtolower($GATEWAY["merchantid"]), strtolower($trn_id), $GATEWAY["encryptionkeyword"]];
$verstr = implode(":", $verstr);
$verstr = sha1($verstr);
if ($verstr == $verificationString) {
    if (isset($GATEWAY["convertto"]) && 0 < strlen($GATEWAY["convertto"])) {
        $data = WHMCS\Database\Capsule::table("tblinvoices")->where("id", $invoiceid)->first(["userid", "total"]);
        $total = $data->total;
        $currencyArr = getCurrency($data->userid);
        $amount = convertCurrency($amount, $currencyArr["id"], $GATEWAY["convertto"], 1);
        $roundAmt = round($amount, 1);
        $roundTotal = round($total, 1);
        if ($roundAmt == $roundTotal) {
            $amount = $total;
        }
    }
    addInvoicePayment($invoiceid, $trn_id, $amount, "0", "cashu");
    $transactionStatus = "Successful";
    $success = true;
} else {
    $transactionStatus = "Invalid Hash";
    $success = false;
}
logTransaction($GATEWAY["paymentmethod"], $_REQUEST, $transactionStatus);
callback3DSecureRedirect($invoiceid, $success);
