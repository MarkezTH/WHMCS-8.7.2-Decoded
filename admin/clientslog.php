<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("View Activity Log");
$aInt->setClientsProfilePresets();
$aInt->setHelpLink("Clients:Emails/Notes/Logs Tabs");
$userid = (int) App::getFromRequest("userid");
$aInt->valUserID($userid);
$aInt->assertClientBoundary($userid);
ob_start();
echo "\n<form method=\"post\" action=\"clientslog.php?userid=";
echo $userid;
echo "\">\n\n<div class=\"context-btn-container\">\n    <input type=\"submit\" value=\"";
echo $aInt->lang("system", "filterlog");
echo "\" class=\"btn btn-default\" />\n</div>\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td width=\"15%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "date");
echo "</td>\n        <td class=\"fieldarea\">\n            <div class=\"form-group date-picker-prepend-icon\">\n                <label for=\"inputDate\" class=\"field-icon\">\n                    <i class=\"fal fa-calendar-alt\"></i>\n                </label>\n                <input id=\"inputDate\"\n                       type=\"text\"\n                       name=\"date\"\n                       value=\"";
echo App::getFromRequest("date");
echo "\"\n                       class=\"form-control date-picker-single\"\n                />\n            </div>\n        </td>\n        <td width=\"15%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "description");
echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"description\" value=\"";
echo $whmcs->get_req_var("description");
echo "\" class=\"form-control input-300\"></td>\n    </tr>\n    <tr>\n        <td width=\"15%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "username");
echo "</td>\n        <td class=\"fieldarea\"><select name=\"username\" class=\"form-control select-inline\">\n            <option value=\"\">Any</option>\n";
$adminUsernames = WHMCS\User\Admin::active()->orderBy("username", "ASC")->pluck("username")->toArray();
$selectedUsername = App::getFromRequest("username");
foreach ($adminUsernames as $adminUsername) {
    echo "<option" . ($adminUsername === $selectedUsername ? " selected" : "") . ">" . $adminUsername . "</option>";
}
echo "            </select></td>\n        <td width=\"15%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "ipaddress");
echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"ipaddress\" value=\"";
echo $whmcs->get_req_var("ipaddress");
echo "\" class=\"form-control input-150\"></td>\n    </tr>\n</table>\n\n</form>\n\n<br />\n\n";
$aInt->sortableTableInit("date");
$log = new WHMCS\Log\Activity();
$log->setCriteria(["userid" => $userid, "date" => $whmcs->get_req_var("date"), "username" => $whmcs->get_req_var("username"), "description" => $whmcs->get_req_var("description"), "ipaddress" => $whmcs->get_req_var("ipaddress")]);
$numrows = $log->getTotalCount();
$logs = collect($log->getLogEntries($whmcs->get_req_var("page")));
$usersMap = WHMCS\User\User::whereIn("id", $logs->pluck("userId"))->pluck("email", "id");
$adminsMap = WHMCS\User\Admin::whereIn("id", $logs->pluck("adminId"))->pluck("email", "id");
$tabledata = [];
foreach ($logs as $entry) {
    if (0 < $entry["adminId"]) {
        $userId = $entry["adminId"];
        $userType = AdminLang::trans("fields.adminId");
        $userLabel = getfrommap($adminsMap, $entry["adminId"], "Missing Admin");
    } else {
        if (0 < $entry["userId"]) {
            $userId = $entry["userId"];
            $userType = AdminLang::trans("fields.userId");
            $userLabel = getfrommap($usersMap, $entry["userId"], "Missing User");
        } else {
            $userId = "";
            $userType = AdminLang::trans("global.userSystem");
            $userLabel = "-";
        }
    }
    $tabledata[] = [$entry["date"], "<div align=\"left\">" . $entry["description"] . "</div>", "<small>" . $userType . " " . $userId . "<br>" . "<div class=\"truncate\" style=\"max-width:200px;color:#bbb;\">" . $userLabel . "</div></small>", "<small>" . $entry["ipaddress"] . "</small>"];
}
echo $aInt->sortableTable([["", $aInt->lang("fields", "date"), 150], $aInt->lang("fields", "logEntry"), ["", $aInt->lang("fields", "user"), 220], ["", $aInt->lang("fields", "ipaddress"), 120]], $tabledata);
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->display();
function getFromMap($map, $id, $fallbackLabel)
{
    if ($id === 0) {
        return "-";
    }
    if ($map->has($id)) {
        return $map[$id];
    }
    return $fallback . " " . $id;
}
