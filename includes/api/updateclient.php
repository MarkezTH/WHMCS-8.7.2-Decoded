<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getClientsDetails")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
if (!function_exists("saveCustomFields")) {
    require ROOTDIR . "/includes/customfieldfunctions.php";
}
$apiresults = [];
$whmcs = App::self();
$skipValidation = App::getFromRequest("skipvalidation");
$customFields = App::getFromRequest("customfields");
$clientId = App::getFromRequest("clientid");
$clientip = App::getFromRequest("clientip");
$clientEmail = App::getFromRequest("clientemail");
try {
    if (!empty($clientEmail)) {
        $client = WHMCS\User\Client::where("email", $clientEmail)->firstOrFail();
        $clientId = $client->id;
    } else {
        $client = WHMCS\User\Client::findOrFail($clientId);
    }
} catch (Exception $e) {
    $apiresults = ["result" => "error", "message" => "Client ID Not Found"];
    return NULL;
}
if (App::isInRequest("email_preferences", "domain")) {
    try {
        $client->validateEmailPreferences(App::getFromRequest("email_preferences"));
    } catch (WHMCS\Exception\Validation\Required $e) {
        $apiresults = ["result" => "error", "message" => "You must have at least one email address enabled to receive domain related notifications as required by ICANN. To disable domain notifications, please create an alternative contact that is set to receive them"];
        return NULL;
    } catch (Exception $e) {
        $apiresults = ["result" => "error", "message" => $e->getMessage()];
        return NULL;
    }
}
$cardDataPresent = (bool) App::getFromRequest("cardtype");
$bankDataPresent = (bool) App::getFromRequest("bankcode");
$clearCardData = (bool) App::getFromRequest("clearcreditcard");
if ($clearCardData) {
    $cardDataPresent = false;
}
$ccPayMethods = NULL;
$bankPayMethods = NULL;
if ($cardDataPresent || $bankDataPresent) {
    $clientPayMethods = $client->payMethods;
    if ($cardDataPresent) {
        $ccPayMethods = $clientPayMethods->filter(function (WHMCS\Payment\PayMethod\Model $payMethod) {
            return $payMethod->payment->isLocalCreditCard();
        });
        if (1 < $ccPayMethods->count()) {
            $apiresults = ["result" => "error", "message" => "Multiple Credit Card Pay Methods Found"];
            return NULL;
        }
    }
    if ($bankDataPresent) {
        $bankPayMethods = $clientPayMethods->filter(function (WHMCS\Payment\PayMethod\Model $payMethod) {
            return $payMethod->isBankAccount();
        });
        if (1 < $bankPayMethods->count()) {
            $apiresults = ["result" => "error", "message" => "Multiple Bank Account Pay Methods Found"];
            return NULL;
        }
    }
    $apiresults["warning"] = "Credit card related parameters are now deprecated and may be removed in a future version. Use AddPayMethod or UpdatePayMethod instead.";
}
if ((App::getFromRequest("clearcreditcard") || App::getFromRequest("cardtype")) && !function_exists("updateCCDetails")) {
    require ROOTDIR . "/includes/ccfunctions.php";
}
if ($cardDataPresent && $ccPayMethods) {
    if (0 < $ccPayMethods->count()) {
        $payMethod = $ccPayMethods->offsetGet(0);
    } else {
        $payMethod = WHMCS\Payment\PayMethod\Adapter\CreditCard::factoryPayMethod($client, $client, "New Card");
    }
    updateCCDetails($clientId, App::getFromRequest("cardtype"), App::getFromRequest("cardnum"), App::getFromRequest("cvv"), App::getFromRequest("expdate"), App::getFromRequest("startdate"), App::getFromRequest("issuenumber"), "", "", "", $payMethod);
}
if ($bankDataPresent && $bankPayMethods) {
    if (0 < $bankPayMethods->count()) {
        $payMethod = $bankPayMethods->offsetGet(0);
    } else {
        $payMethod = WHMCS\Payment\PayMethod\Adapter\BankAccount::factoryPayMethod($client, $client, "New Account");
    }
    $payment = $payMethod->payment;
    $payment->setRoutingNumber(App::getFromRequest("bankcode"));
    $payment->setAccountNumber(App::getFromRequest("bankacct"));
    $payment->save();
}
if (App::getFromRequest("clearcreditcard")) {
    $apiresults["warning"] = "Credit card related parameters are now deprecated and may be removed in a future version. Use DeletePayMethod instead.";
    updateCCDetails($clientId, "", "", "", "", "", "", "", true);
}
if (App::isInRequest("email")) {
    $newEmail = App::getFromRequest("email");
    if (filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false) {
        $apiresults = ["result" => "error", "message" => "The email address entered is not valid"];
        return NULL;
    }
    $clientCheck = WHMCS\User\Client::where("email", "=", $newEmail)->where("id", "!=", $clientId);
    $contactCheck = WHMCS\User\Client\Contact::where("email", "=", $newEmail)->where("subaccount", "=", "1");
    if (0 < $clientCheck->count() || 0 < $contactCheck->count()) {
        $apiresults = ["result" => "error", "message" => "Duplicate Email Address"];
        return NULL;
    }
}
$oldClientsDetails = getClientsDetails($clientId);
unset($oldClientsDetails["cctype"]);
unset($oldClientsDetails["cclastfour"]);
unset($oldClientsDetails["gatewayid"]);
$fieldsArray = ["firstname", "lastname", "companyname", "email", "address1", "address2", "city", "state", "postcode", "country", "phonenumber", "tax_id", "credit", "taxexempt", "notes", "status", "language", "currency", "groupid", "taxexempt", "latefeeoveride", "overideduenotices", "billingcid", "separateinvoices", "disableautocc", "datecreated"];
$booleanValues = ["taxexempt", "latefeeoveride", "overideduenotices", "separateinvoices", "disableautocc"];
foreach ($fieldsArray as $fieldName) {
    if (App::isInRequest($fieldName)) {
        $value = App::getFromRequest($fieldName);
        if (in_array($fieldName, $booleanValues)) {
            $value = $value ? 1 : 0;
        }
        $client->{$fieldName} = $value;
    }
}
if (App::isInRequest("email_preferences")) {
    $client->setEmailPreferences(App::getFromRequest("email_preferences"));
}
if ($client->isDirty()) {
    $client->save();
}
if ($customFields) {
    $customFields = safe_unserialize(base64_decode($customFields));
    if (!$skipValidation) {
        $validate = new WHMCS\Validate();
        $validate->validateCustomFields("client", "", false, $customFields);
        $customFieldsErrors = $validate->getErrors();
        if (count($customFieldsErrors)) {
            $error = implode(", ", $customFieldsErrors);
            $apiresults = ["result" => "error", "message" => $error];
            return NULL;
        }
    }
    saveCustomFields($clientId, $customFields, "client", true);
}
if (App::isInRequest("paymentmethod")) {
    $gateway = new WHMCS\Module\Gateway();
    $paymentMethod = App::getFromRequest("paymentmethod");
    if ($gateway->isActiveGateway($paymentMethod)) {
        clientChangeDefaultGateway($clientId, $paymentMethod);
    }
}
if (App::isInRequest("marketingoptin")) {
    $optInStatus = (bool) App::getFromRequest("marketingoptin");
    try {
        if (!$client->marketingEmailsOptIn && $optInStatus) {
            $client->marketingEmailOptIn($clientip);
        } else {
            if ($client->marketingEmailsOptIn && !$optInStatus) {
                $client->marketingEmailOptOut($clientip);
            }
        }
    } catch (Exception $e) {
    }
}
if (WHMCS\Config\Setting::getValue("TaxEUTaxValidation")) {
    $taxExempt = WHMCS\Billing\Tax\Vat::setTaxExempt($client);
    $client->save();
}
$newClientsDetails = getClientsDetails($clientId);
unset($newClientsDetails["cctype"]);
unset($newClientsDetails["cclastfour"]);
unset($newClientsDetails["gatewayid"]);
$hookValues = array_merge(["userid" => $clientId, "isOptedInToMarketingEmails" => $client->isOptedInToMarketingEmails(), "olddata" => $oldClientsDetails], $newClientsDetails);
HookMgr::run("ClientEdit", $hookValues);
$updateFieldsArray = ["firstname" => "First Name", "lastname" => "Last Name", "companyname" => "Company Name", "email" => "Email Address", "address1" => "Address 1", "address2" => "Address 2", "city" => "City", "state" => "State", "postcode" => "Postcode", "country" => "Country", "phonenumber" => "Phone Number", "billingcid" => "Billing Contact", "groupid" => "Client Group", "language" => "Language", "currency" => "Currency", "status" => "Status", "defaultgateway" => "Default Payment Method"];
$updatedTickBoxArray = ["latefeeoveride" => "Late Fees Override", "overideduenotices" => "Overdue Notices", "taxexempt" => "Tax Exempt", "separateinvoices" => "Separate Invoices", "disableautocc" => "Disable CC Processing", "marketing_emails_opt_in" => "Marketing Emails Opt-in", "overrideautoclose" => "Auto Close", "generalemails" => "General Emails", "productemails" => "Product Emails", "domainemails" => "Domain Emails", "invoiceemails" => "Invoice Emails", "supportemails" => "Support Emails", "affiliateemails" => "Affiliate Emails"];
$changeList = [];
foreach ($newClientsDetails as $key => $value) {
    if (in_array($key, array_merge(array_keys($updateFieldsArray), array_keys($updatedTickBoxArray)))) {
        if (in_array($key, array_keys($updateFieldsArray)) && $value != $oldClientsDetails[$key]) {
            $oldValue = $oldClientsDetails[$key];
            $newValue = $value;
            $log = true;
            if ($key == "groupid") {
                $oldValue = $oldValue ? WHMCS\Database\Capsule::table("tblclientgroups")->where("id", "=", $oldValue)->value("groupname") : AdminLang::trans("global.none");
                $newValue = $newValue ? WHMCS\Database\Capsule::table("tblclientgroups")->where("id", "=", $newValue)->value("groupname") : AdminLang::trans("global.none");
            } else {
                if ($key == "currency") {
                    $oldCurrency = WHMCS\Billing\Currency::find($oldValue);
                    $newCurrency = WHMCS\Billing\Currency::find($newValue);
                    $oldValue = $oldCurrency ? $oldCurrency->code : "";
                    $newValue = $newCurrency ? $newCurrency->code : "";
                }
            }
            if ($log) {
                $changeList[] = $updateFieldsArray[$key] . ": '" . $oldValue . "' to '" . $newValue . "'";
            }
        } else {
            if (in_array($key, array_keys($updatedTickBoxArray))) {
                if ($key == "overideduenotices") {
                    $oldField = $oldClientsDetails[$key] ? "Disabled" : "Enabled";
                    $newField = $value ? "Disabled" : "Enabled";
                } else {
                    $oldField = $oldClientsDetails[$key] ? "Enabled" : "Disabled";
                    $newField = $value ? "Enabled" : "Disabled";
                }
                if ($oldField != $newField) {
                    $changeList[] = $updatedTickBoxArray[$key] . ": '" . $oldField . "' to '" . $newField . "'";
                }
            }
        }
    }
}
if (!count($changeList)) {
    $changeList[] = "No Changes";
}
$changes = implode(", ", $changeList);
logActivity("Client Profile Modified - " . $changes . " - User ID: " . $clientId, $clientId);
$apiresults = array_merge($apiresults, ["result" => "success", "clientid" => $clientId]);
