<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Mass Mail", false);
$aInt->title = AdminLang::trans("utilities.emailCampaigns.title");
$aInt->sidebar = "utilities";
$aInt->icon = "massmail";
$aInt->helplink = "Mass Mail";
$aInt->requiredFiles(["customfieldfunctions"]);
$clientgroups = getClientGroups();
$jscode = "function showMailOptions(type) {\n    \$(\"#product_criteria\").slideUp();\n    \$(\"#addon_criteria\").slideUp();\n    \$(\"#domain_criteria\").slideUp();\n    \$(\"#client_criteria\").slideDown();\n    if (type) \$(\"#\"+type+\"_criteria\").slideDown();\n}";
$clientCountries = WHMCS\Database\Capsule::table("tblclients")->distinct("country")->orderBy("country")->pluck("country")->all();
$clientLanguages = WHMCS\Database\Capsule::table("tblclients")->distinct("language")->orderBy("language")->pluck("language")->all();
$campaignName = "";
$emailType = "";
$clientGroup = [];
$countries = $clientCountries;
$languages = $clientLanguages;
$clientStatuses = WHMCS\Utility\Status::CLIENT_STATUSES;
$selectedProducts = [];
$selectedProductStatuses = [];
$selectedAddonStatuses = [];
$selectedDomainStatuses = [];
$servers = [];
$customFieldValues = [];
$sendForEach = false;
$existingConfiguration = WHMCS\Session::getAndDelete("MassMailConfiguration");
$alert = "";
if ($existingConfiguration && is_array($existingConfiguration)) {
    $campaignName = $existingConfiguration["campaign_name"];
    $emailType = $existingConfiguration["email_type"];
    $clientGroup = $existingConfiguration["client_group"];
    $countries = $existingConfiguration["client_country"];
    $languages = $existingConfiguration["client_language"];
    $clientStatuses = $existingConfiguration["client_status"];
    $selectedProducts = $existingConfiguration["package_ids"];
    $selectedProductStatuses = $existingConfiguration["product_statuses"];
    $selectedAddons = $existingConfiguration["addon_ids"];
    $selectedAddonStatuses = $existingConfiguration["addon_statuses"];
    $selectedDomainStatuses = $existingConfiguration["domain_statuses"];
    $servers = $existingConfiguration["servers"];
    $customFieldValues = $existingConfiguration["custom_fields"];
    $sendForEach = $existingConfiguration["send_for_each"];
    $alert = WHMCS\View\Helper::alert(AdminLang::trans("sendmessage.noreceiptientsdesc"));
}
if ($emailType === "general") {
    $emailType = "";
}
$jQueryCode = "showMailOptions('" . $emailType . "');\n\njQuery('#frmMassMailConfigure').on('submit', function() {\n    jQuery(this).find('.form-group').removeClass('has-error');\n    jQuery(this).find('.field-error-msg').hide();\n    var input = jQuery('#inputCampaignName');\n    if (input.val() === '') {\n        input.showInputError();\n        jQuery('html, body').animate({\n            scrollTop: input.closest('tr').offset().top - 10\n        }, 500);\n        return false;\n    }\n    return true;\n})";
ob_start();
if (!WHMCS\Marketing\EmailSubscription::isUsingOptInField()) {
    $title = AdminLang::trans("marketingConsent.conversionTitle");
    $link = AdminLang::trans("global.clickhere");
    $url = routePath("admin-marketing-consent-convert");
    $link = "<a href=\"" . $url . "\" class=\"open-modal\" data-modal-title=\"" . $title . "\">" . $link . "</a>";
    $body = AdminLang::trans("marketingConsent.conversionData", [":clickHere" => $link]);
    echo "<div id=\"marketingConsentAlert\" class=\"alert alert-update-banner-grey marketing-consent-alert\"><h2><i class=\"fas fa-sync fa-fw\"></i><strong>" . $title . "</strong></h2>" . $body . "</div>";
}
if ($alert) {
    echo $alert;
}
$aInt->title = AdminLang::trans("utilities.emailCampaigns.title");
$stepLang = AdminLang::trans("global.stepOfStep", [":step" => 1, ":steps" => 2]);
$configureCampaignText = AdminLang::trans("utilities.emailCampaigns.configureRecipients");
echo "<h2>";
echo $stepLang;
echo ": ";
echo $configureCampaignText;
echo "</h2>\n\n<p>";
echo $aInt->lang("massmail", "pagedesc");
echo "</p>\n\n<form method=\"post\" action=\"sendmessage.php?type=massmail\" id=\"frmMassMailConfigure\">\n\n<h2>";
echo $aInt->lang("massmail", "messagetype");
echo "</h2>\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td width=\"20%\" class=\"fieldlabel\">\n            ";
echo AdminLang::trans("fields.campaignName");
echo "        </td>\n        <td class=\"fieldarea\">\n            <div class=\"form-group no-margin\">\n                <input id=\"inputCampaignName\"\n                       type=\"text\"\n                       class=\"form-control input-500\"\n                       name=\"campaign_name\"\n                       value=\"";
echo $campaignName;
echo "\"\n                       maxlength=\"250\"\n                >\n                <span class=\"field-error-msg\">";
echo AdminLang::trans("validation.filled", [":attribute" => AdminLang::trans("fields.campaignName")]);
echo "</span>\n            </div>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
echo $aInt->lang("massmail", "emailtype");
echo "        </td>\n        <td class=\"fieldarea\">\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"emailtype\" id=\"typegen\" value=\"general\" onclick=\"showMailOptions('')\"";
echo !$emailType || $emailType === "general" ? " checked=\"checked\"" : "";
echo " />\n                ";
echo AdminLang::trans("emailtpls.type.general");
echo "            </label>\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"emailtype\" id=\"typeprod\" value=\"product\" onclick=\"showMailOptions('product')\"";
echo $emailType === "product" ? " checked=\"checked\"" : "";
echo " />\n                ";
echo $aInt->lang("fields", "product");
echo "            </label>\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"emailtype\" id=\"typeaddon\" value=\"addon\" onclick=\"showMailOptions('addon')\"";
echo $emailType === "addon" ? " checked=\"checked\"" : "";
echo " />\n                ";
echo $aInt->lang("fields", "addon");
echo "            </label>\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"emailtype\" id=\"typedom\" value=\"domain\" onclick=\"showMailOptions('domain')\"";
echo $emailType === "domain" ? " checked=\"checked\"" : "";
echo " />\n                ";
echo $aInt->lang("fields", "domain");
echo "            </label>\n        </td>\n    </tr>\n</table>\n\n<div id=\"client_criteria\" style=\"display:none;\">\n\n<br />\n\n<h2>";
echo $aInt->lang("massmail", "clientcriteria");
echo "</h2>\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"20%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "clientgroup");
echo "</td><td class=\"fieldarea\"><select name=\"clientgroup[]\" size=\"4\" multiple=\"true\" class=\"form-control\">";
foreach ($clientgroups as $groupid => $data) {
    $selected = "";
    if (in_array($groupid, $clientGroup)) {
        $selected = " selected=\"selected\"";
    }
    echo "<option value=\"" . $groupid . "\"" . $selected . ">" . $data["name"] . "</option>";
}
echo "</select></td></tr>\n";
$customfields = getCustomFields("client", "", "", true, "", $customFieldValues);
foreach ($customfields as $customfield) {
    if (strtolower($customfield["type"]) !== "password") {
        echo "<tr><td class=\"fieldlabel\">" . $customfield["name"] . "</td><td class=\"fieldarea\">";
        if ($customfield["type"] == "tickbox") {
            $noFilter = $customfield["value"] == "" ? " checked" : "";
            $checkedOnly = $customfield["value"] == "cfon" ? " checked" : "";
            $uncheckedOnly = $customfield["value"] == "cfoff" ? " checked" : "";
            $noFilterText = AdminLang::trans("sendmessage.noFilter");
            $checkedText = AdminLang::trans("sendmessage.checkedOnly");
            $uncheckedText = AdminLang::trans("sendmessage.uncheckedOnly");
            echo "<input type=\"radio\" name=\"customfield[" . $customfield["id"] . "]\" value=\"\"" . $noFilter . "/> " . $noFilterText . " \n<input type=\"radio\" name=\"customfield[" . $customfield["id"] . "]\" value=\"cfon\"" . $checkedOnly . "/> " . $checkedText . " \n<input type=\"radio\" name=\"customfield[" . $customfield["id"] . "]\" value=\"cfoff\"" . $uncheckedOnly . "/> " . $uncheckedText;
        } else {
            if ($customfield["type"] === "dropdown") {
                echo str_replace("\"><option value=\"", "\"><option value=\"\">" . $aInt->lang("global", "any") . "</option><option value=\"", $customfield["input"]);
            } else {
                echo $customfield["input"];
            }
        }
        echo "</td></tr>";
    }
}
echo "<tr>\n    <td class=\"fieldlabel\">";
echo $aInt->lang("fields", "country");
echo "</td>\n    <td class=\"fieldarea\">\n        <select name=\"clientcountry[]\" size=\"4\" multiple=\"true\" class=\"form-control\">";
$countryHelper = new WHMCS\Utility\Country();
foreach ($clientCountries as $countryCode) {
    if ($countryHelper->isValidCountryCode($countryCode)) {
        $selected = "";
        if (in_array($countryCode, $countries)) {
            $selected = " selected=\"selected\"";
        }
        echo "<option value=\"" . $countryCode . "\" " . $selected . ">" . $countryHelper->getName($countryCode) . "</option>";
    }
}
echo "        </select>\n    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("global", "language");
echo "</td><td class=\"fieldarea\"><select name=\"clientlanguage[]\" size=\"4\" multiple=\"true\" class=\"form-control\"><option value=\"\" selected>";
echo $aInt->lang("global", "default");
echo "</option>";
foreach ($clientLanguages as $clientLanguage) {
    $language = $displayLanguage = $clientLanguage;
    if (trim($language)) {
        $selected = "";
        if (in_array($language, $languages)) {
            $selected = " selected=\"selected\"";
        }
        echo "<option value=\"" . $language . "\"" . $selected . ">" . ucfirst($displayLanguage) . "</option>";
    }
}
echo "</select></td></tr>\n    <tr>\n        <td class=\"fieldlabel\">";
echo AdminLang::trans("massmail.clientstatus");
echo "</td>\n        <td class=\"fieldarea\">\n            <select name=\"clientstatus[]\" size=\"3\" multiple=\"multiple\" class=\"form-control\">\n                ";
foreach (WHMCS\Utility\Status::CLIENT_STATUSES as $clientStatus) {
    $selected = "";
    if (in_array($clientStatus, $clientStatuses)) {
        $selected = " selected=\"selected\"";
    }
    echo "<option value=\"" . $clientStatus . "\"" . $selected . ">" . AdminLang::trans("status." . strtolower($clientStatus)) . "</option>";
}
echo "            </select>\n        </td>\n    </tr>\n</table>\n\n</div>\n<div id=\"product_criteria\" style=\"display:none;\">\n\n<br />\n\n<h2>";
echo $aInt->lang("massmail", "productservicecriteria");
echo "</h2>\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr>\n    <td width=\"20%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "product");
echo "</td>\n    <td class=\"fieldarea\"><select name=\"productids[]\" size=\"10\" multiple=\"true\" class=\"form-control\">";
$products = new WHMCS\Product\Products();
$productsList = $products->getProducts();
foreach ($productsList as $data) {
    $id = $data["id"];
    $name = $data["name"];
    $groupname = $data["groupname"];
    $selected = "";
    if (in_array($id, $selectedProducts)) {
        $selected = " selected=\"selected\"";
    }
    echo "<option value=\"" . $id . "\"" . $selected . ">" . $groupname . " - " . $name . "</option>";
}
echo "</select></td></tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("massmail", "productservicestatus");
echo "</td>\n    <td class=\"fieldarea\">\n        <select name=\"productstatus[]\" size=\"5\" multiple=\"true\" class=\"form-control\">\n            ";
foreach (WHMCS\Utility\Status::SERVICE_STATUSES as $serviceStatus) {
    $translation = AdminLang::trans("status." . strtolower($serviceStatus));
    $selected = "";
    if (in_array($serviceStatus, $selectedProductStatuses)) {
        $selected = " selected=\"selected\"";
    }
    echo "<option value=\"" . $serviceStatus . "\"" . $selected . ">" . $translation . "</option>";
}
echo "        </select>\n    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("massmail", "assignedserver");
echo "</td><td class=\"fieldarea\"><select name=\"server[]\" size=\"5\" multiple=\"true\" class=\"form-control\">";
$result = select_query("tblservers", "", "", "name", "ASC");
while ($data = mysql_fetch_array($result)) {
    $id = $data["id"];
    $name = $data["name"];
    $selected = "";
    if (in_array($id, $servers)) {
        $selected = " selected=\"selected\"";
    }
    echo "<option value=\"" . $id . "\"" . $selected . ">" . $name . "</option>";
}
echo "</select></td></tr>\n<tr>\n    <td class=\"fieldlabel\">";
echo $aInt->lang("massmail", "sendforeachdomain");
echo "</td>\n    <td class=\"fieldarea\">\n        <input type=\"hidden\" name=\"sendforeach\" value=\"0\">\n        <input type=\"checkbox\" name=\"sendforeach\"";
echo $sendForEach ? " checked=\"checked\"" : "";
echo " value=\"1\">\n        ";
echo $aInt->lang("massmail", "tickboxsendeverymatchingdomain");
echo "    </td>\n</tr>\n</table>\n\n</div>\n<div id=\"addon_criteria\" style=\"display:none;\">\n\n<br />\n\n<h2>";
echo $aInt->lang("massmail", "addoncriteria");
echo "</h2>\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"20%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "addon");
echo "</td><td class=\"fieldarea\"><select name=\"addonids[]\" size=\"10\" multiple=\"true\" class=\"form-control\">";
$result = select_query("tbladdons", "id,name", "", "name", "ASC");
while ($data = mysql_fetch_array($result)) {
    $id = $data["id"];
    $addonname = $data["name"];
    echo "<option value=\"" . $id . "\">" . $addonname . "</option>";
}
echo "</select></td></tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
echo AdminLang::trans("massmail.addonstatus");
echo "</td>\n        <td class=\"fieldarea\">\n            <select name=\"addonstatus[]\" size=\"5\" multiple=\"true\" class=\"form-control\">\n                ";
foreach (WHMCS\Utility\Status::SERVICE_STATUSES as $serviceStatus) {
    $translation = AdminLang::trans("status." . strtolower($serviceStatus));
    $selected = "";
    if (in_array($serviceStatus, $selectedAddonStatuses)) {
        $selected = " selected=\"selected\"";
    }
    echo "<option value=\"" . $serviceStatus . "\"" . $selected . ">" . $translation . "</option>";
}
echo "            </select>\n        </td>\n    </tr>\n</table>\n\n</div>\n<div id=\"domain_criteria\" style=\"display:none;\">\n\n<br />\n\n<h2>";
echo $aInt->lang("massmail", "domaincriteria");
echo "</h2>\n\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=\"20%\" class=\"fieldlabel\">";
echo AdminLang::trans("massmail.domainstatus");
echo "</td>\n            <td class=\"fieldarea\">\n                <select name=\"domainstatus[]\" size=\"5\" multiple=\"multiple\" class=\"form-control\">\n                    ";
echo (new WHMCS\Domain\Status())->translatedDropdownOptions($selectedDomainStatuses);
echo "                </select>\n            </td>\n        </tr>\n    </table>\n\n</div>\n\n<div class=\"btn-container\">\n    <input id=\"btnSubmit\" type=\"submit\" value=\"";
echo $aInt->lang("massmail", "composemsg");
echo "\" class=\"btn btn-default\">\n</div>\n\n</form>\n\n<p>";
echo $aInt->lang("massmail", "footnote");
echo "</p>\n\n";
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jscode = $jscode;
$aInt->jquerycode = $jQueryCode;
$aInt->display();
