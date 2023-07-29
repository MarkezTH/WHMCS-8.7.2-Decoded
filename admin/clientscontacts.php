<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Edit Clients Details");
$aInt->requiredFiles(["clientfunctions"]);
$userid = App::getFromRequest("userid");
$action = App::getFromRequest("action");
$aInt->setClientsProfilePresets();
$aInt->valUserID($userid);
$aInt->setClientsProfilePresets($userid);
$aInt->assertClientBoundary($userid);
$aInt->setHelpLink("Clients:Contacts Tab");
$whmcs = App::self();
$emailerr = $whmcs->get_req_var("emailerr");
$email = $whmcs->get_req_var("email");
$contactid = App::getFromRequest("contactid");
$generalemails = App::getFromRequest("email_preferences", "general");
$invoiceemails = App::getFromRequest("email_preferences", "invoice");
$productemails = App::getFromRequest("email_preferences", "product");
$domainemails = App::getFromRequest("email_preferences", "domain");
$supportemails = App::getFromRequest("email_preferences", "support");
$affiliateemails = App::getFromRequest("email_preferences", "affiliate");
$firstname = App::getFromRequest("firstname");
$lastname = App::getFromRequest("lastname");
$companyname = App::getFromRequest("companyname");
$address1 = App::getFromRequest("address1");
$address2 = App::getFromRequest("address2");
$city = App::getFromRequest("city");
$state = App::getFromRequest("state");
$postcode = App::getFromRequest("postcode");
$country = App::getFromRequest("country");
$phonenumber = App::getFromRequest("phonenumber");
if ($action == "save") {
    $querystring = "";
    check_token("WHMCS.admin.default");
    checkPermission("Edit Clients Details");
    $validate = new WHMCS\Validate();
    $contact = NULL;
    if (0 < (int) $contactid) {
        try {
            $contact = WHMCS\User\Client\Contact::findOrFail($contactid);
            $contact->validateEmailPreferences(App::getFromRequest("email_preferences"));
        } catch (WHMCS\Exception\Validation\Required $e) {
            $validate->addError(AdminLang::trans("emailPreferences.oneRequired") . " " . AdminLang::trans($e->getMessage()));
        } catch (Exception $e) {
            $validate->addError("Invalid Contact ID");
        }
    }
    if ($domainemails) {
        $domainemails = 1;
    }
    if ($generalemails) {
        $generalemails = 1;
    }
    if ($invoiceemails) {
        $invoiceemails = 1;
    }
    if ($productemails) {
        $productemails = 1;
    }
    if ($supportemails) {
        $supportemails = 1;
    }
    if ($affiliateemails) {
        $affiliateemails = 1;
    }
    $taxId = "";
    if (WHMCS\Billing\Tax\Vat::isTaxIdEnabled()) {
        $taxId = App::getFromRequest(WHMCS\Billing\Tax\Vat::getFieldName(true));
    }
    $valErr = "";
    $queryStr = "userid=" . $userid . "&contactid=" . $contactid;
    if ($validate->validate("required", "email", ["clients", "erroremail"])) {
        $validate->validate("email", "email", ["clients", "erroremailinvalid"]);
    }
    $errormessage = $validate->getErrors();
    if (count($errormessage)) {
        WHMCS\Session::setAndRelease("profilevalidationerror", $errormessage);
        $data = $_REQUEST;
        unset($data["action"]);
        redir(build_query_string($data));
    }
    $phonenumber = App::formatPostedPhoneNumber();
    if ($contactid == "addnew") {
        $contactid = addContact($userid, $firstname, $lastname, $companyname, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber, $generalemails, $productemails, $domainemails, $invoiceemails, $supportemails, $affiliateemails, $taxId);
        $queryStr = str_replace("addnew", $contactid, $queryStr);
    } else {
        logActivity("Contact Modified - User ID: " . $userid . " - Contact ID: " . $contactid, $userid);
        $oldcontactdata = get_query_vals("tblcontacts", "", ["userid" => $userid, "id" => $contactid]);
        $table = "tblcontacts";
        $array = ["firstname" => $firstname, "lastname" => $lastname, "companyname" => $companyname, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber, "tax_id" => $taxId, "domainemails" => $domainemails, "generalemails" => $generalemails, "invoiceemails" => $invoiceemails, "productemails" => $productemails, "supportemails" => $supportemails, "affiliateemails" => $affiliateemails];
        $where = ["id" => $contactid];
        update_query($table, $array, $where);
        run_hook("ContactEdit", array_merge(["userid" => $userid, "contactid" => $contactid, "olddata" => $oldcontactdata], $array));
    }
    $queryStr .= "&success=1";
    redir($queryStr);
}
if ($action == "delete") {
    check_token("WHMCS.admin.default");
    $client = new WHMCS\Client($userid);
    $client->deleteContact($contactid);
    redir("userid=" . $userid);
}
ob_start();
$infobox = "";
$sessionErrors = WHMCS\Session::startGetDeleteAndRelease("profilevalidationerror");
if ($sessionErrors && 0 < count($sessionErrors)) {
    infoBox(AdminLang::trans("global.validationerror"), implode("<br>", $sessionErrors), "error");
}
if ($whmcs->get_req_var("success")) {
    infoBox(AdminLang::trans("global.changesuccess"), AdminLang::trans("global.changesuccessdesc"), "success");
}
echo $infobox;
echo "\n<div class=\"context-btn-container\">\n<div class=\"text-left\">\n<form action=\"";
echo $_SERVER["PHP_SELF"];
echo "\" method=\"get\">\n<input type=\"hidden\" name=\"userid\" value=\"";
echo $userid;
echo "\">\n";
echo $aInt->lang("clientsummary", "contacts");
echo ": <select name=\"contactid\" onChange=\"submit()\" class=\"form-control select-inline\">\n";
$result = select_query("tblcontacts", "", ["userid" => $userid], "firstname` ASC,`lastname", "ASC");
while ($data = mysql_fetch_array($result)) {
    $contactlistid = $data["id"];
    if (!$contactid) {
        $contactid = $contactlistid;
    }
    $contactlistfirstname = $data["firstname"];
    $contactlistlastname = $data["lastname"];
    $contactlistemail = $data["email"];
    echo "<option value=\"" . $contactlistid . "\"";
    if ($contactlistid == $contactid) {
        echo " selected";
    }
    echo ">" . $contactlistfirstname . " " . $contactlistlastname . " - " . $contactlistemail . "</option>";
}
if (!$contactid) {
    $contactid = "addnew";
}
echo "<option value=\"addnew\"";
if ($contactid == "addnew") {
    echo " selected";
}
echo ">";
echo $aInt->lang("global", "addnew");
echo "</option>\n</select>\n<noscript>\n<input type=\"submit\" value=\"";
echo $aInt->lang("global", "go");
echo "\" class=\"btn btn-default\" />\n</noscript>\n</form>\n</div>\n</div>\n\n";
$aInt->deleteJSConfirm("deleteContact", "clients", "deletecontactconfirm", "?action=delete&userid=" . $userid . "&contactid=");
if ($contactid && $contactid != "addnew") {
    $contact = WHMCS\User\Client\Contact::whereUserid($userid)->whereId($contactid)->first();
    if (is_null($contact)) {
        redir("userid=" . $userid);
    }
    $contactid = $contact->id;
    $firstname = $contact->firstname;
    $lastname = $contact->lastname;
    $companyname = $contact->companyname;
    $email = $contact->email;
    $address1 = $contact->address1;
    $address2 = $contact->address2;
    $city = $contact->city;
    $state = $contact->state;
    $postcode = $contact->postcode;
    $country = $contact->country;
    $phonenumber = $contact->phoneNumberFormatted;
    $taxId = $contact->taxId;
    $generalemails = $contact->generalemails;
    $productemails = $contact->productemails;
    $domainemails = $contact->domainemails;
    $invoiceemails = $contact->invoiceemails;
    $supportemails = $contact->supportemails;
    $affiliateemails = $contact->affiliateemails;
}
echo "\n<form method=\"post\" action=\"";
echo $_SERVER["PHP_SELF"];
echo "?action=save&userid=";
echo $userid;
echo "&contactid=";
echo $contactid;
echo "\">\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"15%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "firstname");
echo "</td><td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250\" name=\"firstname\" tabindex=\"1\" value=\"";
echo $firstname;
echo "\"></td><td width=\"15%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "address");
echo " 1</td><td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250\" name=\"address1\" tabindex=\"7\" value=\"";
echo $address1;
echo "\"></td></tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("fields", "lastname");
echo "</td><td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250\" name=\"lastname\" tabindex=\"2\" value=\"";
echo $lastname;
echo "\"></td><td class=\"fieldlabel\">";
echo $aInt->lang("fields", "address");
echo " 2</td><td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250 input-inline\" name=\"address2\" tabindex=\"8\" value=\"";
echo $address2;
echo "\"> <font color=#cccccc><small>(";
echo $aInt->lang("global", "optional");
echo ")</small></font></td></tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("fields", "companyname");
echo "</td><td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250 input-inline\" name=\"companyname\" tabindex=\"3\" value=\"";
echo $companyname;
echo "\"> <font color=#cccccc><small>(";
echo $aInt->lang("global", "optional");
echo ")</small></font></td><td class=\"fieldlabel\">";
echo $aInt->lang("fields", "city");
echo "</td><td class=\"fieldarea\"><input type=\"text\" tabindex=\"9\" class=\"form-control input-250\" name=\"city\" value=\"";
echo $city;
echo "\"></td></tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
echo $aInt->lang("fields", "email");
echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" class=\"form-control input-300\" name=\"email\" tabindex=\"4\" value=\"";
echo $email;
echo "\">\n    </td>\n    <td class=\"fieldlabel\">\n        ";
echo $aInt->lang("fields", "state");
echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" class=\"form-control input-250\" name=\"state\" data-selectinlinedropdown=\"1\" tabindex=\"10\" value=\"";
echo $state;
echo "\">\n    </td>\n</tr>\n\n<tr>\n    ";
if (WHMCS\Billing\Tax\Vat::isUsingNativeField()) {
    echo "        <td class=\"fieldlabel\">";
    echo AdminLang::trans(WHMCS\Billing\Tax\Vat::getLabel("fields"));
    echo "</td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" class=\"form-control input-250\" name=\"tax_id\" value=\"";
    echo $taxId;
    echo "\">\n        </td>\n    ";
} else {
    echo "        <td class=\"fieldlabel form-field-hidden-on-respond\"></td>\n        <td class=\"fieldarea form-field-hidden-on-respond\"></td>\n    ";
}
echo "    <td class=\"fieldlabel\">\n        ";
echo $aInt->lang("fields", "postcode");
echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" tabindex=\"11\" class=\"form-control input-150\" name=\"postcode\" value=\"";
echo $postcode;
echo "\">\n    </td>\n</tr>\n\n<tr>\n    <td class=\"fieldlabel form-field-hidden-on-respond\"></td>\n    <td class=\"fieldarea form-field-hidden-on-respond\"></td>\n    <td class=\"fieldlabel\">\n        ";
echo $aInt->lang("fields", "country");
echo "    </td>\n    <td class=\"fieldarea\">";
echo getCountriesDropDown($country, "", "12");
echo "</td></tr>\n<tr>\n    <td class=\"fieldlabel form-field-hidden-on-respond\"></td>\n    <td class=\"fieldarea form-field-hidden-on-respond\"></td>\n    <td class=\"fieldlabel\">\n        ";
echo AdminLang::trans("fields.phonenumber");
echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\"\n               class=\"form-control input-200\"\n               name=\"phonenumber\"\n               tabindex=\"13\"\n               value=\"";
echo $phonenumber;
echo "\"\n        >\n    </td>\n</tr>\n<tr id=\"rowEmailPreferences\">\n    <td class=\"fieldlabel\">\n        ";
echo AdminLang::trans("fields.emailnotifications");
echo "    </td>\n    <td class=\"fieldarea\" colspan=\"3\">\n        ";
$tabIndex = 14;
foreach (WHMCS\Mail\Emailer::CLIENT_EMAILS as $emailType) {
    $checkedVar = $emailType . "emails";
    $langType = $emailType;
    if ($emailType == "contact") {
        $langType .= "Contact";
    }
    echo "            <label class=\"checkbox-inline\">\n                <input type=\"hidden\" name=\"email_preferences[";
    echo $emailType;
    echo "]\" value=\"0\">\n                <input type=\"checkbox\"\n                       name=\"email_preferences[";
    echo $emailType;
    echo "]\"\n                       tabindex=\"";
    echo $tabIndex;
    echo "\"\n                       value=\"1\"\n                    ";
    echo ${$checkedVar} ? "checked=\"checked\"" : "";
    echo "                >\n                ";
    echo AdminLang::trans("emailPreferences." . $langType);
    echo "            </label><br>\n            ";
    $tabIndex++;
}
echo "        <button type=\"button\"\n                class=\"btn btn-sm btn-check-all\"\n                data-checkbox-container=\"rowEmailPreferences\"\n                data-btn-check-toggle=\"1\"\n                id=\"btnSelectAll-rowEmailPreferences\"\n                data-label-text-select=\"";
echo AdminLang::trans("global.checkall");
echo "\"\n                data-label-text-deselect=\"";
echo AdminLang::trans("global.uncheckAll");
echo "\"\n        >\n            ";
echo AdminLang::trans("global.checkall");
echo "        </button>\n    </td>\n</tr>\n</table>\n\n<div class=\"btn-container\">\n    ";
if ($contactid != "addnew") {
    echo "<input type=\"submit\" value=\"";
    echo $aInt->lang("global", "savechanges");
    echo "\" class=\"btn btn-primary\" tabindex=\"";
    echo $tabIndex++;
    echo "\" /> <input type=\"reset\" value=\"";
    echo $aInt->lang("global", "cancelchanges");
    echo "\" class=\"button btn btn-default\" tabindex=\"";
    echo $tabIndex++;
    echo "\" /><br />\n    <a href=\"#\" onClick=\"deleteContact('";
    echo $contactid;
    echo "');return false\" style=\"color:#cc0000\"><b>";
    echo $aInt->lang("global", "delete");
    echo "</b></a>";
} else {
    echo "<input type=\"submit\" value=\"";
    echo $aInt->lang("clients", "addcontact");
    echo "\" class=\"btn btn-primary\" tabindex=\"";
    echo $tabIndex++;
    echo "\" /> <input type=\"reset\" value=\"";
    echo $aInt->lang("global", "cancelchanges");
    echo "\" class=\"button btn btn-default\" tabindex=\"";
    echo $tabIndex++;
    echo "\" />";
}
echo "</div>\n\n</form>\n\n";
$jscode .= "var stateNotRequired = true;";
$jquerycode .= "WHMCS.form.register();";
echo WHMCS\View\Asset::jsInclude("StatesDropdown.js");
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
