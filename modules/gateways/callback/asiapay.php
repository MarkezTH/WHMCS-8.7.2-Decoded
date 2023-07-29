<?php

require "../../../init.php";
$whmcs = WHMCS\Application::getInstance();
$whmcs->load_function("gateway");
$whmcs->load_function("invoice");
$GATEWAY = getGatewayVariables("asiapay");
if (!$GATEWAY["type"]) {
    exit("Module Not Activated");
}
$orderRef = $whmcs->get_req_var("Ref");
$successCode = $whmcs->get_req_var("successcode");
$invoiceid = checkCbInvoiceID($orderRef, $GATEWAY["paymentmethod"]);
if (is_numeric($successCode)) {
    echo "OK";
    $prc = $whmcs->get_req_var("prc");
    $src = $whmcs->get_req_var("src");
    if (!isset($debugdata)) {
        $debugdata = $whmcs->get_req_var("debugdata");
    }
    $prcResponse = ["Success", "Rejected by Payment Bank", "3" => "Rejected due to Payer Authentication Failure", "18446744073709551615" => "Rejected due to Input Parameters Incorrect", "18446744073709551614" => "Rejected due to Server Access Error", "18446744073709551608" => "Rejected due to PayDollar Internal/Fraud Prevention Checking", "18446744073709551607" => "Rejected by Host Access Error"];
    $srcResponse = [["Success"], ["01" => "Bank Decline", "02" => "Bank Decline", "03" => "Other", "04" => "Other", "05" => "Bank Decline", "12" => "Other", "13" => "Other", "14" => "Input Error", "19" => "Other", "25" => "Other", "30" => "Other", "31" => "Other", "41" => "Lost/Stolen Card", "43" => "Lost/Stolen Card", "51" => "Bank Decline", "54" => "Input Error", "55" => "Other", "58" => "Other", "76" => "Other", "77" => "Other", "78" => "Other", "80" => "Other", "89" => "Other", "90" => "Other", "91" => "Other", "94" => "Other", "95" => "Other", "96" => "Other", "99" => "Other", "2000" => "Other"], "3" => ["Payer Authentication Failure"], "18446744073709551615" => ["18446744073709551615" => "Input Parameter Error"], "18446744073709551614" => ["18446744073709551614" => "Server Access Error"], "18446744073709551608" => ["999" => "Other", "1000" => "Skipped Transaction", "2000" => "Blacklist error", "2001" => "Blacklist card by system", "2002" => "Blacklist card by merchant", "2003" => "Black IP by system", "2004" => "Black IP by merchant", "2005" => "Invalid cardholder name", "2006" => "Same card used more than 6 times a day", "2007" => "Duplicate merchant reference no.", "2008" => "Empty merchant reference no.", "2011" => "Other", "2012" => "Card verification failed", "2013" => "Card already registered", "2014" => "High risk country", "2016" => "Same payer IP attempted more than pre-defined no. a day.", "2017" => "Invalid card number", "2018" => "Multi-card attempt", "2019" => "Issuing Bank not match", "2020" => "Single transaction limit exceeded", "2021" => "Daily transaction limit exceeded", "2022" => "Monthly transaction limit exceeded", "2023" => "Invalid channel type", "2031" => "System rejected (TN)", "2032" => "System rejected (TA)", "2033" => "System rejected (TR)", "2099" => "Non testing card"], "18446744073709551607" => ["18446744073709551607" => "Host Access Error"]];
    $prcMsg = array_key_exists($prc, $prcResponse) ? $prcResponse[$prc] : "";
    $srcMsg = array_key_exists($src, $srcResponse[$prc]) ? $srcResponse[$prc][$src] : "";
    if ($successCode == 0) {
        $payRef = $whmcs->get_req_var("PayRef");
        $currCode = $whmcs->get_req_var("Cur");
        $amount = $whmcs->get_req_var("Amt");
        $payerAuth = $whmcs->get_req_var("payerAuth");
        $suppliedHash = $whmcs->get_req_var("secureHash");
        if (isset($GATEWAY["secureHashKey"]) && 0 < strlen(trim($GATEWAY["secureHashKey"]))) {
            $secureHashKey = $GATEWAY["secureHashKey"];
            $hashArr = [$src, $prc, $successCode, $orderRef, $payRef, $currCode, $amount, $payerAuth, $secureHashKey];
            $secureHash = sha1(implode("|", $hashArr));
        } else {
            $secureHash = $suppliedHash;
        }
        if ($suppliedHash == $secureHash) {
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
            if (0 < strlen($prcMsg) && 0 < strlen($srcMsg)) {
                $msg = $prcMsg . " - " . $srcMsg;
            } else {
                $msg = "Successful";
            }
            addInvoicePayment($invoiceid, $payRef, $amount, "0", "asiapay");
            logTransaction($GATEWAY["paymentmethod"], $debugdata, trim($msg));
            redirSystemURL("id=" . $invoiceid . "&paymentsuccess=true", "viewinvoice.php");
        } else {
            logTransaction($GATEWAY["paymentmethod"], $_REQUEST, "Invalid Hash");
            redirSystemURL("id=" . $invoiceid . "&paymentfailed=true", "viewinvoice.php");
        }
    } else {
        if (0 < strlen($prcMsg) && 0 < strlen($srcMsg)) {
            $msg = $prcMsg . " - " . $srcMsg;
        } else {
            $msg = "Payment was declined";
        }
        logTransaction($GATEWAY["paymentmethod"], $_REQUEST, trim($msg));
        redirSystemURL("id=" . $invoiceid . "&paymentfailed=true", "viewinvoice.php");
    }
} else {
    redirSystemURL("id=" . $invoiceid, "viewinvoice.php");
}
