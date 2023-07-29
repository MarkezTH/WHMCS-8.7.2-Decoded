<?php

use WHMCS\Application\Support\Facades\Auth;
use WHMCS\Application\Support\Facades\HookMgr;
use WHMCS\Application\Support\Facades\Lang;

function saveQuote($id = 0, $subject = "", $stage = "", $dateCreated = "", $validUntil = "", $clientType = "", $clientId = 0, $firstName = "", $lastName = "", $companyName = "", $email = "", $address1 = "", $address2 = "", $city = "", $state = "", $postcode = "", $country = "", $phoneNumber = "", $currency = 0, $lineItems = [], $proposal = "", $customerNotes = "", $adminNotes = "", $updatePriceOnly = false, $taxId = "")
{
    $quoteCreated = false;
    if ($id) {
        $quote = WHMCS\Billing\Quote::findOrFail($id);
    } else {
        $quote = new WHMCS\Billing\Quote();
        $quote->subject = $subject;
        $quote->status = $stage;
        $quote->dateCreated = toMySQLDate($dateCreated);
        $quote->validUntilDate = toMySQLDate($validUntil);
        $quote->lastModifiedDate = WHMCS\Carbon::now();
        $quote->save();
        $quoteCreated = true;
    }
    if ($clientType == "new") {
        $clientId = 0;
        $stateTax = $state;
        $countryTax = $country;
        $isClientTaxExempt = false;
        if ($taxId) {
            $isClientTaxExempt = WHMCS\Billing\Tax\Vat::validateNumber($taxId) && WHMCS\Config\Setting::getValue("TaxEUTaxExempt");
        }
    } else {
        $clientDetails = getClientsDetails($clientId);
        $stateTax = $clientDetails["state"];
        $countryTax = $clientDetails["country"];
        $isClientTaxExempt = $clientDetails["taxexempt"];
    }
    $taxLevel1 = getTaxRate(1, $stateTax, $countryTax);
    $taxLevel2 = getTaxRate(2, $stateTax, $countryTax);
    $subtotal = 0;
    $taxableAmount = 0;
    $tax1 = 0;
    $tax2 = 0;
    if ($lineItems) {
        foreach ($lineItems as $lineItem) {
            if (!empty($lineItem["id"])) {
                $quoteItem = $quote->items->find($lineItem["id"]);
            } else {
                $quoteItem = new WHMCS\Billing\Quote\Item();
                $quoteItem->quoteId = $quote->id;
            }
            $quoteItem->description = $lineItem["desc"];
            $quoteItem->quantity = (double) $lineItem["qty"];
            $quoteItem->unitPrice = (double) $lineItem["up"];
            $quoteItem->discount = (double) $lineItem["discount"] ?? 0;
            $quoteItem->isTaxable = $lineItem["taxable"] ?? false;
            $quoteItem->save();
            $lineItemAmount = $quoteItem->getTotal();
            $subtotal += $lineItemAmount;
            if ($quoteItem->isTaxable) {
                $taxableAmount += $lineItemAmount;
            }
        }
    } else {
        foreach ($quote->items as $item) {
            $lineItemAmount = round($item->getTotal(), 2);
            $subtotal += $lineItemAmount;
            if ($item->isTaxable) {
                $taxableAmount += $lineItemAmount;
            }
        }
    }
    if (WHMCS\Config\Setting::getValue("TaxEnabled")) {
        if (0 < $taxLevel1["rate"] && !$isClientTaxExempt) {
            if (WHMCS\Config\Setting::getValue("TaxType") == "Inclusive") {
                $tax1 = format_as_currency($taxableAmount / (100 + $taxLevel1["rate"]) * $taxLevel1["rate"]);
            } else {
                $tax1 = format_as_currency($taxableAmount * $taxLevel1["rate"] / 100);
            }
        }
        if (0 < $taxLevel2["rate"] && !$isClientTaxExempt) {
            if (WHMCS\Config\Setting::getValue("TaxType") == "Inclusive") {
                $tax2 = format_as_currency($taxableAmount / (100 + $taxLevel2["rate"]) * $taxLevel2["rate"]);
            } else {
                if (WHMCS\Config\Setting::getValue("TaxL2Compound")) {
                    $tax2 = format_as_currency(($taxableAmount + $tax1) * $taxLevel2["rate"] / 100);
                } else {
                    $tax2 = format_as_currency($taxableAmount * $taxLevel2["rate"] / 100);
                }
            }
        }
    }
    if (WHMCS\Config\Setting::getValue("TaxType") == "Inclusive") {
        $total = $subtotal;
        $subtotal = $subtotal - $tax1 - $tax2;
    } else {
        $total = $subtotal + $tax1 + $tax2;
    }
    if ($updatePriceOnly) {
        $quote->subtotal = $subtotal;
        $quote->tax1 = $tax1;
        $quote->tax2 = $tax2;
        $quote->total = $total;
        $quote->save();
    } else {
        $quote->subject = $subject;
        $quote->status = $stage;
        $quote->dateCreated = toMySQLDate($dateCreated);
        $quote->validUntilDate = toMySQLDate($validUntil);
        $quote->lastModifiedDate = WHMCS\Carbon::now();
        $quote->clientId = $clientId;
        $quote->firstName = $firstName;
        $quote->lastName = $lastName;
        $quote->companyName = $companyName;
        $quote->email = $email;
        $quote->address1 = $address1;
        $quote->address2 = $address2;
        $quote->city = $city;
        $quote->state = $state;
        $quote->postcode = $postcode;
        $quote->country = $country;
        $quote->phoneNumber = $phoneNumber;
        $quote->taxId = $taxId;
        $quote->currency = $currency;
        $quote->subtotal = $subtotal;
        $quote->tax1 = $tax1;
        $quote->tax2 = $tax2;
        $quote->total = $total;
        $quote->proposal = $proposal;
        $quote->customerNotes = $customerNotes;
        $quote->adminNotes = $adminNotes;
        $quote->save();
    }
    if ($quoteCreated) {
        HookMgr::run("QuoteCreated", ["quoteid" => $quote->id, "status" => $stage]);
    } else {
        HookMgr::run("QuoteStatusChange", ["quoteid" => $quote->id, "status" => $stage]);
    }
    return $quote->id;
}

function genQuotePDF($id)
{
    global $whmcs;
    global $CONFIG;
    global $_LANG;
    global $currency;
    $companyname = $CONFIG["CompanyName"];
    $companyurl = $CONFIG["Domain"];
    $companyaddress = $CONFIG["InvoicePayTo"];
    $companyaddress = explode("\n", $companyaddress);
    $quotenumber = $id;
    $result = select_query("tblquotes", "", ["id" => $id]);
    $data = mysql_fetch_array($result);
    $subject = $data["subject"];
    $stage = $data["stage"];
    $datecreated = fromMySQLDate($data["datecreated"]);
    $validuntil = fromMySQLDate($data["validuntil"]);
    $userid = $data["userid"];
    $proposal = $data["proposal"] ? $data["proposal"] . "\n" : "";
    $notes = $data["customernotes"] ? $data["customernotes"] . "\n" : "";
    $currency = getCurrency($userid, $data["currency"]);
    if ($userid) {
        getUsersLang($userid);
        $stage = getQuoteStageLang($stage);
        $clientsdetails = getClientsDetails($userid);
    } else {
        $clientsdetails["firstname"] = $data["firstname"];
        $clientsdetails["lastname"] = $data["lastname"];
        $clientsdetails["companyname"] = $data["companyname"];
        $clientsdetails["email"] = $data["email"];
        $clientsdetails["address1"] = $data["address1"];
        $clientsdetails["address2"] = $data["address2"];
        $clientsdetails["city"] = $data["city"];
        $clientsdetails["state"] = $data["state"];
        $clientsdetails["postcode"] = $data["postcode"];
        $clientsdetails["country"] = $data["country"];
        $clientsdetails["phonenumber"] = $data["phonenumber"];
    }
    $taxlevel1 = getTaxRate(1, $clientsdetails["state"], $clientsdetails["country"]);
    $taxlevel2 = getTaxRate(2, $clientsdetails["state"], $clientsdetails["country"]);
    $countries = new WHMCS\Utility\Country();
    $clientsdetails["country"] = $countries->getName($clientsdetails["country"]);
    $subtotal = formatCurrency($data["subtotal"]);
    $tax1 = formatCurrency($data["tax1"]);
    $tax2 = formatCurrency($data["tax2"]);
    $total = formatCurrency($data["total"]);
    $lineitems = [];
    $result = select_query("tblquoteitems", "", ["quoteid" => $id], "id", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $line_id = $data["id"];
        $line_desc = $data["description"];
        $line_qty = $data["quantity"];
        $line_unitprice = $data["unitprice"];
        $line_discount = $data["discount"];
        $line_taxable = $data["taxable"];
        $line_total = format_as_currency($line_qty * $line_unitprice * (1 - $line_discount / 100));
        $lineitems[] = ["id" => $line_id, "description" => htmlspecialchars(WHMCS\Input\Sanitize::decode($line_desc)), "qty" => $line_qty, "unitprice" => $line_unitprice, "discount" => $line_discount, "taxable" => $line_taxable, "total" => formatCurrency($line_total)];
    }
    $tplvars = [];
    $tplvars["companyname"] = $companyname;
    $tplvars["companyurl"] = $companyurl;
    $tplvars["companyaddress"] = $companyaddress;
    $tplvars["quotenumber"] = $quotenumber;
    $tplvars["subject"] = $subject;
    $tplvars["stage"] = $stage;
    $tplvars["datecreated"] = $datecreated;
    $tplvars["validuntil"] = $validuntil;
    $tplvars["userid"] = $userid;
    $tplvars["clientsdetails"] = $clientsdetails;
    $tplvars["proposal"] = $proposal;
    $tplvars["notes"] = $notes;
    $tplvars["taxlevel1"] = $taxlevel1;
    $tplvars["taxlevel2"] = $taxlevel2;
    $tplvars["subtotal"] = $subtotal;
    $tplvars["tax1"] = $tax1;
    $tplvars["tax2"] = $tax2;
    $tplvars["total"] = $total;
    $tplvars = WHMCS\Input\Sanitize::decode($tplvars);
    $tplvars["lineitems"] = $lineitems;
    $tplvars["pdfFont"] = WHMCS\Config\Setting::getValue("TCPDFFont");
    $invoice = new WHMCS\Invoice();
    $invoice->pdfCreate($_LANG["quotenumber"] . $id);
    $invoice->pdfAddPage("quotepdf.tpl", $tplvars);
    $pdfdata = $invoice->pdfOutput();
    return $pdfdata;
}

function sendQuotePDF($id)
{
    global $CONFIG;
    global $_LANG;
    global $currency;
    $result = select_query("tblquotes", "", ["id" => $id]);
    $data = mysql_fetch_array($result);
    $subject = $data["subject"];
    $stage = $data["stage"];
    $datecreated = fromMySQLDate($data["datecreated"]);
    $validuntil = fromMySQLDate($data["validuntil"]);
    $userid = $data["userid"];
    $notes = $data["customernotes"] . "\n";
    if ($userid) {
        $clientsdetails = getClientsDetails($userid);
    } else {
        $clientsdetails["firstname"] = $data["firstname"];
        $clientsdetails["lastname"] = $data["lastname"];
        $clientsdetails["companyname"] = $data["companyname"];
        $clientsdetails["email"] = $data["email"];
        $clientsdetails["address1"] = $data["address1"];
        $clientsdetails["address2"] = $data["address2"];
        $clientsdetails["city"] = $data["city"];
        $clientsdetails["state"] = $data["state"];
        $clientsdetails["postcode"] = $data["postcode"];
        $clientsdetails["country"] = $data["country"];
        $clientsdetails["phonenumber"] = $data["phonenumber"];
    }
    $pdfdata = genquotepdf($id);
    $sysurl = App::getSystemUrl();
    $quote_link = "<a href=\"" . $sysurl . "viewquote.php?id=" . $id . "\">" . $sysurl . "viewquote.php?id=" . $id . "</a>";
    $result = sendMessage("Quote Delivery with PDF", $userid, ["emailquote" => true, "quote_number" => $id, "quote_subject" => $subject, "quote_date_created" => $datecreated, "quote_valid_until" => $validuntil, "client_id" => $userid, "client_first_name" => $clientsdetails["firstname"], "client_last_name" => $clientsdetails["lastname"], "client_company_name" => $clientsdetails["companyname"], "client_email" => $clientsdetails["email"], "client_address1" => $clientsdetails["address1"], "client_address2" => $clientsdetails["address2"], "client_city" => $clientsdetails["city"], "client_state" => $clientsdetails["state"], "client_postcode" => $clientsdetails["postcode"], "client_country" => $clientsdetails["country"], "client_phonenumber" => $clientsdetails["phonenumber"], "client_language" => $clientsdetails["language"] ?? NULL, "quoteattachmentdata" => $pdfdata, "quote_link" => $quote_link]);
    if ($result === true) {
        update_query("tblquotes", ["stage" => "Delivered"], ["id" => $id]);
        return true;
    }
    return $result;
}

function convertQuotetoInvoice($id, $invoicetype = NULL, $invoiceduedate = NULL, $depositpercent = 0, $depositduedate = NULL, $finalduedate = NULL, $sendemail = false)
{
    global $CONFIG;
    global $_LANG;
    $result = select_query("tblquotes", "", ["id" => $id]);
    $data = mysql_fetch_array($result);
    $userid = $data["userid"];
    $firstname = $data["firstname"];
    $lastname = $data["lastname"];
    $companyname = $data["companyname"];
    $email = $data["email"];
    $address1 = $data["address1"];
    $address2 = $data["address2"];
    $city = $data["city"];
    $state = $data["state"];
    $postcode = $data["postcode"];
    $country = $data["country"];
    $phonenumber = $data["phonenumber"];
    $taxId = $data["tax_id"];
    $currency = $data["currency"];
    if ($userid) {
        getUsersLang($userid);
        $clientsdetails = getClientsDetails($userid);
    } else {
        $user = WHMCS\User\User::createUser($firstname, $lastname, $email, Illuminate\Support\Str::random(24));
        $_SESSION["currency"] = $currency;
        $client = $user->createClient($user, $firstname, $lastname, $companyname, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber, substr(md5($id), 0, 10), ["tax_id" => $taxId], "", true);
        getUsersLang($client->id);
        $clientsdetails = getClientsDetails($client);
        $userid = $client->id;
    }
    $taxExempt = $clientsdetails["taxexempt"];
    $taxRate = $taxRate2 = NULL;
    if ($taxExempt) {
        $taxRate = $taxRate2 = 0;
    }
    $subtotal = $data["subtotal"];
    $tax1 = $data["tax1"];
    $tax2 = $data["tax2"];
    $total = $data["total"];
    $duedate = $finaldate = "";
    if ($invoicetype == "deposit") {
        if ($depositduedate) {
            $duedate = toMySQLDate($depositduedate);
        }
        $finaldate = $finalduedate ? toMySQLDate($finalduedate) : date("Y-m-d");
    } else {
        if ($invoiceduedate) {
            $duedate = toMySQLDate($invoiceduedate);
        }
    }
    $finalinvoiceid = 0;
    $invoice = WHMCS\Billing\Invoice::newInvoice($userid, NULL, $taxRate, $taxRate2);
    if ($duedate) {
        $invoice->dateDue = $duedate;
    }
    $invoice->status = "Unpaid";
    $invoice->tax1 = $tax1;
    $invoice->tax2 = $tax2;
    $invoice->subtotal = $subtotal;
    $invoice->total = $total;
    $invoice->adminNotes = Lang::trans("quoteref") . $id;
    $invoice->save();
    $invoiceid = $invoice->id;
    if ($finaldate) {
        $finalInvoice = WHMCS\Billing\Invoice::newInvoice($userid, NULL, $taxRate, $taxRate2);
        if ($finaldate) {
            $finalInvoice->dateDue = $finaldate;
        }
        $finalInvoice->status = WHMCS\Billing\Invoice::STATUS_UNPAID;
        $finalInvoice->tax1 = $tax1;
        $finalInvoice->tax2 = $tax2;
        $finalInvoice->subtotal = $subtotal;
        $finalInvoice->total = $total;
        $finalInvoice->adminNotes = Lang::trans("quoteref") . $id;
        $finalInvoice->save();
        $finalinvoiceid = $finalInvoice->id;
    }
    $result = select_query("tblquoteitems", "", ["quoteid" => $id], "id", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $line_id = $data["id"];
        $line_desc = $data["description"];
        $line_qty = $data["quantity"];
        $line_unitprice = $data["unitprice"];
        $line_discount = $data["discount"];
        $line_taxable = $data["taxable"];
        $line_total = format_as_currency($line_qty * $line_unitprice * (1 - $line_discount / 100));
        $lineitemdesc = $line_qty . " x " . $line_desc . " @ " . $line_unitprice;
        if (0 < $line_discount) {
            $lineitemdesc .= " - " . $line_discount . "% " . $_LANG["orderdiscount"];
        }
        if ($finalinvoiceid) {
            $originalamount = $line_total;
            $line_total = $originalamount * $depositpercent / 100;
            $final_amount = $originalamount - $line_total;
            insert_query("tblinvoiceitems", ["invoiceid" => $finalinvoiceid, "userid" => $userid, "description" => $lineitemdesc . " (" . (100 - $depositpercent) . "% " . $_LANG["quotefinalpayment"] . ")", "amount" => $final_amount, "taxed" => $line_taxable]);
            $lineitemdesc .= " (" . $depositpercent . "% " . $_LANG["quotedeposit"] . ")";
        }
        insert_query("tblinvoiceitems", ["invoiceid" => $invoiceid, "userid" => $userid, "description" => $lineitemdesc, "amount" => $line_total, "taxed" => $line_taxable]);
    }
    if (!function_exists("updateInvoiceTotal")) {
        require ROOTDIR . "/includes/invoicefunctions.php";
    }
    updateInvoiceTotal($invoiceid);
    if ($finalinvoiceid) {
        updateInvoiceTotal($finalinvoiceid);
    }
    if (defined("APICALL")) {
        $source = "api";
        $user = WHMCS\Session::get("adminid");
    } else {
        if (defined("ADMINAREA")) {
            $source = "adminarea";
            $user = WHMCS\Session::get("adminid");
        } else {
            $source = "clientarea";
            $user = Auth::client()->id;
        }
    }
    $invoiceArr = ["source" => $source, "user" => $user, "invoiceid" => $invoiceid, "status" => "Unpaid"];
    $invoice->runCreationHooks($source);
    if ($sendemail) {
        run_hook("InvoiceCreationPreEmail", $invoiceArr);
        sendMessage("Invoice Created", $invoiceid);
    }
    HookMgr::run("InvoiceCreated", $invoiceArr);
    if ($finalinvoiceid) {
        $finalInvoice->runCreationHooks($source);
        $invoiceArr = ["source" => $source, "user" => $user, "invoiceid" => $finalinvoiceid, "status" => "Unpaid"];
        if ($sendemail) {
            run_hook("InvoiceCreationPreEmail", $invoiceArr);
            sendMessage("Invoice Created", $finalinvoiceid);
        }
        HookMgr::run("InvoiceCreated", $invoiceArr);
    }
    update_query("tblquotes", ["userid" => $userid, "stage" => "Accepted", "dateaccepted" => WHMCS\Carbon::now()->toDateString()], ["id" => $id]);
    return $invoiceid;
}

function getQuoteStageLang($stage)
{
    global $_LANG;
    $translation = $_LANG["quotestage" . strtolower(str_replace(" ", "", $stage))];
    if (!$translation) {
        $translation = $stage;
    }
    return $translation;
}
