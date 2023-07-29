<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Configure Banned Emails");
$aInt->title = AdminLang::trans("bans.emailtitle");
$aInt->sidebar = "config";
$aInt->icon = "configbans";
$aInt->helplink = "Security/Ban Control";
$aInt->requireAuthConfirmation();
if (isset($action) && $action == "add") {
    check_token("WHMCS.admin.default");
    $error = false;
    if (!WHMCS\Domains\Domain::isValidDomain($domain)) {
        infoBox(AdminLang::trans("global.error"), AdminLang::trans("bans.domainaddfail"));
        $error = true;
    } else {
        if (WHMCS\Security\BanControl\EmailDomain::where("domain", $domain)->exists()) {
            infoBox(AdminLang::trans("global.error"), AdminLang::trans("bans.domainexistsfail"));
            $error = true;
        }
    }
    if (!$error) {
        WHMCS\Security\BanControl\EmailDomain::create(["domain" => $domain]);
        logAdminActivity("Banned Email Domain Added: '" . $domain . "'");
        infoBox(AdminLang::trans("bans.emailaddsuccess"), AdminLang::trans("bans.emailaddsuccessinfo"));
    }
}
if (isset($action) && $action == "delete") {
    check_token("WHMCS.admin.default");
    $record = WHMCS\Security\BanControl\EmailDomain::find((int) $id);
    if ($record) {
        $record->delete();
        logAdminActivity("Banned Email Domain Removed: '" . $record->domain . "'");
        infoBox(AdminLang::trans("bans.emaildelsuccess"), AdminLang::trans("bans.emaildelsuccessinfo"));
    }
}
ob_start();
echo $infobox;
$aInt->deleteJSConfirm("doDelete", "bans", "emaildelsure", "?action=delete&id=");
echo $aInt->beginAdminTabs([AdminLang::trans("global.add")], true);
echo "\n<form method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "\">\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr>\n    <td width=\"15%\" class=\"fieldlabel\">";
echo AdminLang::trans("bans.emaildomain");
echo "</td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" name=\"domain\" size=\"50\"> (";
echo AdminLang::trans("bans.onlydomain");
echo ")\n        <input type=\"hidden\" name=\"action\" value=\"add\">\n    </td>\n</tr>\n</table>\n\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
echo AdminLang::trans("bans.addbannedemail");
echo "\" class=\"btn btn-primary\" />\n</div>\n\n</form>\n\n";
echo $aInt->endAdminTabs();
echo "\n<br>\n\n";
$aInt->sortableTableInit("nopagination");
$tabledata = [];
$strDelete = AdminLang::trans("global.delete");
foreach (WHMCS\Security\BanControl\EmailDomain::all() as $data) {
    $delLink = "            <a href=\"#\" onClick=\"doDelete('" . $data->id . "');return false\">\n                <img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $strDelete . "\">\n             </a>";
    $tabledata[] = [$data->domain, $data->count, $delLink];
}
echo $aInt->sortableTable([AdminLang::trans("bans.emaildomain"), AdminLang::trans("bans.usagecount"), ""], $tabledata);
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
