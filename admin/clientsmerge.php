<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Edit Clients Details");
$aInt->requiredFiles(["clientfunctions"]);
$userid = (int) App::getFromRequest("userid");
$newuserid = (int) App::getFromRequest("newuserid");
$mergemethod = App::getFromRequest("mergemethod");
ob_start();
if (!$newuserid) {
    echo "<script type=\"text/javascript\">\n\$(document).ready(function(){\n    \$(\"#clientsearchval\").keyup(function () {\n        var useridsearchlength = \$(\"#clientsearchval\").val().length;\n        if (useridsearchlength>2) {\n        WHMCS.http.jqClient.post(whmcsBaseUrl + adminBaseRoutePath + \"/search.php\", { clientsearch: 1, value: \$(\"#clientsearchval\").val(), token: \"" . generate_token("plain") . "\" },\n            function(data){\n                if (data) {\n                    \$(\"#clientsearchresults\").html(data);\n                    \$(\"#clientsearchresults\").slideDown(\"slow\");\n                }\n            });\n        }\n    });\n});\nfunction searchselectclient(userid,name,email) {\n    \$(\"#newuserid\").val(userid);\n    \$(\"#clientsearchresults\").slideUp(\"slow\");\n}\n\nvar whmcsBaseUrl = \"" . WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "\";\nvar adminBaseRoutePath = \"" . WHMCS\Admin\AdminServiceProvider::getAdminRouteBase() . "\";\n</script>\n";
    echo "    <div class=\"alert alert-danger admin-modal-error\" style=\"display: none;\"></div>\n\n<p>";
    echo $aInt->lang("clients", "mergeexplain");
    echo "</p>\n\n<form method=\"post\" action=\"";
    echo $whmcs->getPhpSelf();
    echo "?userid=";
    echo $userid;
    echo "\">\n";
    echo generate_token();
    echo "<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td class=\"fieldlabel\">";
    echo $aInt->lang("clients", "firstclient");
    echo "</td><td class=\"fieldarea\">";
    $result = select_query("tblclients", "", ["id" => $userid]);
    $data = mysql_fetch_array($result);
    $useridselect = $data["id"];
    $firstname = $data["firstname"];
    $lastname = $data["lastname"];
    echo $firstname . " " . $lastname . " (" . $useridselect . ")";
    echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
    echo $aInt->lang("clients", "secondclient");
    echo "</td><td class=\"fieldarea\"><table cellspacing=\"0\" cellpadding=\"0\"><tr><td><input type=\"text\" name=\"newuserid\" id=\"newuserid\" size=\"10\" /></td></tr></table></td></tr>\n<tr><td class=\"fieldarea\" colspan=\"2\"><div align=\"center\"><input type=\"radio\" name=\"mergemethod\" value=\"to1\" id=\"to1\" /> <label for=\"to1\">";
    echo $aInt->lang("clients", "tofirst");
    echo "</label> <input type=\"radio\" name=\"mergemethod\" value=\"to2\" id=\"to2\" checked /> <label for=\"to2\">";
    echo $aInt->lang("clients", "tosecond");
    echo "</label></div></td></tr>\n</table>\n\n<br />\n<div align=\"center\">";
    echo $aInt->lang("global", "clientsintellisearch");
    echo ": <input type=\"text\" id=\"clientsearchval\" size=\"25\" /></div>\n<br />\n<div id=\"clientsearchresults\">\n<div class=\"searchresultheader\">Search Results</div>\n<div class=\"searchresult\" align=\"center\">Matches will appear here as you type</div>\n</div>\n\n</form>\n\n";
} else {
    check_token("WHMCS.admin.default");
    try {
        if ($userid === $newuserid) {
            throw new Exception();
        }
        $otherClient = WHMCS\User\Client::findOrFail($newuserid);
        $viewedClient = WHMCS\User\Client::find($userid);
        if ($mergemethod == "to1") {
            $toClient = $viewedClient;
            $fromClient = $otherClient;
        } else {
            $toClient = $otherClient;
            $fromClient = $viewedClient;
        }
        unset($viewedClient);
        unset($otherClient);
        $fromClient->mergeTo($toClient)->delete();
    } catch (Exception $e) {
        $error = AdminLang::trans("clients.specifyclient");
    }
}
$content = ob_get_contents();
ob_end_clean();
if (!empty($error)) {
    $aInt->jsonResponse(["errorMsg" => $error]);
}
$aInt->jsonResponse(["body" => $content, "reloadPage" => $toClient ? "clientssummary.php?userid=" . $toClient->id : ""]);
