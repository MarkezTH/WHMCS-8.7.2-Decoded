<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Add New Client", false);
$aInt->title = AdminLang::trans("clients.addnew");
$aInt->sidebar = "clients";
$aInt->icon = "clientsadd";
$aInt->requiredFiles(["clientfunctions", "customfieldfunctions", "gatewayfunctions"]);
$action = App::getFromRequest("action");
$allowSingleSignOn = $whmcs->isInRequest("token") ? (int) $whmcs->getFromRequest("allowsinglesignon") : 1;
$marketing_emails_opt_in = (int) App::getFromRequest("marketing_emails_opt_in");
$taxId = "";
$email = trim($whmcs->get_req_var("email"));
$whmcs->replace_input_vars(["email" => $email]);
if (App::isInRequest("token")) {
    $latefeeoveride = !empty($latefeeoveride) ? 0 : 1;
    $overideduenotices = !empty($overideduenotices) ? 0 : 1;
    $overrideautoclose = !empty($overrideautoclose) ? 0 : 1;
}
foreach (WHMCS\Mail\Emailer::CLIENT_EMAILS as $emailType) {
    $targetVariable = $emailType . "Emails";
    ${$targetVariable} = "checked=\"checked\"";
    if ($emailType != WHMCS\Mail\Emailer::EMAIL_TYPE_DOMAIN && App::isInRequest("email_preferences") && !$email_preferences[$emailType]) {
        unset($targetVariable);
    }
}
if ($action == "add") {
    check_token("WHMCS.admin.default");
    $sendemail = App::getFromRequest("sendemail");
    $result = select_query("tblclients", "COUNT(*)", ["email" => $email]);
    $data = mysql_fetch_array($result);
    $taxId = App::getFromRequest("tax_id");
    if ($data[0]) {
        infoBox(AdminLang::trans("clients.duplicateemail"), AdminLang::trans("clients.duplicateemailexp"), "error");
    } else {
        if (!trim($email) && empty($cccheck)) {
            infoBox(AdminLang::trans("global.validationerror"), AdminLang::trans("clients.invalidemail"), "error");
        } else {
            if (empty($cccheck) && trim($email)) {
                $validate = new WHMCS\Validate();
                if (!$validate->validate("email", "email", "clientareaerroremailinvalid")) {
                    $errormessage .= $validate->getHTMLErrorOutput();
                    infoBox(AdminLang::trans("global.validationerror"), AdminLang::trans("clients.invalidemail"), "error");
                } else {
                    $query = "subaccount=1 AND email='" . mysql_real_escape_string($email) . "'";
                    $result = select_query("tblcontacts", "COUNT(*)", $query);
                    $data = mysql_fetch_array($result);
                    if ($data[0]) {
                        infoBox(AdminLang::trans("clients.duplicateemail"), AdminLang::trans("clients.duplicateemailexp"), "error");
                    }
                }
                if (!$infobox) {
                    run_validate_hook($validate, "ClientDetailsValidation", $_POST);
                    if (App::isInRequest("email_preferences") && empty($email_preferences["domain"])) {
                        $validate->addError(AdminLang::trans("emailPreferences.oneRequired") . " " . AdminLang::trans("emailPreferences.domainClientRequired"));
                    }
                    $validate->validate("uniqueemail", "email", AdminLang::trans("clients.duplicateemailuser"), [(int) App::getFromRequest("user"), ""]);
                    $errormessage = $validate->getErrors();
                    if (count($errormessage)) {
                        infoBox(AdminLang::trans("global.validationerror"), implode("<br/>", $errormessage), "error");
                    }
                }
            }
            if (!$infobox) {
                if ((int) App::getFromRequest("existing_user") === 1) {
                    try {
                        $user = WHMCS\User\User::findOrFail(App::getFromRequest("user"));
                    } catch (Exception $e) {
                        infoBox(AdminLang::trans("global.erroroccurred"), "Invalid User Selected", "error");
                    }
                } else {
                    try {
                        $user = WHMCS\User\User::createUser($firstname, $lastname, $email, $password, $language);
                    } catch (WHMCS\Exception\User\EmailAlreadyExists $e) {
                        infoBox(AdminLang::trans("global.erroroccurred"), AdminLang::trans("clients.duplicateemail"), "error");
                    } catch (Exception $e) {
                        infoBox(AdminLang::trans("global.erroroccurred"), $e->getMessage(), "error");
                    }
                }
            }
            if (!$infobox) {
                $_SESSION["currency"] = $currency;
                $phonenumber = App::formatPostedPhoneNumber();
                $client = $user->createClient($firstname, $lastname, $companyname, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber, $sendemail === "on", ["notes" => $notes, "status" => $status, "taxexempt" => $taxexempt ?? NULL, "latefeeoveride" => $latefeeoveride, "overideduenotices" => $overideduenotices, "language" => $language, "billingcid" => $billingcid, "lastlogin" => "00000000000000", "groupid" => $groupid ?? NULL, "separateinvoices" => $separateinvoices ?? NULL, "disableautocc" => $disableautocc ?? NULL, "defaultgateway" => $paymentmethod, "emailoptout" => !$marketing_emails_opt_in, "overrideautoclose" => $overrideautoclose, "allow_sso" => $allowSingleSignOn, "credit" => (double) $whmcs->get_req_var("credit"), "tax_id" => $taxId], "", true, $marketing_emails_opt_in);
                if (App::isInRequest("email_preferences")) {
                    WHMCS\Database\Capsule::table("tblclients")->where("id", $client->id)->update(["email_preferences" => json_encode($email_preferences)]);
                }
                WHMCS\Session::delete(WHMCS\Authentication\AuthManager::TOKEN_NAME);
                WHMCS\Session::delete(WHMCS\Authentication\AuthManager::SESSION_CLIENTID_NAME);
                WHMCS\Session::delete(WHMCS\Authentication\AuthManager::SESSION_TWOFACTOR_CLIENTID_NAME);
                WHMCS\Cookie::delete(WHMCS\Authentication\AuthManager::TOKEN_NAME);
                redir("userid=" . $client->id, "clientssummary.php");
            }
        }
    }
}
WHMCS\Session::release();
$aInt->populateStandardAdminSmartyVariables();
ob_start();
$templateData = ["formAction" => $whmcs->getPhpSelf() . "?action=add", "userId" => 0, "groupid" => $groupid ?? 0, "infoBox" => $infobox ?? NULL, "firstName" => $firstname ?? NULL, "addressOne" => $address1 ?? NULL, "lastName" => $lastname ?? NULL, "addressTwo" => $address2 ?? NULL, "companyName" => $companyname ?? NULL, "cityName" => $city ?? NULL, "emailAddress" => $email ?? NULL, "stateName" => $state ?? NULL, "password" => $password ?? NULL, "postCode" => $postcode ?? NULL, "countryName" => $country ?? NULL, "phoneNumber" => $phonenumber ?? NULL, "taxId" => $taxId ?? NULL, "language" => $language ?? NULL, "billingcid" => $billingcid ?? 0, "defaultGateway" => $defaultgateway ?? NULL, "clientStatus" => $status ?? NULL, "affiliateEmails" => $affiliateEmails, "domainEmails" => $domainEmails, "generalEmails" => $generalEmails, "invoiceEmails" => $invoiceEmails, "productEmails" => $productEmails, "supportEmails" => $supportEmails, "lateFeeOveride" => $latefeeoveride ?? NULL, "overideDueNotices" => $overideduenotices ?? NULL, "taxExempt" => $taxexempt ?? NULL, "separateInvoices" => $separateinvoices ?? NULL, "disableAutoCc" => $disableautocc ?? NULL, "marketingEmailsOptIn" => $marketingEmailsOptIn ?? NULL, "overrideAutoClose" => $overrideautoclose ?? NULL, "twoFaEnabled" => $twofaenabled ?? NULL, "allowSingleSignOn" => $allowSingleSignOn ?? NULL, "adminNotes" => $notes ?? NULL, "remoteAccountLinks" => [], "currency" => !empty($currency) ? (int) $currency : WHMCS\Billing\Currency::defaultCurrency()->first()->id, "generatePasswordForm" => $aInt->getTemplate("generate-password", false)];
echo view("admin.client.shared.add-edit", $templateData);
$jqueryCode = "\n    WHMCS.form.register();\n";
$jsCode = "var stateNotRequired = true;\n";
echo WHMCS\View\Asset::jsInclude("StatesDropdown.js");
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jqueryCode;
$aInt->jscode = $jsCode;
$aInt->display();
