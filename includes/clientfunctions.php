<?php

use WHMCS\Application\Support\Facades\Auth;
use WHMCS\Application\Support\Facades\Lang;

define("CHECKDETAILS_EMAIL_NONE", 0);
define("CHECKDETAILS_EMAIL_ALL", PHP_INT_MAX);
define("CHECKDETAILS_EMAIL_REQUIRED", 2);
define("CHECKDETAILS_EMAIL_VALID", 4);
define("CHECKDETAILS_EMAIL_UNIQUE_USER", 8);
define("CHECKDETAILS_EMAIL_UNIQUE_CLIENT", 16);
define("CHECKDETAILS_EMAIL_BANNED_DOMAIN", 32);
define("CHECKDETAILS_EMAIL_ASSOC_CLIENT", 64);
function getClientsDetails($userid = "", $contactid = "")
{
    if (!$userid) {
        $authClient = Auth::client();
        if (!$authClient) {
            throw new WHMCS\Exception\Authentication\ClientRequired();
        }
        $userid = $authClient->id;
    }
    $client = new WHMCS\Client($userid);
    $details = $client->getDetails($contactid);
    return $details;
}

function getClientsStats($userid, WHMCS\User\Client $client = NULL)
{
    global $currency;
    $currency = getCurrency($userid);
    $stats = [];
    if (is_null($client) || $client->id != $userid) {
        $client = WHMCS\User\Client::find($userid);
    }
    $invoiceTypeItemInvoiceIds = WHMCS\Database\Capsule::table("tblinvoiceitems")->where("userid", $userid)->where("type", "Invoice")->pluck("invoiceid")->all();
    $invoiceAddFundsTypeItemInvoiceIds = WHMCS\Database\Capsule::table("tblinvoiceitems")->where("userid", $userid)->whereIn("type", ["AddFunds", "Invoice"])->pluck("invoiceid")->all();
    $invoicesData = WHMCS\Database\Capsule::table("tblinvoices")->where("tblinvoices.userid", $userid)->where("status", "Unpaid")->leftJoin("tblaccounts", "tblaccounts.invoiceid", "=", "tblinvoices.id")->whereNotIn("tblinvoices.id", $invoiceTypeItemInvoiceIds)->groupBy("tblinvoices.id")->get([WHMCS\Database\Capsule::raw("IFNULL(total, 0) as total"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountin), 0) as amount_in"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountout), 0) as amount_out")])->all();
    $stats["numdueinvoices"] = count($invoicesData);
    $invoicesData = collect($invoicesData);
    $stats["dueinvoicesbalance"] = formatCurrency($invoicesData->sum(function ($invoiceData) {
        return $invoiceData->total - $invoiceData->amount_in + $invoiceData->amount_out;
    }));
    $stats["incredit"] = $client ? 0 < $client->credit : false;
    $stats["creditbalance"] = formatCurrency($client ? $client->credit : 0);
    $transactionsData = WHMCS\Database\Capsule::table("tblaccounts")->where("userid", $userid)->first([WHMCS\Database\Capsule::raw("IFNULL(SUM(fees), 0) as fees"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountin), 0) as amount_in"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountout), 0) as amount_out")]);
    $stats["grossRevenue"] = formatCurrency($transactionsData->amount_in);
    $stats["expenses"] = formatCurrency($transactionsData->fees + $transactionsData->amount_out);
    $stats["income"] = formatCurrency($transactionsData->amount_in - $transactionsData->fees - $transactionsData->amount_out);
    $overDueInvoices = WHMCS\Database\Capsule::table("tblinvoices")->where("tblinvoices.userid", $userid)->where("status", "Unpaid")->where("duedate", "<", WHMCS\Carbon::today()->toDateTimeString())->leftJoin("tblaccounts", "tblaccounts.invoiceid", "=", "tblinvoices.id")->whereNotIn("tblinvoices.id", $invoiceTypeItemInvoiceIds)->groupBy("tblinvoices.id")->get([WHMCS\Database\Capsule::raw("IFNULL(total, 0) as total"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountin), 0) as amount_in"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountout), 0) as amount_out")])->all();
    $stats["numoverdueinvoices"] = count($overDueInvoices);
    $overDueInvoices = collect($overDueInvoices);
    $stats["overdueinvoicesbalance"] = formatCurrency($overDueInvoices->sum(function ($invoiceData) {
        return $invoiceData->total - $invoiceData->amount_in + $invoiceData->amount_out;
    }));
    $invoicesData = WHMCS\Database\Capsule::table("tblinvoices")->where("tblinvoices.userid", $userid)->where("status", "Draft")->leftJoin("tblaccounts", "tblaccounts.invoiceid", "=", "tblinvoices.id")->whereNotIn("tblinvoices.id", $invoiceTypeItemInvoiceIds)->groupBy("tblinvoices.id")->get([WHMCS\Database\Capsule::raw("IFNULL(total, 0) as total"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountin), 0) as amount_in"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountout), 0) as amount_out")])->all();
    $stats["numDraftInvoices"] = count($invoicesData);
    $invoicesData = collect($invoicesData);
    $stats["draftInvoicesBalance"] = formatCurrency($invoicesData->sum(function ($invoiceData) {
        return $invoiceData->total - $invoiceData->amount_in + $invoiceData->amount_out;
    }));
    $invoiceStatus = [WHMCS\Billing\Invoice::STATUS_UNPAID => ["invoice_count" => 0, "total" => 0, "credit" => 0], WHMCS\Billing\Invoice::STATUS_PAID => ["invoice_count" => 0, "total" => 0, "credit" => 0], WHMCS\Billing\Invoice::STATUS_CANCELLED => ["invoice_count" => 0, "total" => 0, "credit" => 0], WHMCS\Billing\Invoice::STATUS_REFUNDED => ["invoice_count" => 0, "total" => 0, "credit" => 0], WHMCS\Billing\Invoice::STATUS_COLLECTIONS => ["invoice_count" => 0, "total" => 0, "credit" => 0], WHMCS\Billing\Invoice::STATUS_PAYMENT_PENDING => ["invoice_count" => 0, "total" => 0, "credit" => 0]];
    $invoiceData = WHMCS\Database\Capsule::table("tblinvoices")->where("userid", $userid)->whereNotIn("tblinvoices.id", $invoiceAddFundsTypeItemInvoiceIds)->groupBy("status")->get(["status", WHMCS\Database\Capsule::raw("count(tblinvoices.id) as invoice_count"), WHMCS\Database\Capsule::raw("SUM(IFNULL(total, 0)) as total"), WHMCS\Database\Capsule::raw("SUM(IFNULL(credit, 0)) as credit")])->all();
    foreach ($invoiceData as $invoiceDatum) {
        $invoiceStatus[$invoiceDatum->status]["invoice_count"] = $invoiceDatum->invoice_count;
        $invoiceStatus[$invoiceDatum->status]["total"] = $invoiceDatum->total;
        $invoiceStatus[$invoiceDatum->status]["credit"] = $invoiceDatum->credit;
    }
    foreach ($invoiceStatus as $status => $invoiceCounts) {
        $statusKey = strtolower(str_replace(" ", "", $status));
        $key = "num" . $statusKey . "invoices";
        $stats[$key] = $invoiceCounts["invoice_count"];
        $key = $statusKey . "invoicesamount";
        $value = $invoiceCounts["total"];
        if ($status == "Paid") {
            $value += $invoiceCounts["credit"];
        }
        $stats[$key] = formatCurrency($value);
    }
    $productstats = [];
    $result = full_query("SELECT tblproducts.type,domainstatus,COUNT(*) FROM tblhosting INNER JOIN tblproducts ON tblhosting.packageid=tblproducts.id WHERE tblhosting.userid=" . (int) $userid . " GROUP BY domainstatus,tblproducts.type");
    while ($data = mysql_fetch_array($result)) {
        $productstats[$data[0]][$data[1]] = $data[2];
    }
    $stats["productsnumactivehosting"] = isset($productstats["hostingaccount"]["Active"]) ? $productstats["hostingaccount"]["Active"] : 0;
    $stats["productsnumhosting"] = 0;
    if (array_key_exists("hostingaccount", $productstats) && is_array($productstats["hostingaccount"])) {
        foreach ($productstats["hostingaccount"] as $status => $count) {
            $stats["productsnumhosting"] += $count;
        }
    }
    $stats["productsnumactivereseller"] = isset($productstats["reselleraccount"]["Active"]) ? $productstats["reselleraccount"]["Active"] : 0;
    $stats["productsnumreseller"] = 0;
    if (array_key_exists("reselleraccount", $productstats) && is_array($productstats["reselleraccount"])) {
        foreach ($productstats["reselleraccount"] as $status => $count) {
            $stats["productsnumreseller"] += $count;
        }
    }
    $stats["productsnumactiveservers"] = isset($productstats["server"]["Active"]) ? $productstats["server"]["Active"] : 0;
    $stats["productsnumservers"] = 0;
    if (array_key_exists("server", $productstats) && is_array($productstats["server"])) {
        foreach ($productstats["server"] as $status => $count) {
            $stats["productsnumservers"] += $count;
        }
    }
    $stats["productsnumactiveother"] = isset($productstats["other"]["Active"]) ? $productstats["other"]["Active"] : 0;
    $stats["productsnumother"] = 0;
    if (array_key_exists("other", $productstats) && is_array($productstats["other"])) {
        foreach ($productstats["other"] as $status => $count) {
            $stats["productsnumother"] += $count;
        }
    }
    $stats["productsnumactive"] = $stats["productsnumactivehosting"] + $stats["productsnumactivereseller"] + $stats["productsnumactiveservers"] + $stats["productsnumactiveother"];
    $stats["productsnumtotal"] = $stats["productsnumhosting"] + $stats["productsnumreseller"] + $stats["productsnumservers"] + $stats["productsnumother"];
    $domainstats = [];
    $result = select_query("tbldomains", "status,COUNT(*)", "userid=" . (int) $userid . " GROUP BY status");
    while ($data = mysql_fetch_array($result)) {
        $domainstats[$data[0]] = $data[1];
    }
    $stats["numactivedomains"] = isset($domainstats["Active"]) ? $domainstats["Active"] : 0;
    $stats["numdomains"] = 0;
    foreach ($domainstats as $count) {
        $stats["numdomains"] += $count;
    }
    $quotestats = [];
    $result = select_query("tblquotes", "stage,COUNT(*)", "userid=" . (int) $userid . " GROUP BY stage");
    while ($data = mysql_fetch_array($result)) {
        $quotestats[$data[0]] = $data[1];
    }
    $stats["numacceptedquotes"] = isset($quotestats["Accepted"]) ? $quotestats["Accepted"] : 0;
    $stats["numquotes"] = 0;
    foreach ($quotestats as $count) {
        $stats["numquotes"] += $count;
    }
    $statusfilter = [];
    $result = select_query("tblticketstatuses", "title", ["showactive" => "1"]);
    while ($data = mysql_fetch_array($result)) {
        $statusfilter[] = $data[0];
    }
    $ticketstats = [];
    $result = select_query("tbltickets", "status,COUNT(*)", "userid=" . (int) $userid . " AND merged_ticket_id = 0 GROUP BY status");
    while ($data = mysql_fetch_array($result)) {
        $ticketstats[$data[0]] = $data[1];
    }
    $stats["numtickets"] = 0;
    $stats["numactivetickets"] = $stats["numtickets"];
    foreach ($ticketstats as $status => $count) {
        if (in_array($status, $statusfilter)) {
            $stats["numactivetickets"] += $count;
        }
        $stats["numtickets"] += $count;
    }
    $result = select_query("tblaffiliatesaccounts", "COUNT(*)", ["clientid" => $userid], "", "", "", "tblaffiliates ON tblaffiliatesaccounts.affiliateid=tblaffiliates.id");
    $data = mysql_fetch_array($result);
    $stats["numaffiliatesignups"] = $data[0];
    $stats["isAffiliate"] = get_query_val("tblaffiliates", "id", ["clientid" => (int) $userid]) ? true : false;
    return $stats;
}

function getCountriesDropDown($selected = "", $fieldname = "", $tabindex = "", $selectInline = true, $disable = false)
{
    global $CONFIG;
    global $_LANG;
    if (!$selected) {
        $selected = $CONFIG["DefaultCountry"];
    }
    if (!$fieldname) {
        $fieldname = "country";
    }
    if ($tabindex) {
        $tabindex = " tabindex=\"" . $tabindex . "\"";
    }
    if ($disable) {
        $disable = " disabled";
    } else {
        $disable = "";
    }
    $countries = new WHMCS\Utility\Country();
    $selectInlineClass = $selectInline ? " select-inline" : "";
    $dropdowncode = "<select name=\"" . $fieldname . "\" id=\"" . $fieldname . "\" class=\"form-control custom-select" . $selectInlineClass . "\"" . $tabindex . $disable . ">";
    foreach ($countries->getCountryNameArray() as $countriesvalue1 => $countriesvalue2) {
        $dropdowncode .= "<option value=\"" . $countriesvalue1 . "\"";
        if ($countriesvalue1 == $selected) {
            $dropdowncode .= " selected=\"selected\"";
        }
        $dropdowncode .= ">" . $countriesvalue2 . "</option>";
    }
    $dropdowncode .= "</select>";
    if ($countries->hasCountryOverride() && WHMCS\Config\Setting::getValue("PhoneNumberDropdown")) {
        $overrides = file_get_contents($countries->countryOverrideFilepath());
        $dropdowncode .= "<script>\nif (typeof customCountryData === \"undefined\") {\n    var customCountryData = " . $overrides . ";\n}\n</script>";
    }
    return $dropdowncode;
}

function checkDetailsareValid($uid = "", $signup = false, $emailFlags = CHECKDETAILS_EMAIL_ALL, $captcha = true, $checkcustomfields = true, $checkClientsProfileUneditiableFields = false, $checkPassword = false, $checkSecurityQuestions = false, $checkTermsOfService = false)
{
    $whmcs = DI::make("app");
    $validate = new WHMCS\Validate();
    $emailCheckActive = function ($flag) use ($emailFlags) {
        return ($emailFlags & $flag) == $flag || ($emailFlags & CHECKDETAILS_EMAIL_ALL) == CHECKDETAILS_EMAIL_ALL;
    };
    if ($emailFlags === true) {
        $emailFlags = CHECKDETAILS_EMAIL_ALL;
    } else {
        if ($emailFlags === false) {
            $emailFlags = CHECKDETAILS_EMAIL_NONE;
        }
    }
    if ($signup === true) {
        $checkClientsProfileUneditiableFields = false;
        $emailFlags = CHECKDETAILS_EMAIL_ALL;
        $checkPassword = true;
        $checkSecurityQuestions = true;
        $checkTermsOfService = true;
    } else {
        if ($signup === false) {
            $checkClientsProfileUneditiableFields = true;
            $checkPassword = false;
            $checkSecurityQuestions = false;
            $checkTermsOfService = false;
            $captcha = false;
        }
    }
    $validate->addOptionalFields($whmcs->get_config("ClientsProfileOptionalFields"));
    if ($checkClientsProfileUneditiableFields) {
        $clientsProfileUneditableFields = $whmcs->get_config("ClientsProfileUneditableFields");
        if ($whmcs->isApiRequest() || $emailFlags != CHECKDETAILS_EMAIL_NONE) {
            $clientsProfileUneditableFields = preg_replace("/email,?/i", "", $clientsProfileUneditableFields);
        }
        if ($clientsProfileUneditableFields) {
            $validate->addOptionalFields($clientsProfileUneditableFields);
        }
        unset($clientsProfileUneditableFields);
    }
    $validate->validate("required", "firstname", "clientareaerrorfirstname");
    $validate->validate("required", "lastname", "clientareaerrorlastname");
    $continue = true;
    if ($emailCheckActive(CHECKDETAILS_EMAIL_REQUIRED)) {
        $continue = $validate->validate("required", "email", "clientareaerroremail");
    }
    if ($continue && $emailCheckActive(CHECKDETAILS_EMAIL_VALID)) {
        $continue = $validate->validate("email", "email", "clientareaerroremailinvalid");
    }
    if ($continue && $emailCheckActive(CHECKDETAILS_EMAIL_BANNED_DOMAIN)) {
        $continue = $validate->validate("banneddomain", "email", "clientareaerrorbannedemail");
    }
    if ($continue && $emailCheckActive(CHECKDETAILS_EMAIL_UNIQUE_USER)) {
        $continue = $validate->validate("uniqueemail", "email", "ordererroruserexists", [$uid, ""]);
    }
    if ($continue && $emailCheckActive(CHECKDETAILS_EMAIL_ASSOC_CLIENT)) {
        $continue = $validate->validate("assocuser", "email", "clientareaerrorusernotassoc", [$uid, ""]);
    }
    if ($continue && $emailCheckActive(CHECKDETAILS_EMAIL_UNIQUE_CLIENT)) {
        $validate->validate("uniqueclient", "email", "clientareaerroremailexists", [$uid, ""]);
    }
    unset($continue);
    if ($validate->validate("required", "phonenumber", "clientareaerrorphonenumber")) {
        $validate->validate("phone", "phonenumber", "clientareaerrorphonenumber2");
    }
    $validate->validate("required", "address1", "clientareaerroraddress1");
    $validate->validate("required", "city", "clientareaerrorcity");
    $validate->validate("required", "state", "clientareaerrorstate");
    $validate->validate("required", "postcode", "clientareaerrorpostcode");
    $validate->validate("postcode", "postcode", "clientareaerrorpostcode2");
    $validate->validate("language", "accountLanguage", "clientareaerrorlanguage");
    $countryError = "clientareaerrorcountry";
    if (App::isApiRequest()) {
        $countryError = "api.client.countryError";
    }
    $validate->validate("country", "country", $countryError);
    validateTaxId($validate, App::getFromRequest("country"), App::getFromRequest(WHMCS\Billing\Tax\Vat::getFieldName()));
    if (!WHMCS\Config\Setting::getValue("DisableClientEmailPreferences") && App::isInRequest("email_preferences")) {
        $client = WHMCS\User\Client::findOrFail($uid);
        try {
            $client->validateEmailPreferences(App::getFromRequest("email_preferences"));
        } catch (WHMCS\Exception\Validation\Required $e) {
            $validate->addError(Lang::trans("emailPreferences.oneRequired") . " " . Lang::trans($e->getMessage()));
        } catch (Exception $e) {
            $validate->addError("An Unknown Error Occurred");
        }
    }
    if ($checkPassword && $validate->validate("required", "password", "ordererrorpassword") && $validate->validate("pwstrength", "password", "pwstrengthfail") && $validate->validate("required", "password2", "clientareaerrorpasswordconfirm")) {
        $validate->validate("match_value", "password", "clientareaerrorpasswordnotmatch", "password2");
    }
    if ($checkcustomfields) {
        $validate->validateCustomFields("client", 0);
    }
    $securityQuestions = getSecurityQuestions();
    if ($securityQuestions && $checkSecurityQuestions) {
        $validate->validate("inarray", "securityqid", "securityquestionrequired", array_column($securityQuestions, "id"));
        $validate->validate("required", "securityqans", "securityanswerrequired");
    }
    if ($captcha) {
        $captchaCheck = new WHMCS\Utility\Captcha();
        $captchaCheck->validateAppropriateCaptcha(WHMCS\Utility\Captcha::FORM_REGISTRATION, $validate);
    }
    if ($checkTermsOfService && $whmcs->get_config("EnableTOSAccept")) {
        $validate->validate("required", "accepttos", "ordererroraccepttos");
    }
    run_validate_hook($validate, "ClientDetailsValidation", $_POST);
    $errormessage = $validate->getHTMLErrorOutput();
    return $errormessage;
}

function validateContactDetails($cid = "", $reqpw = false, $prefix = "")
{
    global $whmcs;
    $validate = new WHMCS\Validate();
    $contact = $cid ? WHMCS\User\Client\Contact::find($cid) : NULL;
    $validate->addOptionalFields($whmcs->get_config("ClientsProfileOptionalFields"));
    $validate->validate("required", $prefix . "firstname", "clientareaerrorfirstname");
    $validate->validate("required", $prefix . "lastname", "clientareaerrorlastname");
    if ($validate->validate("required", $prefix . "email", "clientareaerroremail") && $validate->validate("email", $prefix . "email", "clientareaerroremailinvalid") && (is_null($contact) || $contact->email !== App::getFromRequest("email"))) {
        $validate->validate("banneddomain", $prefix . "email", "clientareaerrorbannedemail");
    }
    $validate->validate("required", $prefix . "address1", "clientareaerroraddress1");
    $validate->validate("required", $prefix . "city", "clientareaerrorcity");
    $validate->validate("required", $prefix . "state", "clientareaerrorstate");
    $validate->validate("required", $prefix . "postcode", "clientareaerrorpostcode");
    $validate->validate("postcode", $prefix . "postcode", "clientareaerrorpostcode2");
    if ($validate->validate("required", $prefix . "phonenumber", "clientareaerrorphonenumber")) {
        $validate->validate("phone", $prefix . "phonenumber", "clientareaerrorphonenumber2");
    }
    $validate->validate("country", $prefix . "country", "clientareaerrorcountry");
    validateTaxId($validate, App::getFromRequest($prefix . "country"), App::getFromRequest($prefix . "tax_id"));
    if ($contact && !WHMCS\Config\Setting::getValue("DisableClientEmailPreferences")) {
        try {
            $contact->validateEmailPreferences(App::getFromRequest("email_preferences"));
        } catch (WHMCS\Exception\Validation\Required $e) {
            $validate->addError(Lang::trans("emailPreferences.oneRequired") . " " . Lang::trans("emailPreferences.domainContactRequired"));
        } catch (Exception $e) {
            $validate->addError("An Unknown Error Occurred");
        }
    }
    run_validate_hook($validate, "ContactDetailsValidation", $_POST);
    return $validate;
}

function checkContactDetails($cid = "", $reqpw = false, $prefix = "")
{
    return validatecontactdetails($cid, $reqpw, $prefix)->getHTMLErrorOutput();
}

function validateTaxId($validate, $countryCode, $taxId)
{
    if (strlen($countryCode) == 0 || strlen($taxId) == 0 || !array_key_exists($countryCode, WHMCS\Billing\Tax\Vat::EU_COUNTRIES) || !WHMCS\Billing\Tax\Vat::isTaxIdEnabled() || !WHMCS\Config\Setting::getValue("TaxEUTaxValidation")) {
        return NULL;
    }
    try {
        if (!WHMCS\Billing\Tax\Vat::validateNumber($taxId)) {
            $validate->addError(Lang::trans("tax.errorInvalid", [":taxLabel" => Lang::trans(WHMCS\Billing\Tax\Vat::getLabel())]));
        }
    } catch (Throwable $e) {
        $validate->addError(Lang::trans("tax.errorService", [":taxLabel" => Lang::trans(WHMCS\Billing\Tax\Vat::getLabel())]));
    }
}

function addClient($user, $firstname, $lastname, $companyname, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber = true, $sendemail = [], $additionalData = "", $uuid = false, $isAdmin = NULL, $marketingOptIn = NULL, $clientIp = NULL, $language)
{
    global $whmcs;
    if (!$country) {
        $country = $whmcs->get_config("DefaultCountry");
    }
    if (!$uuid) {
        $uuid = Ramsey\Uuid\Uuid::uuid4()->toString();
    }
    if (!$clientIp) {
        $clientIp = WHMCS\Utility\Environment\CurrentRequest::getIP();
    }
    if (defined("ADMINAREA")) {
        $isAdmin = true;
    }
    $taxId = "";
    if (isset($additionalData["tax_id"])) {
        $taxId = $additionalData["tax_id"];
    }
    $fullhost = gethostbyaddr($clientIp);
    $currency = WHMCS\Billing\Currency::factoryForClientArea();
    $selectLanguage = function ($language) {
        if ($language) {
            return $language;
        }
        return Lang::getDefault() != Lang::getName() ? Lang::getName() : "";
    };
    $email = trim($email);
    $table = "tblclients";
    $array = ["uuid" => $uuid, "firstname" => $firstname, "lastname" => $lastname, "companyname" => $companyname, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber, "tax_id" => $taxId, "password" => "", "lastlogin" => "now()", "ip" => $clientIp, "host" => $fullhost, "status" => "Active", "datecreated" => "now()", "language" => $selectLanguage($language), "currency" => $currency["id"], "email_verified" => 0, "email_preferences" => json_encode(WHMCS\User\Client::$emailPreferencesDefaults), "created_at" => "now()"];
    $clientId = insert_query($table, $array);
    logActivity("Created Client " . $firstname . " " . $lastname, $clientId, ["withClientId" => true, "addUserId" => $user->id]);
    $user->clients()->attach($clientId, ["owner" => true]);
    $client = $user->getClient($clientId);
    if (!empty($additionalData)) {
        $legacyBooleanColumns = ["taxexempt", "latefeeoveride", "overideduenotices", "separateinvoices", "disableautocc", "emailoptout", "overrideautoclose"];
        foreach ($legacyBooleanColumns as $column) {
            if (isset($additionalData[$column])) {
                $additionalData[$column] = (bool) $additionalData[$column];
            }
        }
        if (!empty($additionalData["credit"]) && $additionalData["credit"] <= 0) {
            unset($additionalData["credit"]);
        }
        $tableData = $additionalData;
        if (isset($tableData["customfields"])) {
            unset($tableData["customfields"]);
        }
        if (WHMCS\Billing\Tax\Vat::isTaxIdDisabled() || !WHMCS\Billing\Tax\Vat::isUsingNativeField()) {
            unset($tableData["tax_id"]);
        }
        if (is_array($tableData) && !empty($tableData)) {
            WHMCS\Database\Capsule::table("tblclients")->where("id", $client->id)->update($tableData);
        }
        if (!empty($tableData["credit"])) {
            WHMCS\Database\Capsule::table("tblcredit")->insert(["clientid" => $client->id, "date" => WHMCS\Carbon::now()->format("Y-m-d"), "description" => "Opening Credit Balance", "amount" => $tableData["credit"]]);
        }
    }
    if (!function_exists("saveCustomFields")) {
        require ROOTDIR . "/includes/customfieldfunctions.php";
    }
    $customFields = $whmcs->get_req_var("customfield");
    if (empty($customFields) && !empty($additionalData["customfields"])) {
        $customFields = $additionalData["customfields"];
    }
    saveCustomFields($client->id, $customFields, "client", $isAdmin);
    if (!is_null($marketingOptIn)) {
        if ($marketingOptIn) {
            $client->marketingEmailOptIn($clientIp, false);
        } else {
            $client->marketingEmailOptOut($clientIp, false);
        }
    }
    if ($sendemail) {
        sendMessage("Client Signup Email", $client->id, ["client_password" => ""]);
    }
    if (WHMCS\Config\Setting::getValue("TaxEUTaxValidation")) {
        $taxExempt = WHMCS\Billing\Tax\Vat::setTaxExempt($client);
        $client->save();
        if ($taxExempt != ($additionalData["taxexempt"] ?? NULL)) {
            $additionalData["taxexempt"] = $taxExempt;
        }
    }
    if (!defined("APICALL")) {
        run_hook("ClientAdd", array_merge(["client_id" => $client->id, "user_id" => $user->id, "userid" => $client->id, "firstname" => $firstname, "lastname" => $lastname, "companyname" => $companyname, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber, "password" => ""], $additionalData, ["customfields" => $customFields]));
    }
    return $client;
}

function addContact($userid, $firstname, $lastname, $companyname, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber, $generalemails = "", $productemails = "", $domainemails = "", $invoiceemails = "", $supportemails = "", $affiliateemails = "", $taxId = "")
{
    if (!$country) {
        $country = WHMCS\Config\Setting::getValue("DefaultCountry");
    }
    $table = "tblcontacts";
    $array = ["userid" => $userid, "firstname" => $firstname, "lastname" => $lastname, "companyname" => $companyname, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber, "tax_id" => $taxId, "subaccount" => false, "password" => "", "permissions" => "", "generalemails" => $generalemails, "productemails" => $productemails, "domainemails" => $domainemails, "invoiceemails" => $invoiceemails, "supportemails" => $supportemails, "affiliateemails" => $affiliateemails];
    $contactid = insert_query($table, $array);
    run_hook("ContactAdd", array_merge($array, ["contactid" => $contactid]));
    logActivity("Added Contact - User ID: " . $userid . " - Contact ID: " . $contactid, $userid);
    return $contactid;
}

function deleteClient($userid)
{
    try {
        $client = WHMCS\User\Client::findOrFail($userid);
        $client->deleteEntireClient();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function getSecurityQuestions($questionid = "")
{
    if ($questionid) {
        $questions = WHMCS\User\User\SecurityQuestion::find($questionid);
    } else {
        $questions = WHMCS\User\User\SecurityQuestion::all();
    }
    $results = [];
    foreach ($questions as $question) {
        $results[] = ["id" => $question->id, "question" => $question->question];
    }
    return $results;
}

function generateClientPW($plain, $salt = "")
{
    if (!$salt) {
        $seeds = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ#!%()#!%()#!%()";
        $seeds_count = strlen($seeds) - 1;
        for ($i = 0; $i < 5; $i++) {
            $salt .= $seeds[rand(0, $seeds_count)];
        }
    }
    return md5($salt . WHMCS\Input\Sanitize::decode($plain)) . ":" . $salt;
}

function checkContactPermission($requiredPermission, $noRedirect = false)
{
    if (Auth::hasPermission($requiredPermission)) {
        return true;
    }
    if ($noRedirect) {
        return false;
    }
    header("Location: " . routePathWithQuery("user-permission-denied", [], []));
    exit;
}

function validateClientLogin($username, $password)
{
    $authentication = new WHMCS\Authentication\Client($username, $password);
    if ($authentication::isInSecondFactorRequestState()) {
        if (!$authentication->verifySecondFactor()) {
            return false;
        }
        $authentication->finalizeLogin();
        return true;
    }
    if ($authentication->verifyFirstFactor()) {
        if (!$authentication->needsSecondFactorToFinalize()) {
            $authentication->finalizeLogin();
            return true;
        }
        $authentication->prepareSecondFactor();
    }
    return false;
}

function createCancellationRequest($userid, $serviceid, $reason, $type)
{
    global $CONFIG;
    global $currency;
    $existing = get_query_val("tblcancelrequests", "COUNT(id)", ["relid" => $serviceid]);
    if ($existing == 0) {
        if (!in_array($type, ["Immediate", "End of Billing Period"])) {
            $type = "End of Billing Period";
        }
        insert_query("tblcancelrequests", ["date" => "now()", "relid" => $serviceid, "reason" => $reason, "type" => $type]);
        if ($type == "End of Billing Period") {
            logActivity("Automatic Cancellation Requested for End of Current Cycle - Service ID: " . $serviceid, $userid);
        } else {
            logActivity("Automatic Cancellation Requested Immediately - Service ID: " . $serviceid, $userid);
        }
        $data = WHMCS\Database\Capsule::table("tblhosting")->where("tblhosting.id", $serviceid)->join("tblproducts", "tblproducts.id", "=", "tblhosting.packageid")->first(["domain", "freedomain", "subscriptionid"]);
        $domain = $data->domain;
        $freedomain = $data->freedomain;
        $subscriptionId = $data->subscriptionid;
        if ($freedomain && $domain) {
            $data = get_query_vals("tbldomains", "id,recurringamount,registrationperiod,dnsmanagement,emailforwarding,idprotection", ["userid" => $userid, "domain" => $domain], "status", "ASC");
            $domainid = $data["id"];
            $recurringamount = $data["recurringamount"];
            $regperiod = $data["registrationperiod"];
            $dnsmanagement = $data["dnsmanagement"];
            $emailforwarding = $data["emailforwarding"];
            $idprotection = $data["idprotection"];
            if ($recurringamount <= 0) {
                $currency = getCurrency($userid);
                $result = select_query("tblpricing", "msetupfee,qsetupfee,ssetupfee", ["type" => "domainaddons", "currency" => $currency["id"], "relid" => 0]);
                $data = mysql_fetch_array($result);
                $domaindnsmanagementprice = $data["msetupfee"] * $regperiod;
                $domainemailforwardingprice = $data["qsetupfee"] * $regperiod;
                $domainidprotectionprice = $data["ssetupfee"] * $regperiod;
                $domainparts = explode(".", $domain, 2);
                if (!function_exists("getTLDPriceList")) {
                    require ROOTDIR . "/includes/domainfunctions.php";
                }
                $temppricelist = getTLDPriceList("." . $domainparts[1], "", true, $userid);
                $recurringamount = $temppricelist[$regperiod]["renew"];
                if ($dnsmanagement) {
                    $recurringamount += $domaindnsmanagementprice;
                }
                if ($emailforwarding) {
                    $recurringamount += $domainemailforwardingprice;
                }
                if ($idprotection) {
                    $recurringamount += $domainidprotectionprice;
                }
                update_query("tbldomains", ["recurringamount" => $recurringamount], ["id" => $domainid]);
            }
        }
        run_hook("CancellationRequest", ["userid" => $userid, "relid" => $serviceid, "reason" => $reason, "type" => $type]);
        if ($CONFIG["CancelInvoiceOnCancellation"]) {
            cancelUnpaidInvoicebyProductID($serviceid, $userid);
        }
        if (WHMCS\Config\Setting::getValue("AutoCancelSubscriptions") && $subscriptionId) {
            if (!function_exists("cancelSubscriptionForService")) {
                require ROOTDIR . "/includes/gatewayfunctions.php";
            }
            try {
                cancelSubscriptionForService($serviceid, $userid);
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }
        return "success";
    }
    return "Existing Cancellation Request Exists";
}

function recalcRecurringProductPrice($serviceid, $userid = "", $pid = "", $billingcycle = "", $configoptionsrecurring = "empty", $promoid = 0, $includesetup = false, $showHiddenOverride = false, $quantity = 0)
{
    $serviceData = WHMCS\Database\Capsule::table("tblhosting")->find($serviceid);
    if (!$userid) {
        $userid = $serviceData->userid;
    }
    if (!$pid) {
        $pid = $serviceData->packageid;
    }
    if (!$billingcycle) {
        $billingcycle = $serviceData->billingcycle;
    }
    if (!$quantity) {
        $quantity = (double) $serviceData->qty;
    }
    global $currency;
    $currency = getCurrency($userid);
    $result = select_query("tblpricing", "", ["type" => "product", "currency" => $currency["id"], "relid" => $pid]);
    $data = mysql_fetch_array($result);
    if ($billingcycle == "Monthly") {
        $amount = $data["monthly"];
    } else {
        if ($billingcycle == "Quarterly") {
            $amount = $data["quarterly"];
        } else {
            if ($billingcycle == "Semi-Annually") {
                $amount = $data["semiannually"];
            } else {
                if ($billingcycle == "Annually") {
                    $amount = $data["annually"];
                } else {
                    if ($billingcycle == "Biennially") {
                        $amount = $data["biennially"];
                    } else {
                        if ($billingcycle == "Triennially") {
                            $amount = $data["triennially"];
                        } else {
                            $amount = 0;
                        }
                    }
                }
            }
        }
    }
    if ($amount <= 0) {
        $amount = 0;
    }
    if ($includesetup === true) {
        $setupvar = substr(strtolower($billingcycle), 0, 1);
        if (0 < $data[$setupvar . "setupfee"]) {
            $amount += $data[$setupvar . "setupfee"];
        }
    }
    if ($configoptionsrecurring == "empty") {
        if (!function_exists("getCartConfigOptions")) {
            require ROOTDIR . "/includes/configoptionsfunctions.php";
        }
        $configoptions = getCartConfigOptions($pid, "", $billingcycle, $serviceid, "", $showHiddenOverride);
        foreach ($configoptions as $configoption) {
            $amount += $configoption["selectedrecurring"];
            if ($includesetup === true) {
                $amount += $configoption["selectedsetup"];
            }
        }
    } else {
        $amount += (double) $configoptionsrecurring;
    }
    if ($promoid) {
        $amount -= (double) recalcPromoAmount($pid, $userid, $serviceid, $billingcycle, $amount, $promoid);
    }
    return $quantity * $amount;
}

function closeClient($userid)
{
    update_query("tblclients", ["status" => "Closed"], ["id" => $userid]);
    update_query("tblhosting", ["domainstatus" => "Cancelled", "termination_date" => date("Y-m-d")], ["userid" => $userid, "domainstatus" => "Pending"]);
    update_query("tblhosting", ["domainstatus" => "Cancelled", "termination_date" => date("Y-m-d")], ["userid" => $userid, "domainstatus" => "Active"]);
    update_query("tblhosting", ["domainstatus" => "Terminated", "termination_date" => date("Y-m-d")], ["userid" => $userid, "domainstatus" => "Suspended"]);
    $result = select_query("tblhosting", "id", ["userid" => $userid]);
    while ($data = mysql_fetch_array($result)) {
        $domainlistid = $data["id"];
        update_query("tblhostingaddons", ["status" => "Cancelled", "termination_date" => date("Y-m-d")], ["hostingid" => $domainlistid, "status" => "Pending"]);
        update_query("tblhostingaddons", ["status" => "Cancelled", "termination_date" => date("Y-m-d")], ["hostingid" => $domainlistid, "status" => "Active"]);
        update_query("tblhostingaddons", ["status" => "Terminated", "termination_date" => date("Y-m-d")], ["hostingid" => $domainlistid, "status" => "Suspended"]);
    }
    update_query("tbldomains", ["status" => "Cancelled"], ["userid" => $userid, "status" => "Pending"]);
    update_query("tbldomains", ["status" => "Cancelled"], ["userid" => $userid, "status" => "Active"]);
    update_query("tbldomains", ["status" => "Cancelled"], ["userid" => $userid, "status" => "Pending-Transfer"]);
    update_query("tblinvoices", ["status" => "Cancelled"], ["userid" => $userid, "status" => "Unpaid"]);
    update_query("tblbillableitems", ["invoiceaction" => "0"], ["userid" => $userid]);
    logActivity("Client Status changed to Closed - User ID: " . $userid, $userid);
    run_hook("ClientClose", ["userid" => $userid]);
}

function convertStateToCode($ostate, $country)
{
    if (is_null($ostate)) {
        return "";
    }
    $sc = "";
    $state = strtolower($ostate);
    $country = strtoupper($country);
    if ($country == "US") {
        if ($state == "alabama") {
            $sc = "AL";
        } else {
            if ($state == "alaska") {
                $sc = "AK";
            } else {
                if ($state == "arizona") {
                    $sc = "AZ";
                } else {
                    if ($state == "arkansas") {
                        $sc = "AR";
                    } else {
                        if ($state == "california") {
                            $sc = "CA";
                        } else {
                            if ($state == "colorado") {
                                $sc = "CO";
                            } else {
                                if ($state == "connecticut") {
                                    $sc = "CT";
                                } else {
                                    if ($state == "delaware") {
                                        $sc = "DE";
                                    } else {
                                        if ($state == "florida") {
                                            $sc = "FL";
                                        } else {
                                            if ($state == "georgia") {
                                                $sc = "GA";
                                            } else {
                                                if ($state == "hawaii") {
                                                    $sc = "HI";
                                                } else {
                                                    if ($state == "idaho") {
                                                        $sc = "ID";
                                                    } else {
                                                        if ($state == "illinois") {
                                                            $sc = "IL";
                                                        } else {
                                                            if ($state == "indiana") {
                                                                $sc = "IN";
                                                            } else {
                                                                if ($state == "iowa") {
                                                                    $sc = "IA";
                                                                } else {
                                                                    if ($state == "kansas") {
                                                                        $sc = "KS";
                                                                    } else {
                                                                        if ($state == "kentucky") {
                                                                            $sc = "KY";
                                                                        } else {
                                                                            if ($state == "louisiana") {
                                                                                $sc = "LA";
                                                                            } else {
                                                                                if ($state == "maine") {
                                                                                    $sc = "ME";
                                                                                } else {
                                                                                    if ($state == "maryland") {
                                                                                        $sc = "MD";
                                                                                    } else {
                                                                                        if ($state == "massachusetts") {
                                                                                            $sc = "MA";
                                                                                        } else {
                                                                                            if ($state == "michigan") {
                                                                                                $sc = "MI";
                                                                                            } else {
                                                                                                if ($state == "minnesota") {
                                                                                                    $sc = "MN";
                                                                                                } else {
                                                                                                    if ($state == "mississippi") {
                                                                                                        $sc = "MS";
                                                                                                    } else {
                                                                                                        if ($state == "missouri") {
                                                                                                            $sc = "MO";
                                                                                                        } else {
                                                                                                            if ($state == "montana") {
                                                                                                                $sc = "MT";
                                                                                                            } else {
                                                                                                                if ($state == "nebraska") {
                                                                                                                    $sc = "NE";
                                                                                                                } else {
                                                                                                                    if ($state == "nevada") {
                                                                                                                        $sc = "NV";
                                                                                                                    } else {
                                                                                                                        if ($state == "new hampshire") {
                                                                                                                            $sc = "NH";
                                                                                                                        } else {
                                                                                                                            if ($state == "new jersey") {
                                                                                                                                $sc = "NJ";
                                                                                                                            } else {
                                                                                                                                if ($state == "new mexico") {
                                                                                                                                    $sc = "NM";
                                                                                                                                } else {
                                                                                                                                    if ($state == "new york") {
                                                                                                                                        $sc = "NY";
                                                                                                                                    } else {
                                                                                                                                        if ($state == "north carolina") {
                                                                                                                                            $sc = "NC";
                                                                                                                                        } else {
                                                                                                                                            if ($state == "north dakota") {
                                                                                                                                                $sc = "ND";
                                                                                                                                            } else {
                                                                                                                                                if ($state == "ohio") {
                                                                                                                                                    $sc = "OH";
                                                                                                                                                } else {
                                                                                                                                                    if ($state == "oklahoma") {
                                                                                                                                                        $sc = "OK";
                                                                                                                                                    } else {
                                                                                                                                                        if ($state == "oregon") {
                                                                                                                                                            $sc = "OR";
                                                                                                                                                        } else {
                                                                                                                                                            if ($state == "pennsylvania") {
                                                                                                                                                                $sc = "PA";
                                                                                                                                                            } else {
                                                                                                                                                                if ($state == "rhode island") {
                                                                                                                                                                    $sc = "RI";
                                                                                                                                                                } else {
                                                                                                                                                                    if ($state == "south carolina") {
                                                                                                                                                                        $sc = "SC";
                                                                                                                                                                    } else {
                                                                                                                                                                        if ($state == "south dakota") {
                                                                                                                                                                            $sc = "SD";
                                                                                                                                                                        } else {
                                                                                                                                                                            if ($state == "tennessee") {
                                                                                                                                                                                $sc = "TN";
                                                                                                                                                                            } else {
                                                                                                                                                                                if ($state == "texas") {
                                                                                                                                                                                    $sc = "TX";
                                                                                                                                                                                } else {
                                                                                                                                                                                    if ($state == "utah") {
                                                                                                                                                                                        $sc = "UT";
                                                                                                                                                                                    } else {
                                                                                                                                                                                        if ($state == "vermont") {
                                                                                                                                                                                            $sc = "VT";
                                                                                                                                                                                        } else {
                                                                                                                                                                                            if ($state == "virginia") {
                                                                                                                                                                                                $sc = "VA";
                                                                                                                                                                                            } else {
                                                                                                                                                                                                if ($state == "washington") {
                                                                                                                                                                                                    $sc = "WA";
                                                                                                                                                                                                } else {
                                                                                                                                                                                                    if ($state == "west virginia") {
                                                                                                                                                                                                        $sc = "WV";
                                                                                                                                                                                                    } else {
                                                                                                                                                                                                        if ($state == "wisconsin") {
                                                                                                                                                                                                            $sc = "WI";
                                                                                                                                                                                                        } else {
                                                                                                                                                                                                            if ($state == "wyoming") {
                                                                                                                                                                                                                $sc = "WY";
                                                                                                                                                                                                            }
                                                                                                                                                                                                        }
                                                                                                                                                                                                    }
                                                                                                                                                                                                }
                                                                                                                                                                                            }
                                                                                                                                                                                        }
                                                                                                                                                                                    }
                                                                                                                                                                                }
                                                                                                                                                                            }
                                                                                                                                                                        }
                                                                                                                                                                    }
                                                                                                                                                                }
                                                                                                                                                            }
                                                                                                                                                        }
                                                                                                                                                    }
                                                                                                                                                }
                                                                                                                                            }
                                                                                                                                        }
                                                                                                                                    }
                                                                                                                                }
                                                                                                                            }
                                                                                                                        }
                                                                                                                    }
                                                                                                                }
                                                                                                            }
                                                                                                        }
                                                                                                    }
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    } else {
        if ($country == "CA") {
            if ($state == "alberta") {
                $sc = "AB";
            } else {
                if ($state == "british columbia") {
                    $sc = "BC";
                } else {
                    if ($state == "manitoba") {
                        $sc = "MB";
                    } else {
                        if ($state == "new brunswick") {
                            $sc = "NB";
                        } else {
                            if ($state == "newfoundland") {
                                $sc = "NL";
                            } else {
                                if ($state == "northwest territories") {
                                    $sc = "NT";
                                } else {
                                    if ($state == "nova scotia") {
                                        $sc = "NS";
                                    } else {
                                        if ($state == "nunavut") {
                                            $sc = "NU";
                                        } else {
                                            if ($state == "ontario") {
                                                $sc = "ON";
                                            } else {
                                                if ($state == "prince edward island") {
                                                    $sc = "PE";
                                                } else {
                                                    if ($state == "quebec") {
                                                        $sc = "QC";
                                                    } else {
                                                        if ($state == "saskatchewan") {
                                                            $sc = "SK";
                                                        } else {
                                                            if ($state == "yukon") {
                                                                $sc = "YT";
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if (!$sc) {
        $sc = $ostate;
    }
    return $sc;
}

function getClientsPaymentMethod($userid)
{
    $gatewayclass = new WHMCS\Gateways();
    $paymentmethod = "";
    if ($userid) {
        $clientPaymentMethod = get_query_val("tblclients", "defaultgateway", ["id" => $userid]);
        if ($clientPaymentMethod && $gatewayclass->isActiveGateway($clientPaymentMethod)) {
            $paymentmethod = $clientPaymentMethod;
        }
        if (!$paymentmethod) {
            $invoicePaymentMethod = get_query_val("tblinvoices", "paymentmethod", ["userid" => $userid], "id", "DESC", "0,1");
            if ($invoicePaymentMethod && $gatewayclass->isActiveGateway($invoicePaymentMethod)) {
                $paymentmethod = $invoicePaymentMethod;
            }
        }
    }
    if (!$paymentmethod) {
        $paymentmethod = $gatewayclass->getFirstAvailableGateway();
    }
    return $paymentmethod;
}

function clientChangeDefaultGateway($userid, $paymentmethod)
{
    $defaultgateway = get_query_val("tblclients", "defaultgateway", ["id" => $userid]);
    if (WHMCS\Session::get("adminid") && !$paymentmethod && $defaultgateway) {
        update_query("tblclients", ["defaultgateway" => ""], ["id" => $userid]);
    }
    if ($paymentmethod && $paymentmethod != $defaultgateway) {
        if ($paymentmethod == "none") {
            update_query("tblclients", ["defaultgateway" => ""], ["id" => $userid]);
        }
        if (!WHMCS\Module\GatewaySetting::gateway($paymentmethod)->exists()) {
            return false;
        }
        update_query("tblclients", ["defaultgateway" => $paymentmethod], ["id" => $userid]);
        update_query("tblhosting", ["paymentmethod" => $paymentmethod], ["userid" => $userid]);
        update_query("tblhostingaddons", ["paymentmethod" => $paymentmethod], "hostingid IN (SELECT id FROM tblhosting WHERE userid=" . (int) $userid . ")");
        update_query("tbldomains", ["paymentmethod" => $paymentmethod], ["userid" => $userid]);
        update_query("tblinvoices", ["paymentmethod" => $paymentmethod], ["userid" => $userid, "status" => "Unpaid"]);
    }
}

function recalcPromoAmount($pid, $userid, $serviceid, $billingcycle, $recurringamount, $promoid)
{
    global $currency;
    $currency = getCurrency($userid);
    $recurringdiscount = 0;
    $result = select_query("tblpromotions", "", ["id" => $promoid]);
    $data = mysql_fetch_array($result);
    $id = $data["id"];
    $type = $data["type"];
    $recurring = $data["recurring"];
    $value = (double) $data["value"];
    if ($recurring) {
        if ($type == "Percentage") {
            $recurringdiscount = $recurringamount * $value / 100;
        } else {
            if ($type == "Fixed Amount") {
                if ($currency["id"] != 1) {
                    $value = convertCurrency($value, null, $currency["id"], 1);
                }
                if ($recurringamount < $value) {
                    $recurringdiscount = $recurringamount;
                } else {
                    $recurringdiscount = $value;
                }
            } else {
                if ($type == "Price Override") {
                    if ($currency["id"] != 1) {
                        $value = convertCurrency($value, null, $currency["id"], 1);
                    }
                    $recurringdiscount = $recurringamount - $value;
                }
            }
        }
    }
    return $recurringdiscount;
}

function cancelUnpaidInvoicebyProductID($serviceid, $userid = "")
{
    $userid = (int) $userid;
    $serviceid = (int) $serviceid;
    if (!$userid) {
        $userid = (int) WHMCS\Database\Capsule::table("tblhosting")->where("id", $serviceid)->get(["userid"])->first();
    }
    if (!$userid) {
        return NULL;
    }
    $addons = WHMCS\Database\Capsule::table("tblhostingaddons")->where("hostingid", "=", $serviceid)->get(["id"])->all();
    $addonIds = [];
    foreach ($addons as $addon) {
        $addonIds[] = $addon->id;
    }
    $unpaidInvoices = WHMCS\Database\Capsule::table("tblinvoiceitems")->where(["type" => "Hosting", "relid" => $serviceid, "status" => WHMCS\Billing\Invoice::STATUS_UNPAID, "tblinvoices.userid" => $userid])->join("tblinvoices", "tblinvoices.id", "=", "tblinvoiceitems.invoiceid")->get(["tblinvoiceitems.id", "tblinvoiceitems.invoiceid"])->all();
    foreach ($unpaidInvoices as $data) {
        cancelInvoiceItem($userid, $serviceid, $data->invoiceid, $data->id, $addonIds);
    }
    if ($addonIds) {
        $invoiceItems = WHMCS\Database\Capsule::table("tblinvoiceitems")->where("type", "=", "Addon")->whereIn("relid", $addonIds)->where("status", "=", "Unpaid")->where("tblinvoices.userid", "=", $userid)->join("tblinvoices", "tblinvoices.id", "=", "tblinvoiceitems.invoiceid")->get(["tblinvoiceitems.id", "tblinvoiceitems.relid", "tblinvoiceitems.invoiceid"])->all();
        foreach ($invoiceItems as $invoiceItem) {
            $itemCount = WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceItem->invoiceid)->count();
            if (1 < $itemCount && $itemCount <= 3) {
                $itemCount -= WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceItem->invoiceid)->where("type", "=", "GroupDiscount")->count();
                $itemCount -= WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceItem->invoiceid)->where("type", "=", "LateFee")->count();
            }
            if ($itemCount == 1) {
                WHMCS\Database\Capsule::table("tblinvoices")->where("id", $invoiceItem->invoiceid)->update(["status" => WHMCS\Billing\Invoice::STATUS_CANCELLED, "date_cancelled" => WHMCS\Carbon::now()->toDateTimeString(), "updated_at" => WHMCS\Carbon::now()->toDateTimeString()]);
                logActivity("Cancelled Outstanding Product Addon Invoice - Invoice ID: " . $invoiceItem->invoiceid . " - Service Addon ID: " . $invoiceItem->relid, $userid);
                run_hook("InvoiceCancelled", ["invoiceid" => $invoiceItem->invoiceid]);
            } else {
                WHMCS\Database\Capsule::table("tblinvoiceitems")->delete($invoiceItem->id);
                WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceItem->invoiceid)->where("type", "=", "GroupDiscount")->delete();
                if (!function_exists("updateInvoiceTotal")) {
                    require_once ROOTDIR . "/includes/invoicefunctions.php";
                }
                updateInvoiceTotal($invoiceItem->invoiceid);
                logActivity("Removed Outstanding Product Renewal Invoice Line Item - Invoice ID: " . $invoiceItem->invoiceid . " - Service ID: " . $invoiceItem->relid, $userid);
            }
        }
    }
    return true;
}

function cancelInvoiceItem($userid, int $serviceid, int $invoiceid, int $itemid, $addonIds)
{
    if ($userid <= 0 || $serviceid <= 0 || $invoiceid <= 0 || $itemid <= 0) {
        throw new Exception("One of the userId, serviceId, invoiceId or itemId is invalid.  Unable to attempt invoice item cancellation.");
    }
    $itemcount = WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", $invoiceid)->whereNotIn("id", function ($query) use($invoiceid, $serviceid) {
        $query->select("id")->from("tblinvoiceitems")->where("invoiceid", $invoiceid)->where(function ($query) use($serviceid) {
            $query->whereIn("type", ["GroupDiscount", "LateFee"])->orWhere("amount", "0")->orWhere(function ($query) use($serviceid) {
                $query->where("type", "PromoHosting")->where("relid", $serviceid);
            });
        });
    })->count();
    if ($addonIds) {
        $itemcount -= WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceid)->where("type", "=", "Addon")->whereIn("relid", $addonIds)->count();
    }
    if ($itemcount <= 1) {
        WHMCS\Database\Capsule::table("tblinvoices")->where("id", $invoiceid)->update(["status" => WHMCS\Billing\Invoice::STATUS_CANCELLED, "date_cancelled" => WHMCS\Carbon::now()->toDateTimeString(), "updated_at" => WHMCS\Carbon::now()->toDateTimeString()]);
        logActivity("Cancelled Outstanding Product Renewal Invoice - Invoice ID: " . $invoiceid . " - Service ID: " . $serviceid, $userid);
        run_hook("InvoiceCancelled", ["invoiceid" => $invoiceid]);
    } else {
        WHMCS\Database\Capsule::table("tblinvoiceitems")->where("id", $itemid)->orWhere(function ($query) use($invoiceid, $serviceid) {
            $query->where("invoiceid", $invoiceid)->where("type", "PromoHosting")->where("relid", $serviceid);
        })->orWhere(function ($query) use($invoiceid) {
            $query->where("invoiceid", $invoiceid)->where("type", "GroupDiscount");
        })->delete();
        if (!function_exists("updateInvoiceTotal")) {
            require_once ROOTDIR . "/includes/invoicefunctions.php";
        }
        updateInvoiceTotal($invoiceid);
        logActivity("Removed Outstanding Product Renewal Invoice Line Item - Invoice ID: " . $invoiceid . " - Service ID: " . $serviceid, $userid);
    }
}

function getClientDefaultBankDetails($userId, $mode = "allowLegacy", &$foundPayMethodRef = false)
{
    $bankDetails = ["bankname" => NULL, "banktype" => NULL, "bankacct" => NULL, "bankcode" => NULL, "gatewayid" => NULL];
    $client = WHMCS\User\Client::find($userId);
    if (!$client) {
        return $bankDetails;
    }
    if (!in_array($mode, ["forceLegacy", "forcePayMethod", "allowLegacy"])) {
        $mode = "allowLegacy";
    }
    if ($mode == "forceLegacy") {
        return getClientsBankDetails($userId);
    }
    if ($mode == "allowLegacy" && $client->needsBankDetailsMigrated()) {
        return getClientsBankDetails($userId);
    }
    $payMethods = $client->payMethods->bankAccounts();
    $gateway = new WHMCS\Module\Gateway();
    $payMethod = NULL;
    foreach ($payMethods as $tryPayMethod) {
        if (!$tryPayMethod->isUsingInactiveGateway()) {
            $payMethod = $tryPayMethod;
            if ($payMethod) {
                $payment = $payMethod->payment;
                if ($payment) {
                    $bankDetails["paymethodid"] = $payMethod->id;
                    $bankDetails["bankname"] = "";
                    $bankDetails["banktype"] = "Checking";
                    $bankDetails["bankcode"] = "";
                    $bankDetails["bankacct"] = $payment->getAccountNumber();
                    if ($payment instanceof WHMCS\Payment\Contracts\BankAccountDetailsInterface) {
                        $bankDetails["bankname"] = $payment->getBankName();
                        $bankDetails["banktype"] = $payment->getAccountType();
                        $bankDetails["bankcode"] = $payment->getRoutingNumber();
                    }
                }
                if ($payment && $payment instanceof WHMCS\Payment\Contracts\RemoteTokenDetailsInterface) {
                    $bankDetails["gatewayid"] = $payment->getRemoteToken();
                }
                if ($foundPayMethodRef !== false) {
                    $foundPayMethodRef = $payMethod;
                }
                $bankDetails["payMethod"] = $payMethod;
            }
            return $bankDetails;
        }
    }
}

function getClientsBankDetails($userId)
{
    if (!isset($users) || !is_array($users)) {
        $users = [];
    }
    if (!array_key_exists($userId, $users)) {
        $ccHash = DI::make("config")["cc_encryption_hash"];
        $aesHash = md5($ccHash . $userId);
        $clientInfo = WHMCS\Database\Capsule::table("tblclients")->where("id", $userId)->first(["bankname", "banktype", WHMCS\Database\Capsule::raw("AES_DECRYPT(bankcode, '" . $aesHash . "') as bankcode"), WHMCS\Database\Capsule::raw("AES_DECRYPT(bankacct, '" . $aesHash . "') as bankacct"), "gatewayid"]);
        $users[$userId] = (array) $clientInfo;
    }
    return $users[$userId];
}

function normaliseInternationalPhoneNumberFormat(&$details, $pollute = true)
{
    $phoneFields = ["Phone", "Phone Number"];
    $countryCodeField = "Phone Country Code";
    foreach ($phoneFields as $field) {
        if (array_key_exists($field, $details)) {
            $countryCode = "";
            if (!empty($details[$countryCodeField])) {
                $countryCode = $details[$countryCodeField];
            }
            $details[$field] = normalisePhoneFormat($details[$field], $countryCode, ".");
            if ($pollute) {
                $details["phone-normalised"] = true;
            }
        }
    }
}

function normalisePhoneFormat($phone = "", $countryCode = ".", $delimiter)
{
    $delimCode = dechex(mb_ord($delimiter));
    $pattern = "/^\\+[0-9]{1,3}\\x" . $delimCode . "[0-9]{1,14}\$/";
    if (preg_match($pattern, $phone) == 1) {
        return $phone;
    }
    if (strlen($phone) == 0) {
        return "";
    }
    $phone = preg_replace("/[^0-9]/", "", $phone);
    if (empty($countryCode)) {
        $countryCode = "";
    } else {
        $countryCode = "+" . $countryCode . $delimiter;
    }
    return $countryCode . $phone;
}
