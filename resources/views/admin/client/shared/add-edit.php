<?php

if (!empty($userId)) {
    echo "<div class=\"context-btn-container\">\n    <a href=\"";
    echo routePath("admin-client-consent-history", $userId);
    echo "\" class=\"btn btn-default btn-sm open-modal\" data-modal-title=\"Consent History\">\n        ";
    echo AdminLang::trans("marketingConsent.viewHistory");
    echo "    </a>\n</div>\n";
}
echo "\n";
echo $infoBox;
echo "\n<form id=\"frmAddUser\" method=\"post\" action=\"";
echo $formAction;
echo "\">\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=\"15%\" class=\"fieldlabel\">";
echo AdminLang::trans("fields.firstname");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250\" name=\"firstname\" value=\"";
echo $firstName;
echo "\" tabindex=\"1\"></td>\n            <td class=\"fieldlabel\" width=\"15%\">";
echo AdminLang::trans("fields.address1");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250\" name=\"address1\" value=\"";
echo $addressOne;
echo "\" tabindex=\"8\"></td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.lastname");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250\" name=\"lastname\" value=\"";
echo $lastName;
echo "\" tabindex=\"2\"></td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.address2");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250 input-inline\" name=\"address2\" value=\"";
echo $addressTwo;
echo "\" tabindex=\"9\"> <font color=#cccccc><small>(";
echo AdminLang::trans("global.optional");
echo ")</small></font></td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.companyname");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250 input-inline\" name=\"companyname\" value=\"";
echo $companyName;
echo "\" tabindex=\"3\"> <font color=#cccccc><small>(";
echo AdminLang::trans("global.optional");
echo ")</small></font></td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.city");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250\" name=\"city\" value=\"";
echo $cityName;
echo "\" tabindex=\"10\"></td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.email");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-300\" name=\"email\" value=\"";
echo $emailAddress;
echo "\" tabindex=\"4\"></td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.state");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-250\" name=\"state\" data-selectinlinedropdown=\"1\" value=\"";
echo $stateName;
echo "\" tabindex=\"11\"></td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel";
if (!empty($userId)) {
    echo " form-field-hidden-on-respond";
}
echo "\">";
if (empty($userId)) {
    echo AdminLang::trans("fields.password");
}
echo "</td>\n            <td class=\"fieldarea";
if (!empty($userId)) {
    echo " form-field-hidden-on-respond";
}
echo "\">";
if (empty($userId)) {
    echo "                <div class=\"input-group col-lg-6\" style=\"width: 300px\">\n                    <input type=\"text\" class=\"form-control input-150 input-inline\" name=\"password\" id=\"inputPassword\" autocomplete=\"off\" value=\"";
    echo $password;
    echo "\" onfocus=\"if(this.value=='";
    echo AdminLang::trans("fields.entertochange");
    echo "')this.value=''\" tabindex=\"5\" />\n                ";
}
echo "                ";
if (empty($userId)) {
    echo "                    <span class=\"input-group-btn\">\n                        <button type=\"button\" class=\"btn btn-default generate-password\" data-targetfields=\"inputPassword\">\n                            ";
    echo AdminLang::trans("generatePassword.btnLabel");
    echo "                        </button>\n                    </span>\n                </div>\n                ";
}
echo "            </td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.postcode");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-150\" name=\"postcode\" value=\"";
echo $postCode;
echo "\" tabindex=\"12\"></td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel form-field-hidden-on-respond\"></td>\n            <td class=\"fieldarea form-field-hidden-on-respond\"></td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.country");
echo "</td>\n            <td class=\"fieldarea\">";
echo getCountriesDropDown($countryName, "", 13);
echo "</td>\n        </tr>\n        <tr>\n            ";
if (WHMCS\Billing\Tax\Vat::isUsingNativeField()) {
    echo "                <td class=\"fieldlabel\">\n                    ";
    echo AdminLang::trans(WHMCS\Billing\Tax\Vat::getLabel("fields"));
    echo "                </td>\n                <td class=\"fieldarea\">\n                    <input type=\"text\" name=\"tax_id\" value=\"";
    echo $taxId;
    echo "\" class=\"form-control input-250\"  tabindex=\"7\">\n                </td>\n            ";
} else {
    echo "                <td class=\"fieldlabel form-field-hidden-on-respond\"></td>\n                <td class=\"fieldarea form-field-hidden-on-respond\"></td>\n            ";
}
echo "            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.phonenumber");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" class=\"form-control input-200\" name=\"phonenumber\" value=\"";
echo $phoneNumber;
echo "\" tabindex=\"14\"></td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("global.language");
echo "</td>\n            <td class=\"fieldarea\">\n                <select name=\"language\" class=\"form-control select-inline\" tabindex=\"15\">\n                    <option value=\"\">";
echo AdminLang::trans("global.default");
echo "</option>\n                    ";
foreach (WHMCS\Language\ClientLanguage::getLanguages() as $lang) {
    echo "<option value=\"" . $lang . "\"";
    if ($language && $lang == WHMCS\Language\ClientLanguage::getValidLanguageName($language)) {
        echo " selected=\"selected\"";
    }
    echo ">" . ucfirst($lang) . "</option>";
}
echo "                </select>\n            </td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.paymentmethod");
echo "</td>\n            <td class=\"fieldarea\">";
echo paymentMethodsSelection(AdminLang::trans("clients.changedefault"), 18, $defaultGateway);
echo "</td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.status");
echo "</td>\n            <td class=\"fieldarea\">\n                <select name=\"status\" class=\"form-control select-inline\" tabindex=\"16\">\n                    <option value=\"Active\"";
echo $clientStatus == "Active" ? " selected" : "";
echo ">";
echo AdminLang::trans("status.active");
echo "</option>\n                    <option value=\"Inactive\"";
echo $clientStatus == "Inactive" ? " selected" : "";
echo ">";
echo AdminLang::trans("status.inactive");
echo "</option>\n                    <option value=\"Closed\"";
echo $clientStatus == "Closed" ? " selected" : "";
echo ">";
echo AdminLang::trans("status.closed");
echo "</option>\n                </select>\n            </td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("clients.billingcontact");
echo "</td>\n            <td class=\"fieldarea\">\n                <select name=\"billingcid\" class=\"form-control select-inline\" tabindex=\"19\">\n                    <option value=\"0\">";
echo AdminLang::trans("global.default");
echo "</option>\n                    ";
if ($userId) {
    $result = select_query("tblcontacts", "", ["userid" => $userId], "firstname` ASC,`lastname", "ASC");
    while ($data = mysql_fetch_array($result)) {
        echo "<option value=\"" . $data["id"] . "\"";
        if ($data["id"] == $billingcid) {
            echo " selected";
        }
        echo ">" . $data["firstname"] . " " . $data["lastname"] . "</option>";
    }
}
echo "                </select>\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.clientgroup");
echo "</td>\n            <td class=\"fieldarea\">\n                <select name=\"groupid\" class=\"form-control select-inline\" tabindex=\"17\">\n                    <option value=\"0\">";
echo AdminLang::trans("global.none");
echo "</option>\n                    ";
$result = select_query("tblclientgroups", "", "", "groupname", "ASC");
while ($data = mysql_fetch_assoc($result)) {
    $group_id = $data["id"];
    $group_name = $data["groupname"];
    $group_colour = $data["groupcolour"];
    echo "<option style=\"background-color:" . $group_colour . "\" value=" . $group_id . "";
    if ($group_id == $groupid) {
        echo " selected";
    }
    echo ">" . $group_name . "</option>";
}
echo "                </select>\n            </td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("currencies.currency");
echo "</td>\n            <td class=\"fieldarea\">\n                <select name=\"currency\" class=\"form-control select-inline\" tabindex=\"20\">\n                    ";
$result = select_query("tblcurrencies", "id,code", "", "code", "ASC");
while ($data = mysql_fetch_array($result)) {
    echo "<option value=\"" . $data["id"] . "\"";
    if ($data["id"] == $currency) {
        echo " selected";
    }
    echo ">" . $data["code"] . "</option>";
}
echo "                </select>\n            </td>\n        </tr>\n        ";
if (!WHMCS\Config\Setting::getValue("DisableClientEmailPreferences")) {
    echo "        <tr id=\"rowEmailPreferences\">\n            <td class=\"fieldlabel\">\n                ";
    echo AdminLang::trans("fields.emailnotifications");
    echo "            </td>\n            <td class=\"fieldarea\" colspan=\"3\">\n                ";
    $tabIndex = 21;
    foreach (WHMCS\Mail\Emailer::CLIENT_EMAILS as $emailType) {
        $checkedVar = $emailType . "Emails";
        echo "                    <label class=\"checkbox-inline\">\n                        <input type=\"hidden\" name=\"email_preferences[";
        echo $emailType;
        echo "]\" value=\"0\">\n                        <input type=\"checkbox\"\n                               name=\"email_preferences[";
        echo $emailType;
        echo "]\"\n                               tabindex=\"";
        echo $tabIndex;
        echo "\"\n                               value=\"1\"\n                            ";
        echo ${$checkedVar};
        echo "                        >\n                        ";
        echo AdminLang::trans("emailPreferences." . $emailType);
        echo "                    </label><br>\n                    ";
        $tabIndex++;
    }
    echo "                <button type=\"button\"\n                        class=\"btn btn-sm btn-check-all\"\n                        data-checkbox-container=\"rowEmailPreferences\"\n                        data-btn-check-toggle=\"1\"\n                        id=\"btnSelectAll-rowEmailPreferences\"\n                        data-label-text-select=\"";
    echo AdminLang::trans("global.checkall");
    echo "\"\n                        data-label-text-deselect=\"";
    echo AdminLang::trans("global.uncheckAll");
    echo "\"\n                >\n                    ";
    echo AdminLang::trans("global.checkall");
    echo "                </button>\n            </td>\n        </tr>\n        ";
}
if (!empty($remoteAuth)) {
    echo "        <tr id=\"linkedAccountsReport\"";
    echo $remoteAuth->getEnabledProviders() ?: " hidden";
    echo " >\n            <td class=\"fieldlabel\">";
    echo AdminLang::trans("signIn.linkedTableTitle");
    echo "</td>\n            <td class=\"fieldarea\" colspan=\"3\">\n                <table class=\"clientssummarystats\">\n                    <thead>\n                        <tr>\n                            <th>";
    echo AdminLang::trans("signIn.provider");
    echo "</th>\n                            <th>";
    echo AdminLang::trans("signIn.name");
    echo "</th>\n                            <th>";
    echo AdminLang::trans("signIn.emailAddress");
    echo "</th>\n                            <th></th>\n                        </tr>\n                    </thead>\n                    <tbody>\n                    ";
    if ($remoteAccountLinks) {
        foreach ($remoteAccountLinks as $id => $metadata) {
            echo "<tr class=\"alternating\" id=\"remoteAuth" . $id . "\">";
            echo "<td>" . $metadata->getProviderName() . "</td>";
            echo "<td>";
            echo $metadata->getFullname() ?: "n/a";
            echo "</td><td>";
            echo $metadata->getEmailAddress() ?: "n/a";
            echo "</td>";
            echo "<td><a href=\"#\" class=\"removeAccountLink\" data-authid=\"" . $id . "\"><img src=\"images/icons/delete.png\"></a></td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan=\"4\">" . AdminLang::trans("signIn.emptyTable") . "</td></tr>";
    }
    echo "                    </tbody>\n                </table>\n            </td>\n        </tr>\n        ";
}
echo "        <tr>\n            ";
$tabIndex = 50;
$customFields = getCustomFields("client", "", $userId, "on", "");
$x = 0;
foreach ($customFields as $customField) {
    $x++;
    echo "<td class=\"fieldlabel\">" . $customField["name"] . "</td><td class=\"fieldarea\">" . str_replace(["<input", "<select", "<textarea"], ["<input tabindex=\"" . $tabIndex . "\"", "<select tabindex=\"" . $tabIndex . "\"", "<textarea tabindex=\"" . $tabIndex . "\""], $customField["input"]) . "</td>";
    if ($x % 2 == 0 || $x == count($customFields)) {
        echo "</tr><tr>";
    }
    $tabIndex++;
}
if (!empty($hookReturn)) {
    foreach ($hookReturn as $hookData) {
        foreach ($hookData as $key => $value) {
            echo "<td class=\"fieldlabel\">" . $key . "</td><td class=\"fieldarea\" colspan=\"3\">" . $value . "</td></tr>";
        }
    }
}
echo "            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.settings");
echo "</td>\n            <td class=\"fieldarea\" colspan=\"3\">\n                <div class=\"row\">\n                    <div class=\"col-sm-6 col-lg-4\">\n                        <label class=\"checkbox-inline toggle bottom-margin-5\">\n                            <input type=\"checkbox\" name=\"latefeeoveride\"";
echo $lateFeeOveride ?: " checked";
echo " id=\"latefeeoveride\" class=\"slide-toggle-mini\"> ";
echo AdminLang::trans("clients.latefees");
echo "                        </label>\n                    </div>\n                    <div class=\"col-sm-6 col-lg-4\">\n                        <label class=\"checkbox-inline toggle bottom-margin-5\">\n                            <input type=\"checkbox\" name=\"overideduenotices\"";
echo $overideDueNotices ?: " checked";
echo " id=\"overideduenotices\" class=\"slide-toggle-mini\"> ";
echo AdminLang::trans("clients.overduenotices");
echo "                        </label>\n                    </div>\n                    <div class=\"col-sm-6 col-lg-4\">\n                        <label class=\"checkbox-inline toggle bottom-margin-5\">\n                            <input type=\"checkbox\" name=\"taxexempt\"";
echo !$taxExempt ?: " checked";
echo " id=\"taxexempt\" class=\"slide-toggle-mini\"> ";
echo AdminLang::trans("clients.taxexempt");
echo "                        </label>\n                    </div>\n                    <div class=\"col-sm-6 col-lg-4\">\n                        <label class=\"checkbox-inline toggle bottom-margin-5\">\n                            <input type=\"checkbox\" name=\"separateinvoices\"";
echo !$separateInvoices ?: " checked";
echo " id=\"separateinvoices\" class=\"slide-toggle-mini\"> ";
echo AdminLang::trans("clients.separateinvoices");
echo "                        </label>\n                    </div>\n                    <div class=\"col-sm-6 col-lg-4\">\n                        <label class=\"checkbox-inline toggle bottom-margin-5\">\n                            <input type=\"checkbox\" name=\"disableautocc\"";
echo !$disableAutoCc ?: " checked";
echo " id=\"disableautocc\" class=\"slide-toggle-mini\"> ";
echo AdminLang::trans("clients.disableccprocessing");
echo "                        </label>\n                    </div>\n                    <div class=\"col-sm-6 col-lg-4\">\n                        <label class=\"checkbox-inline toggle bottom-margin-5\">\n                            <input type=\"checkbox\" name=\"marketing_emails_opt_in\"";
echo !$marketingEmailsOptIn ?: " checked";
echo " id=\"marketing_emails_opt_in\" value=\"1\" class=\"slide-toggle-mini\"> ";
echo AdminLang::trans("clients.marketingEmailsOptIn");
echo "                        </label>\n                    </div>\n                    <div class=\"col-sm-6 col-lg-4\">\n                        <label class=\"checkbox-inline toggle bottom-margin-5\">\n                            <input type=\"checkbox\" name=\"overrideautoclose\"";
echo $overrideAutoClose ?: " checked";
echo " id=\"overrideautoclose\" value=\"1\" class=\"slide-toggle-mini\"> ";
echo AdminLang::trans("clients.overrideautoclose");
echo "                        </label>\n                    </div>\n                    <div class=\"col-sm-6 col-lg-4\">\n                        <label class=\"checkbox-inline toggle bottom-margin-5\">\n                            <input type=\"checkbox\" name=\"allowsinglesignon\" value=\"1\"";
echo !$allowSingleSignOn ?: " checked";
echo " id=\"allowsinglesignon\" class=\"slide-toggle-mini\"> ";
echo AdminLang::trans("clients.allowSSO");
echo "                        </label>\n                    </div>\n                </div>\n            </td>\n        </tr>\n        ";
if (empty($userId)) {
    echo "            <tr>\n                <td class=\"fieldlabel\">\n                    ";
    echo AdminLang::trans("fields.owner");
    echo "                </td>\n                <td class=\"fieldarea\" colspan=\"3\">\n                    <label class=\"radio-inline toggle bottom-margin-5\">\n                        <input type=\"radio\"\n                               class=\"check-existing-new-user\"\n                               name=\"existing_user\"\n                               value=\"0\"\n                               checked=\"checked\"\n                        > ";
    echo AdminLang::trans("clients.newUserAccount");
    echo "                    </label><br>\n                    <label class=\"radio-inline toggle bottom-margin-5\">\n                        <input type=\"radio\"\n                               class=\"check-existing-new-user\"\n                               name=\"existing_user\"\n                               value=\"1\"\n                        > ";
    echo AdminLang::trans("clients.existingUserAccount");
    echo "                    </label>\n                    <div id=\"divSelectUser\" style=\"max-width:400px\" class=\"form-field-width-container hidden\">\n                        <select id=\"selectUser\"\n                                name=\"user\"\n                                class=\"form-control selectize selectize-user-search\"\n                                data-value-field=\"id\"\n                                data-allow-empty-option=\"0\"\n                                placeholder=\"";
    echo AdminLang::trans("user.typeToSearch");
    echo "\"\n                                data-user-label=\"";
    echo AdminLang::trans("fields.user");
    echo "\"\n                                data-search-url=\"";
    echo routePath("admin-client-user-associate-search", 0);
    echo "\"\n                        >\n                        </select>\n                    </div>\n                </td>\n            </tr>\n        ";
}
echo "        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.adminnotes");
echo "</td>\n            <td class=\"fieldarea\" colspan=\"3\"><textarea name=\"notes\" rows=\"4\" class=\"form-control\" tabindex=\"";
echo $tabIndex++;
echo "\">";
echo $adminNotes;
echo "</textarea></td>\n        </tr>\n    </table>\n    ";
if (empty($userId)) {
    echo "    <div class=\"btn-container\">\n        <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"sendemail\" checked tabindex=\"";
    echo $tabIndex++;
    echo "\" /> ";
    echo AdminLang::trans("clients.newaccinfoemail");
    echo "</label>\n    </div>\n    <div class=\"btn-container\">\n        <input type=\"submit\" value=\"";
    echo AdminLang::trans("clients.addclient");
    echo "\" class=\"btn btn-primary\" tabindex=\"";
    echo $tabIndex++;
    echo "\" />\n    </div>\n    ";
} else {
    echo "    <div class=\"btn-container\">\n        <input type=\"submit\" value=\"";
    echo AdminLang::trans("global.savechanges");
    echo "\" class=\"btn btn-primary\" tabindex=\"";
    echo $tabIndex++;
    echo "\" />\n        <input type=\"reset\" value=\"";
    echo AdminLang::trans("global.cancelchanges");
    echo "\" class=\"button btn btn-default\" tabindex=\"";
    echo $tabIndex++;
    echo "\">\n    </div>\n    ";
}
echo "</form>\n";
if (empty($userId)) {
    echo "    ";
    echo $generatePasswordForm;
    echo "    <script>\n        jQuery(document).ready(function() {\n            WHMCS.selectize.userSearch();\n            jQuery('.check-existing-new-user').on('change', function() {\n                var tr = jQuery('#divSelectUser');\n                if (jQuery(this).val() === \"1\") {\n                    tr.removeClass('hidden');\n                } else {\n                    tr.addClass('hidden');\n                }\n            });\n\n            // Activate copy to clipboard functionality\n            jQuery('.copy-to-clipboard').click(WHMCS.ui.clipboard.copy);\n\n            // Password Generator\n            jQuery('.generate-password').click(function(e) {\n                jQuery('#frmGeneratePassword').submit();\n                jQuery('#modalGeneratePassword')\n                    .data('targetfields', jQuery(this).data('targetfields'))\n                    .modal('show');\n            });\n            jQuery('#frmGeneratePassword').submit(function(e) {\n                e.preventDefault();\n                var length = parseInt(jQuery('#inputGeneratePasswordLength').val(), 10);\n                // Check length\n                if (length < 8 || length > 64) {\n                    jQuery('#generatePwLengthError').show();\n                    return;\n                } else {\n                    jQuery('#generatePwLengthError').hide();\n                }\n\n                jQuery('#inputGeneratePasswordOutput').val(WHMCS.adminUtils.generatePassword(length));\n\n            });\n            jQuery('#btnGeneratePasswordInsert')\n                .click(WHMCS.ui.clipboard.copy)\n                .click(function(e) {\n                    jQuery(this).closest('.modal').modal('hide');\n                    var targetFields = jQuery(this).closest('.modal').data('targetfields'),\n                        generatedPassword = jQuery('#inputGeneratePasswordOutput');\n                    jQuery('#' + targetFields).val(generatedPassword.val())\n                        .trigger('keyup');\n                    // Remove the generated password.\n                    generatedPassword.val('');\n                });\n        });\n    </script>\n";
}
