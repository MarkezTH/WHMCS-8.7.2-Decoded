<?php

define("CLIENTAREA", true);
require "init.php";
require "includes/gatewayfunctions.php";
require "includes/quotefunctions.php";
require "includes/invoicefunctions.php";
require "includes/clientfunctions.php";
$whmcs = App::self();
$action = App::getFromRequest("action");
$id = (int) $whmcs->get_req_var("id");
$pagetitle = $_LANG["quotestitle"];
$breadcrumbnav = "<a href=\"index.php\">" . $_LANG["globalsystemname"] . "</a> > " . "<a href=\"clientarea.php\">" . $_LANG["clientareatitle"] . "</a> > " . "<a href=\"clientarea.php?action=quotes\">" . $_LANG["quotes"] . "</a> > " . "<a href=\"viewquote.php?id=" . $id . "\">" . $pagetitle . "</a>";
initialiseClientArea($whmcs->get_lang("quotestitle") . $id, "", $breadcrumbnav);
$admin = WHMCS\User\Admin::getAuthenticatedUser();
if ($admin && !$admin->hasPermission("Manage Quotes")) {
    $admin = NULL;
}
if (!Auth::user() && !$admin) {
    $goto = "viewquote";
    require "login.php";
    exit;
}
if (!$admin && !checkContactPermission("quotes", true)) {
    redir("action=quotes", "clientarea.php");
    exit;
}
$smarty->assign("id", NULL);
$smarty->assign("quoteid", NULL);
$smarty->assign("tosurl", NULL);
$smarty->assign("agreetosrequired", NULL);
$smarty->assign("customfields", NULL);
$smarty->assign("taxrate", NULL);
$smarty->assign("taxrate2", NULL);
$smarty->assign("notes", NULL);
$quote = WHMCS\Billing\Quote::with("items")->where("id", $id);
$acceptedQuote = false;
if ($action == "accept") {
    if (!$agreetos && $CONFIG["EnableTOSAccept"]) {
        $smarty->assign("agreetosrequired", true);
    } else {
        try {
            $quote = $quote->where("userid", $_SESSION["uid"])->whereNotIn("stage", ["Draft", "Accepted"])->firstOrFail();
        } catch (Exception $e) {
            $smarty->assign("error", "on");
            $smarty->assign("invalidQuoteIdRequested", true);
            outputClientArea("viewquote", true);
            exit;
        }
        $quote->stage = "Accepted";
        $quote->dateaccepted = WHMCS\Carbon::now();
        $quote->save();
        $acceptedQuote = true;
        logActivity("Quote Accepted - Quote ID: " . $id);
        $quote_data = $quote->toArray();
        if ($quote_data["userid"]) {
            $clientsdetails = getClientsDetails($quote_data["userid"], "billing");
        } else {
            $clientsdetails = $quote_data;
        }
        $pdfdata = genQuotePDF($id);
        $messageArr = ["emailquote" => true, "quote_number" => $id, "quote_subject" => $quote_data["subject"], "quote_date_created" => fromMySQLDate($quote_data["datecreated"], false, true), "invoice_num" => "", "client_first_name" => $clientsdetails["firstname"], "client_last_name" => $clientsdetails["lastname"], "client_company_name" => $clientsdetails["companyname"], "client_email" => $clientsdetails["email"], "client_address1" => $clientsdetails["address1"], "client_address2" => $clientsdetails["address2"], "client_city" => $clientsdetails["city"], "client_state" => $clientsdetails["state"], "client_postcode" => $clientsdetails["postcode"], "client_country" => $clientsdetails["country"], "client_phonenumber" => $clientsdetails["phonenumber"], "client_id" => $clientsdetails["userid"], "client_language" => $clientsdetails["language"], "quoteattachmentdata" => $pdfdata];
        sendMessage("Quote Accepted", $_SESSION["uid"], $messageArr);
        sendAdminMessage("Quote Accepted Notification", ["quote_number" => $id, "quote_subject" => $quote_data["subject"], "quote_date_created" => $quote_data["datecreated"], "client_id" => $vars["userid"] ?? NULL, "clientname" => $clientsdetails["firstname"] . " " . $clientsdetails["lastname"], "client_email" => $clientsdetails["email"], "client_company_name" => $clientsdetails["companyname"], "client_address1" => $clientsdetails["address1"], "client_address2" => $clientsdetails["address2"], "client_city" => $clientsdetails["city"], "client_state" => $clientsdetails["state"], "client_postcode" => $clientsdetails["postcode"], "client_country" => $clientsdetails["country"], "client_phonenumber" => $clientsdetails["phonenumber"], "client_ip" => $clientsdetails["ip"] ?? NULL, "client_hostname" => $clientsdetails["host"] ?? NULL], "account");
        run_hook("AcceptQuote", ["quoteid" => $id, "invoiceid" => $invoiceid ?? NULL]);
    }
}
if (!$acceptedQuote) {
    if (!$admin) {
        $quote->where("userid", $_SESSION["uid"])->where("stage", "!=", "Draft");
    }
    try {
        $quote = $quote->firstOrFail();
    } catch (Exception $e) {
        $smarty->assign("error", "on");
        $smarty->assign("invalidQuoteIdRequested", true);
        outputClientArea("viewquote", true);
        exit;
    }
}
$id = $quote->id;
$stage = $quote->stage;
$userid = $quote->userid;
$date = $quote->datecreated;
$validuntil = $quote->validuntil;
$subtotal = $quote->subtotal;
$total = $quote->total;
$status = $quote->status;
$proposal = $quote->proposal;
$notes = $quote->customernotes;
$currency = $quote->currency;
$smarty->assign("invalidQuoteIdRequested", false);
$currency = getCurrency($userid, $currency);
$date = fromMySQLDate($date, 0, 1);
$validuntil = fromMySQLDate($validuntil, 0, 1);
if ($userid) {
    $clientsdetails = getClientsDetails($userid, "billing");
} else {
    $clientsdetails = [];
    $clientsdetails["firstname"] = $quote->firstname;
    $clientsdetails["lastname"] = $quote->lastname;
    $clientsdetails["companyname"] = $quote->companyname;
    $clientsdetails["email"] = $quote->email;
    $clientsdetails["address1"] = $quote->address1;
    $clientsdetails["address2"] = $quote->address2;
    $clientsdetails["city"] = $quote->city;
    $clientsdetails["state"] = $quote->state;
    $clientsdetails["postcode"] = $quote->postcode;
    $clientsdetails["country"] = $quote->country;
    $clientsdetails["phonenumber"] = $quote->phonenumber;
}
if ($CONFIG["TaxEnabled"]) {
    $tax = $quote->tax1;
    $tax2 = $quote->tax2;
    $taxdata = getTaxRate(1, $clientsdetails["state"], $clientsdetails["country"]);
    $smarty->assign("taxname", $taxdata["name"]);
    $smarty->assign("taxrate", $taxdata["rate"]);
    $taxdata2 = getTaxRate(2, $clientsdetails["state"], $clientsdetails["country"]);
    $smarty->assign("taxname2", $taxdata2["name"]);
    $smarty->assign("taxrate2", $taxdata2["rate"]);
}
$countries = new WHMCS\Utility\Country();
$clientsdetails["country"] = $countries->getName($clientsdetails["country"]);
$smarty->assign("clientsdetails", $clientsdetails);
$smarty->assign("companyname", $CONFIG["CompanyName"]);
$smarty->assign("pagetitle", $_LANG["quotenumber"] . $id);
$smarty->assign("quoteid", $id);
$smarty->assign("quotenum", $id);
$smarty->assign("payto", nl2br($CONFIG["InvoicePayTo"]));
$smarty->assign("datecreated", $date);
$smarty->assign("datedue", $duedate ?? NULL);
$smarty->assign("subtotal", formatCurrency($subtotal));
$discountString = !empty($discount) ? $discount . "%" : "0%";
$smarty->assign("discount", $discountString);
$smarty->assign("tax", formatCurrency($tax ?? NULL));
$smarty->assign("tax2", formatCurrency($tax2 ?? NULL));
$smarty->assign("total", formatCurrency($total));
$smarty->assign("stage", $stage);
$smarty->assign("validuntil", $validuntil);
$quoteitems = [];
foreach ($quote->items as $data) {
    $qty = $data->quantity;
    $description = $data->description;
    $unitprice = $data->unitprice;
    $discountpc = $discount = $data->discount;
    $taxed = $data->isTaxable;
    if ($qty && $qty != 1) {
        $description = $qty . " x " . $description . " @ " . $unitprice . $_LANG["invoiceqtyeach"];
        $amount = $qty * $unitprice;
    } else {
        $amount = $unitprice;
    }
    $discount = $amount * $discount / 100;
    if ($discount) {
        $amount -= $discount;
    }
    $quoteitems[] = ["description" => nl2br($description), "unitprice" => formatCurrency($unitprice), "discount" => 0 < $discount ? formatCurrency($discount) : "", "discountpc" => $discountpc, "amount" => formatCurrency($amount), "taxed" => $taxed];
}
$smarty->assign("id", $id);
$smarty->assign("quoteitems", $quoteitems);
$smarty->assign("proposal", nl2br($proposal));
$smarty->assign("notes", nl2br($notes));
$smarty->assign("accepttos", $CONFIG["EnableTOSAccept"]);
$smarty->assign("tosurl", $CONFIG["TermsOfService"]);
outputClientArea("viewquote", true, ["ClientAreaPageViewQuote"]);
