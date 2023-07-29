<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Edit Clients Details", false);
$aInt->requiredFiles(["clientfunctions", "customfieldfunctions", "gatewayfunctions"]);
$aInt->setClientsProfilePresets();
$aInt->setHelpLink("Clients:Profile Tab");
$userid = $whmcs->get_req_var("userid");
$aInt->valUserID($userid);
$aInt->assertClientBoundary($userid);
$client = WHMCS\User\Client::find($userid);
if ($whmcs->get_req_var("save")) {
    check_token("WHMCS.admin.default");
    $email = trim($email);
    $result = select_query("tblclients", "COUNT(*)", "email='" . db_escape_string($email) . "' AND id!='" . db_escape_string($userid) . "'");
    $data = mysql_fetch_array($result);
    if ($data[0]) {
        redir("userid=" . $userid . "&emailexists=1");
    } else {
        $where = ["email" => $email, "subaccount" => 1];
        $result = select_query("tblcontacts", "COUNT(*)", $where);
        $data = mysql_fetch_array($result);
        if ($data[0]) {
            redir("userid=" . $userid . "&emailexists=1");
        }
        $queryString = "userid=" . $userid . "&";
        $validate = new WHMCS\Validate();
        run_validate_hook($validate, "ClientDetailsValidation", $_POST);
        $validate->validate("email", "email", ["clients.invalidemail"]);
        if (App::isInRequest("email_preferences")) {
            $emailPreferences = App::getFromRequest("email_preferences");
            try {
                $client->validateEmailPreferences($emailPreferences);
            } catch (WHMCS\Exception\Validation\Required $e) {
                $validate->addError(AdminLang::trans("emailPreferences.oneRequired") . " " . AdminLang::trans($e->getMessage()));
            } catch (Exception $e) {
                $validate->addError("Invalid Client ID");
            }
        }
        $errormessage = $validate->getErrors();
        if (count($errormessage)) {
            $_SESSION["profilevalidationerror"] = $errormessage;
            redir("userid=" . $userid);
        }
        $oldclientsdetails = getClientsDetails($userid);
        $emailWasUpdated = false;
        if ($email != $oldclientsdetails["email"]) {
            $emailWasUpdated = true;
        }
        $uuid = "";
        if (empty($oldclientsdetails["uuid"])) {
            $uuid = Ramsey\Uuid\Uuid::uuid4();
            $uuid = $uuid->toString();
        } else {
            $uuid = $oldclientsdetails["uuid"];
        }
        $table = "tblclients";
        $phonenumber = App::formatPostedPhoneNumber();
        $array = ["uuid" => $uuid, "firstname" => $firstname, "lastname" => $lastname, "companyname" => $companyname, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber, "tax_id" => $tax_id ?? NULL, "currency" => $_POST["currency"], "notes" => $notes, "status" => $status, "taxexempt" => (bool) ($taxexempt ?? NULL), "latefeeoveride" => !empty($latefeeoveride) ? 0 : 1, "overideduenotices" => !empty($overideduenotices) ? 0 : 1, "separateinvoices" => (bool) ($separateinvoices ?? NULL), "disableautocc" => (bool) ($disableautocc ?? NULL), "overrideautoclose" => !empty($overrideautoclose) ? 0 : 1, "language" => $language, "billingcid" => $billingcid, "groupid" => $groupid, "email_preferences" => json_encode($emailPreferences), "allow_sso" => (bool) $whmcs->get_req_var("allowsinglesignon")];
        if ($emailWasUpdated) {
            $array["email_verified"] = 0;
        }
        if (WHMCS\Config\Setting::getValue("DisableClientEmailPreferences")) {
            $array["email_preferences"] = json_encode(WHMCS\User\Client::$emailPreferencesDefaults);
        }
        $where = ["id" => $userid];
        update_query($table, $array, $where);
        $customfields = getCustomFields("client", "", $userid, "on", "");
        foreach ($customfields as $v) {
            $k = $v["id"];
            $customfieldsarray[$k] = $_POST["customfield"][$k];
        }
        $updatefieldsarray = ["firstname" => "First Name", "lastname" => "Last Name", "companyname" => "Company Name", "email" => "Email Address", "address1" => "Address 1", "address2" => "Address 2", "city" => "City", "state" => "State", "postcode" => "Postcode", "country" => "Country", "phonenumber" => "Phone Number", "tax_id" => "Tax ID", "billingcid" => "Billing Contact", "groupid" => "Client Group", "language" => "Language", "currency" => "Currency", "status" => "Status"];
        $updatedtickboxarray = ["latefeeoveride" => "Late Fees Override", "overideduenotices" => "Overdue Notices", "taxexempt" => "Tax Exempt", "separateinvoices" => "Separate Invoices", "disableautocc" => "Disable CC Processing", "overrideautoclose" => "Auto Close", "allowSingleSignOn" => "Allow Single Sign On"];
        $changelist = [];
        foreach ($updatefieldsarray as $field => $displayname) {
            $oldvalue = $oldclientsdetails[$field];
            $newvalue = $array[$field];
            if ($field == "phonenumber" && $newvalue) {
                $newvalue = str_replace([" ", "-"], "", App::formatPostedPhoneNumber());
                $oldvalue = $oldclientsdetails["phonenumberformatted"];
            }
            if ($newvalue != $oldvalue) {
                $log = true;
                if ($field == "groupid") {
                    $oldvalue = $oldvalue ? get_query_val("tblclientgroups", "groupname", ["id" => $oldvalue]) : AdminLang::trans("global.none");
                    $newvalue = $newvalue ? get_query_val("tblclientgroups", "groupname", ["id" => $newvalue]) : AdminLang::trans("global.none");
                } else {
                    if ($field == "currency") {
                        $oldvalue = get_query_val("tblcurrencies", "code", ["id" => $oldvalue]);
                        $newvalue = get_query_val("tblcurrencies", "code", ["id" => $newvalue]);
                    }
                }
                if ($log) {
                    $changelist[] = $displayname . ": '" . $oldvalue . "' to '" . $newvalue . "'";
                }
            }
        }
        foreach ($updatedtickboxarray as $field => $displayname) {
            if ($field == "overideduenotices") {
                $oldfield = $oldclientsdetails[$field] ? "Disabled" : "Enabled";
                $newfield = $array[$field] ? "Disabled" : "Enabled";
            } else {
                if ($field == "allowSingleSignOn") {
                    $oldfield = $oldclientsdetails[$field] ? "Enabled" : "Disabled";
                    $newfield = $array["allow_sso"] ? "Enabled" : "Disabled";
                } else {
                    $oldfield = $oldclientsdetails[$field] ? "Enabled" : "Disabled";
                    $newfield = $array[$field] ? "Enabled" : "Disabled";
                }
            }
            if ($oldfield != $newfield) {
                $changelist[] = $displayname . ": '" . $oldfield . "' to '" . $newfield . "'";
            }
        }
        $marketing_emails_opt_in = (int) App::getFromRequest("marketing_emails_opt_in");
        if ($client->isOptedInToMarketingEmails() && !$marketing_emails_opt_in) {
            $client->marketingEmailOptOut();
            $changelist[] = "Opted Out of Marketing Emails";
        } else {
            if (!$client->isOptedInToMarketingEmails() && $marketing_emails_opt_in) {
                $client->marketingEmailOptIn();
                $changelist[] = "Opted In to Marketing Emails";
            }
        }
        if (!WHMCS\Config\Setting::getValue("DisableClientEmailPreferences")) {
            $emailPreferencesChanges = [];
            unset($array["email_preferences"]);
            foreach ($emailPreferences as $type => $value) {
                $array["email_preferences"][$type] = (int) $value;
                if ((int) $oldclientsdetails["email_preferences"][$type] != (int) $value) {
                    $suffixText = "Disabled";
                    if ($value) {
                        $suffixText = "Enabled";
                    }
                    $emailPreferencesChanges[] = ucfirst($type) . " Emails " . $suffixText;
                }
            }
            if (0 < count($emailPreferencesChanges)) {
                $changelist[] = "Email Preferences Updated: " . implode(", ", $emailPreferencesChanges);
            }
        }
        clientChangeDefaultGateway($userid, $paymentmethod);
        if ($oldclientsdetails["defaultgateway"] != $paymentmethod) {
            $changelist[] = "Default Payment Method: '" . $oldclientsdetails["defaultgateway"] . "' to '" . $paymentmethod . "'";
        }
        foreach ($customfields as $customfield) {
            $fieldid = $customfield["id"];
            if (isset($customfieldsarray[$fieldid]) && $customfield["rawvalue"] != $customfieldsarray[$fieldid]) {
                $changelist[] = "Custom Field " . $customfield["name"] . ": '" . $customfield["rawvalue"] . "' to '" . $customfieldsarray[$fieldid] . "'";
            }
        }
        saveCustomFields($userid, $customfieldsarray ?? NULL, "client", true);
        if (!count($changelist)) {
            $changelist[] = "No Changes";
        }
        logActivity("Client Profile Modified - " . implode(", ", $changelist), $userid, ["withClientId" => true]);
        run_hook("AdminClientProfileTabFieldsSave", $_REQUEST);
        if (WHMCS\Config\Setting::getValue("TaxEUTaxValidation")) {
            $client = WHMCS\User\Client::find($userid);
            $taxExempt = WHMCS\Billing\Tax\Vat::setTaxExempt($client);
            $client->save();
            if ($taxExempt != $array["taxexempt"]) {
                $array["taxexempt"] = $taxExempt;
            }
        }
        HookMgr::run("ClientEdit", array_merge(["userid" => $userid, "isOptedInToMarketingEmails" => $client->isOptedInToMarketingEmails(), "olddata" => $oldclientsdetails], $array));
        $queryString .= "success=true";
        redir($queryString);
    }
}
ob_start();
if ($whmcs->get_req_var("emailexists")) {
    infoBox(AdminLang::trans("clients.duplicateemail"), AdminLang::trans("clients.duplicateemailexp"), "error");
} else {
    if (!empty($_SESSION["profilevalidationerror"])) {
        infoBox(AdminLang::trans("global.validationerror"), implode("<br />", $_SESSION["profilevalidationerror"]), "error");
        unset($_SESSION["profilevalidationerror"]);
    } else {
        if ($whmcs->get_req_var("success")) {
            $successDescription = AdminLang::trans("global.changesuccessdesc");
            if ($whmcs->get_req_var("emailUpdated")) {
                $successDescription .= "  <a href=\"#\" id=\"hrefEmailVerificationSendNew\">" . AdminLang::trans("general.emailVerificationSendNew") . "</a>";
            }
            infoBox(AdminLang::trans("global.changesuccess"), $successDescription, "success");
        }
    }
}
WHMCS\Session::release();
$legacyClient = new WHMCS\Client($client);
$clientsdetails = $legacyClient->getDetails();
$firstname = $clientsdetails["firstname"];
$lastname = $clientsdetails["lastname"];
$companyname = $clientsdetails["companyname"];
$email = $clientsdetails["email"];
$address1 = $clientsdetails["address1"];
$address2 = $clientsdetails["address2"];
$city = $clientsdetails["city"];
$state = $clientsdetails["state"];
$postcode = $clientsdetails["postcode"];
$country = $clientsdetails["country"];
$phonenumber = $clientsdetails["telephoneNumber"];
$taxId = $clientsdetails["tax_id"];
$currency = $clientsdetails["currency"];
$notes = $clientsdetails["notes"];
$status = $clientsdetails["status"];
$defaultgateway = $clientsdetails["defaultgateway"];
$taxexempt = $clientsdetails["taxexempt"];
$latefeeoveride = $clientsdetails["latefeeoveride"];
$overideduenotices = $clientsdetails["overideduenotices"];
$separateinvoices = $clientsdetails["separateinvoices"];
$disableautocc = $clientsdetails["disableautocc"];
$marketingEmailsOptIn = $legacyClient->getClientModel()->isOptedInToMarketingEmails();
$overrideautoclose = $clientsdetails["overrideautoclose"];
$language = $clientsdetails["language"];
$billingcid = $clientsdetails["billingcid"];
$groupid = $clientsdetails["groupid"];
$affiliateEmails = $clientsdetails["email_preferences"]["affiliate"];
$domainEmails = $clientsdetails["email_preferences"]["domain"];
$generalEmails = $clientsdetails["email_preferences"]["general"];
$invoiceEmails = $clientsdetails["email_preferences"]["invoice"];
$productEmails = $clientsdetails["email_preferences"]["product"];
$supportEmails = $clientsdetails["email_preferences"]["support"];
if ($affiliateEmails) {
    $affiliateEmails = "checked=\"checked\"";
}
if ($domainEmails) {
    $domainEmails = "checked=\"checked\"";
}
if ($generalEmails) {
    $generalEmails = "checked=\"checked\"";
}
if ($invoiceEmails) {
    $invoiceEmails = "checked=\"checked\"";
}
if ($productEmails) {
    $productEmails = "checked=\"checked\"";
}
if ($supportEmails) {
    $supportEmails = "checked=\"checked\"";
}
$allowSingleSignOn = $clientsdetails["allowSingleSignOn"];
$hookret = run_hook("AdminClientProfileTabFields", $clientsdetails);
$templateData = ["formAction" => $whmcs->getPhpSelf() . "?save=true&userid=" . $userid, "userId" => $userid, "infoBox" => $infobox, "firstName" => $firstname, "addressOne" => $address1, "lastName" => $lastname, "addressTwo" => $address2, "companyName" => $companyname, "cityName" => $city, "emailAddress" => $email, "stateName" => $state, "password" => $password ?? NULL, "postCode" => $postcode, "countryName" => $country, "phoneNumber" => $phonenumber, "taxId" => $taxId, "language" => $language, "defaultGateway" => $defaultgateway, "clientStatus" => $status, "affiliateEmails" => $affiliateEmails, "domainEmails" => $domainEmails, "generalEmails" => $generalEmails, "invoiceEmails" => $invoiceEmails, "productEmails" => $productEmails, "supportEmails" => $supportEmails, "hookReturn" => $hookret, "lateFeeOveride" => $latefeeoveride, "overideDueNotices" => $overideduenotices, "taxExempt" => $taxexempt, "separateInvoices" => $separateinvoices, "disableAutoCc" => $disableautocc, "marketingEmailsOptIn" => $marketingEmailsOptIn, "overrideAutoClose" => $overrideautoclose, "allowSingleSignOn" => $allowSingleSignOn, "adminNotes" => $notes, "currency" => $currency, "billingcid" => $billingcid, "groupid" => $groupid];
echo view("admin.client.shared.add-edit", $templateData);
$jqueryCode = "\n    jQuery('#hrefEmailVerificationSendNew').click(function() {\n        WHMCS.http.jqClient.post('" . $whmcs->getPhpSelf() . "',\n        {\n            'token': '" . generate_token("plain") . "',\n            'action': 'resendVerificationEmail',\n            'userid': '" . $userid . "'\n        }).done(function(data) {\n            jQuery('#hrefEmailVerificationSendNew').text('" . AdminLang::trans("global.emailSent") . "');\n        });\n    });\n    WHMCS.form.register();\n";
$jsCode = "var stateNotRequired = true;\n";
echo WHMCS\View\Asset::jsInclude("StatesDropdown.js");
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jqueryCode;
$aInt->jscode = $jsCode;
$aInt->display();
