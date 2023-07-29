<?php

define("CLIENTAREA", true);
require "init.php";
require "includes/gatewayfunctions.php";
require "includes/invoicefunctions.php";
require "includes/clientfunctions.php";
require "includes/adminfunctions.php";
$id = $invoiceid = $invoiceIdTitle = (int) $whmcs->get_req_var("id");
$breadcrumbnav = "<a href=\"index.php\">" . $whmcs->get_lang("globalsystemname") . "</a> > <a href=\"clientarea.php\">" . $whmcs->get_lang("clientareatitle") . "</a> > <a href=\"clientarea.php?action=invoices\">" . $_LANG["invoices"] . "</a> > <a href=\"viewinvoice.php?id=" . $invoiceid . "\">" . $_LANG["invoicenumber"] . $invoiceid . "</a>";
$invoice = new WHMCS\Invoice();
$invoiceExists = true;
try {
    $invoice->setID($invoiceid);
} catch (Exception $e) {
    $invoiceExists = false;
}
$invoiceModel = $invoice->getModel();
$invoiceOwnerId = $invoiceModel->userid;
$adminUser = WHMCS\User\Admin::getAuthenticatedUser();
if ($adminUser && !checkPermission("Manage Invoice", true)) {
    $adminUser = NULL;
}
$existingLanguage = NULL;
if ($adminUser && App::getFromRequest("view_as_client")) {
    $existingLanguage = getUsersLang($invoiceOwnerId);
}
if ($invoiceExists && $invoice->getData("invoicenum")) {
    $invoiceIdTitle = $invoice->getData("invoicenum");
}
initialiseClientArea($whmcs->get_lang("invoicestitle") . $invoiceIdTitle, "", "", "", $breadcrumbnav);
if (!$adminUser) {
    Auth::requireLoginAndClient(true);
    try {
        if (!$invoiceExists) {
            throw new Exception();
        }
        Auth::forceSwitchClientIdOrFail($invoiceOwnerId);
        if ($invoiceModel->status == WHMCS\Billing\Invoice::STATUS_DRAFT) {
            throw new Exception();
        }
    } catch (Exception $e) {
        $smarty->assign("error", "on");
        $smarty->assign("invalidInvoiceIdRequested", true);
        outputClientArea("viewinvoice", true);
        exit;
    }
    checkContactPermission("invoices");
}
$smarty->assign("invalidInvoiceIdRequested", false);
if (($invoice->getData("status") === "Paid" || $invoice->getData("status") === "Payment Pending") && isset($_SESSION["orderdetails"]) && $_SESSION["orderdetails"]["InvoiceID"] === $invoiceid && empty($_SESSION["orderdetails"]["paymentcomplete"])) {
    $_SESSION["orderdetails"]["paymentcomplete"] = true;
    redir("a=complete", "cart.php");
}
$gateway = $whmcs->get_req_var("gateway");
if ($gateway) {
    check_token();
    $gateways = new WHMCS\Gateways();
    $validgateways = $gateways->getAvailableGateways($invoiceid);
    if (array_key_exists($gateway, $validgateways)) {
        $invoiceToUpdate = WHMCS\Billing\Invoice::find($invoiceid);
        if ($invoiceToUpdate && $invoiceToUpdate->paymentmethod !== $gateway) {
            $invoiceToUpdate->setPaymentMethod($gateway)->save();
        }
        run_hook("InvoiceChangeGateway", ["invoiceid" => $invoiceid, "paymentmethod" => $gateway]);
    }
    redir("id=" . $invoiceid);
}
$creditbal = get_query_val("tblclients", "credit", ["id" => $invoice->getData("userid")]);
$smartyvalues["manualapplycredit"] = false;
if ($invoice->getData("status") == "Unpaid" && 0 < $creditbal && !$invoice->isAddFundsInvoice()) {
    $balance = $invoice->getData("balance");
    $creditamount = $whmcs->get_req_var("creditamount");
    if ($whmcs->get_req_var("applycredit") && 0 < $creditamount) {
        check_token();
        if ($creditbal < $creditamount) {
            echo $_LANG["invoiceaddcreditovercredit"];
            exit;
        }
        if ($balance < $creditamount) {
            echo $_LANG["invoiceaddcreditoverbalance"];
            exit;
        }
        applyCredit($invoiceid, $invoice->getData("userid"), $creditamount);
        redir("id=" . $invoiceid);
    }
    $smartyvalues["manualapplycredit"] = true;
    $clientCurrency = getCurrency($invoice->getData("userid"));
    $smartyvalues["totalcredit"] = formatCurrency($creditbal, $clientCurrency["id"]) . generate_token("form");
    if (!$creditamount) {
        $creditamount = $balance <= $creditbal ? $balance : $creditbal;
    }
    $smartyvalues["creditamount"] = $creditamount;
}
$outputvars = $invoice->getOutput();
$smartyvalues = array_merge($smartyvalues, $outputvars);
$invoiceitems = $invoice->getLineItems();
$smartyvalues["invoiceitems"] = $invoiceitems;
$transactions = $invoice->getTransactions();
$smartyvalues["transactions"] = $transactions;
$paymentbutton = $invoice->getData("status") == "Unpaid" && 0 < $invoice->getData("balance") ? $invoice->getPaymentLink() : "";
$smartyvalues["paymentbutton"] = $paymentbutton;
$smartyvalues["paymentSuccess"] = (bool) $whmcs->get_req_var("paymentsuccess");
$smartyvalues["paymentInititated"] = (bool) $whmcs->get_req_var("paymentinititated");
$smartyvalues["paymentFailed"] = (bool) $whmcs->get_req_var("paymentfailed");
$smartyvalues["pendingReview"] = (bool) $whmcs->get_req_var("pendingreview");
$smartyvalues["offlineReview"] = (bool) $whmcs->get_req_var("offlinepaid");
$smartyvalues["offlinepaid"] = (bool) $whmcs->get_req_var("offlinepaid");
$smartyvalues["paymentSuccessAwaitingNotification"] = $invoice->showPaymentSuccessAwaitingNotificationMsg($smartyvalues["paymentSuccess"]);
if ($whmcs->get_config("AllowCustomerChangeInvoiceGateway")) {
    $smartyvalues["allowchangegateway"] = true;
    $gateways = new WHMCS\Gateways();
    $availablegateways = $gateways->getAvailableGateways($invoiceid);
    $currency = Currency::factoryForClientArea();
    foreach ($availablegateways as $module => $value) {
        try {
            $gatewayInterface = WHMCS\Module\Gateway::factory($module);
        } catch (Exception $e) {
            unset($availablegateways[$module]);
        }
        if (!$gatewayInterface->isSupportedCurrency($currency["code"])) {
            unset($availablegateways[$module]);
        }
    }
    $frm = new WHMCS\Form();
    $gatewaydropdown = generate_token("form") . $frm->dropdown("gateway", $availablegateways, $invoice->getData("paymentmodule"), "submit()");
    $smartyvalues["gatewaydropdown"] = $gatewaydropdown;
    $smartyvalues["tokenInput"] = generate_token("form");
    $smartyvalues["selectedGateway"] = $invoice->getData("paymentmodule");
    $smartyvalues["availableGateways"] = $availablegateways;
} else {
    $smartyvalues["allowchangegateway"] = false;
}
$smartyvalues["taxIdLabel"] = Lang::trans(WHMCS\Billing\Tax\Vat::getLabel());
outputClientArea("viewinvoice", true, ["ClientAreaPageViewInvoice"]);
if ($existingLanguage) {
    swapLang($existingLanguage);
}
