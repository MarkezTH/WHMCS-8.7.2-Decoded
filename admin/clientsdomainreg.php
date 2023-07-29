<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Perform Registrar Operations");
$aInt->title = $aInt->lang("domains", "regtransfer");
$aInt->sidebar = "clients";
$aInt->icon = "clientsprofile";
$aInt->requiredFiles(["clientfunctions", "registrarfunctions"]);
if ($action == "do") {
    check_token("WHMCS.admin.default");
}
ob_start();
$domainModel = WHMCS\Domain\Domain::find($domainid);
if (!$domainModel) {
    $aInt->gracefulExit("Domain ID Not Found");
}
$ac = App::getFromRequest("ac");
$transfersecret = App::getFromRequest("transfersecret");
$sendregisterconfirm = App::getFromRequest("sendregisterconfirm");
$userid = $domainModel->userid;
$domain = $domainModel->domain;
$orderid = $domainModel->orderid;
$registrar = $domainModel->getRegistrarModuleDisplayName(AdminLang::trans("global.none"));
$registrationperiod = $domainModel->registrationperiod;
$idnLanguage = "";
$idnLanguageExtra = $domainModel->extra()->whereName("idnLanguage")->first();
if ($idnLanguageExtra) {
    $idnLanguage = $idnLanguageExtra->value;
}
if (App::isInRequest("idnLanguage")) {
    $requestIdnLanguage = App::getFromRequest("idnLanguage");
    if ($idnLanguage !== $requestIdnLanguage) {
        $idnLanguage = $requestIdnLanguage;
        $extraDetails = $domainModel->extra()->firstOrNew(["domain_id" => $domainid, "name" => "idnLanguage"]);
        $extraDetails->value = $idnLanguage;
        $extraDetails->save();
    }
}
$params = [];
$params["domainid"] = $domainid;
$nsvals = [];
if (!$ns1 && !$ns2) {
    try {
        $nameservers = $domainModel->getBestNameserversForNewOrder();
    } catch (Throwable $e) {
        $nameservers = [];
    }
    foreach ($nameservers as $key => $value) {
        $nsvals[$key + 1] = $value;
    }
}
if (!$transfersecret) {
    $order = $domainModel->order;
    if ($order) {
        $transfersecret = $order->getEppCodeByDomain($domain);
    }
}
if (is_array($_POST)) {
    for ($i = 1; $i <= 5; $i++) {
        if (isset($_POST["ns" . $i])) {
            $nsvals[$i] = $_POST["ns" . $i];
        }
    }
}
echo "\n<form id=\"frmDomainAction\" method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "?domainid=";
echo $domainid;
echo "&action=do&ac=";
echo $ac;
echo "\">\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"20%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "registrar");
echo "</td><td class=\"fieldarea\">";
echo ucfirst($registrar);
echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("permissions", "action");
echo "</td><td class=\"fieldarea\">";
if ($ac == "") {
    echo $aInt->lang("domains", "actionreg");
} else {
    echo $aInt->lang("domains", "transfer");
}
echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("fields", "domain");
echo "</td><td class=\"fieldarea\">";
echo $domain;
echo "</td></tr>\n";
if (!$ac && $domainModel->domain !== $domainModel->domainPunycode) {
    $languages = array_merge(["" => AdminLang::trans("domains.selectIdnLanguage")], WHMCS\Domains\Idna::getLanguages());
    echo "    <tr>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("domains.idnLanguage");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <div class=\"form-group no-margin\">\n                ";
    echo (new WHMCS\Form())->dropdown("idnLanguage", $languages, $idnLanguage);
    echo "                <div class=\"field-error-msg\">\n                    ";
    echo AdminLang::trans("domains.idnLanguageRequired");
    echo "                </div>\n            </div>\n        </td>\n    </tr>\n";
}
echo "<tr><td class=\"fieldlabel\">";
echo $aInt->lang("domains", "regperiod");
echo "</td><td class=\"fieldarea\">";
echo $registrationperiod;
echo " ";
echo $aInt->lang("domains", "years");
echo "</td></tr>\n";
for ($i = 1; $i <= 5; $i++) {
    echo "<tr><td class=\"fieldlabel\">";
    echo $aInt->lang("domains", "nameserver") . " " . $i;
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"ns";
    echo $i;
    echo "\" size=\"40\" value=\"";
    echo $nsvals[$i];
    echo "\" /> ";
    if ($i == 1) {
        echo $autonsdesc;
    }
    echo "</td></tr>";
}
if ($ac == "transfer") {
    echo "<tr><td class=\"fieldlabel\">";
    echo $aInt->lang("domains", "eppcode");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"transfersecret\" size=\"20\" value=\"";
    echo WHMCS\Input\Sanitize::makeSafeForOutput($transfersecret);
    echo "\" /> (";
    echo $aInt->lang("domains", "ifreq");
    echo ")</td></tr>";
}
echo "<tr><td class=\"fieldlabel\">";
echo $aInt->lang("orders", "sendconfirmation");
echo "</td><td class=\"fieldarea\"><input type=\"checkbox\" name=\"sendregisterconfirm\" checked /> ";
echo $aInt->lang("domains", "sendregisterconfirm");
echo "</td></tr>\n</table>\n\n";
if ($action == "do") {
    define("NO_QUEUE", true);
    $emptyNameservers = true;
    $result = [];
    for ($i = 1; $i <= 5; $i++) {
        $params["ns" . $i] = $_POST["ns" . $i];
        if ($emptyNameservers && $params["ns" . $i]) {
            $emptyNameservers = false;
        }
    }
    $params["transfersecret"] = $_POST["transfersecret"];
    if ($emptyNameservers) {
        $result["error"] = AdminLang::trans("domains.noNameservers");
    } else {
        if (!$ac) {
            $result = RegRegisterDomain($params);
        } else {
            $result = RegTransferDomain($params);
        }
    }
    if ($result["error"]) {
        infoBox($aInt->lang("global", "erroroccurred"), $result["error"], "error");
        echo $infobox;
    } else {
        if ($result["pending"]) {
            infoBox($aInt->lang("status", "pending"), $result["pendingMessage"], "info");
        } else {
            if (!$ac) {
                infoBox($aInt->lang("global", "success"), $aInt->lang("domains", "regsuccess"), "success");
            } else {
                infoBox($aInt->lang("global", "success"), $aInt->lang("domains", "transuccess"), "success");
            }
        }
        echo "<br />" . $infobox;
        echo "\n<p align=\"center\"><input type=\"button\" value=\"";
        echo $aInt->lang("global", "continue");
        echo " &raquo;\" class=\"btn btn-default\" onClick=\"window.location='clientsdomains.php?userid=";
        echo $userid;
        echo "&domainid=";
        echo $domainid;
        echo "'\"></p>\n\n";
        if ($sendregisterconfirm == "on") {
            if ($ac == "") {
                sendMessage("Domain Registration Confirmation", $domainid);
            } else {
                sendMessage("Domain Transfer Initiated", $domainid);
            }
        }
        $complete = "true";
    }
}
if ($complete != "true") {
    $replace = $ac == "" ? $aInt->lang("domains", "actionreg") : $aInt->lang("domains", "transfer");
    $question = str_replace("%s", $replace, $aInt->lang("domains", "actionquestion"));
    echo "\n<p align=center>";
    echo $question;
    echo "</p>\n<p align=center><input type=\"submit\" value=\" ";
    echo $aInt->lang("global", "yes");
    echo " \" class=\"btn btn-success\"> <input type=\"button\" value=\" ";
    echo $aInt->lang("global", "no");
    echo " \" class=\"btn btn-default\" onClick=\"window.location='clientsdomains.php?userid=";
    echo $userid;
    echo "&domainid=";
    echo $domainid;
    echo "'\">\n\n";
}
echo "\n</form>\n\n";
$jQueryCode = "jQuery('#frmDomainAction').on('submit', function(e) {\n    var idnLangInput = jQuery('select[name=\"idnLanguage\"]');\n    if (!idnLangInput.length || (idnLangInput.length && idnLangInput.val())) {\n        return true;\n    }\n    e.preventDefault();\n    idnLangInput.showInputError();\n    return false;\n});";
$content = ob_get_contents();
ob_end_clean();
$aInt->jquerycode = $jQueryCode;
$aInt->content = $content;
$aInt->display();
