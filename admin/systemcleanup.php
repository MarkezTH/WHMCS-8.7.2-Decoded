<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("System Cleanup Operations");
$aInt->title = $aInt->lang("system", "cleanupoperations");
$aInt->sidebar = "utilities";
$aInt->icon = "cleanup";
$aInt->helplink = "System Utilities#System Cleanup";
$action = App::getFromRequest("action");
$date = App::getFromRequest("date");
ob_start();
if ($action == "pruneclientactivity" && $date) {
    check_token("WHMCS.admin.default");
    $sqldate = toMySQLDate($date);
    $query = "DELETE FROM tblactivitylog WHERE userid>0 AND date<'" . db_escape_string($sqldate) . "'";
    $result = full_query($query);
    infoBox($aInt->lang("system", "cleanupsuccess"), $aInt->lang("system", "deleteactivityinfo") . " " . $date . " (" . mysql_affected_rows() . ")");
    logActivity("Cleanup Operation: Pruned Client Activity Logs from before " . $date);
}
if ($action == "deletemessages" && $date) {
    check_token("WHMCS.admin.default");
    $sqldate = toMySQLDate($date);
    $query = "DELETE FROM tblemails WHERE pending='0' and date<'" . db_escape_string($sqldate) . "'";
    $result = full_query($query);
    infoBox($aInt->lang("system", "cleanupsuccess"), $aInt->lang("system", "deletemessagesinfo") . " " . $date . " (" . mysql_affected_rows() . ")");
    logActivity("Cleanup Operation: Pruned Messages Sent before " . $date);
}
if ($action == "cleargatewaylog") {
    check_token("WHMCS.admin.default");
    $query = "TRUNCATE tblgatewaylog";
    $result = full_query($query);
    infoBox($aInt->lang("system", "cleanupsuccess"), $aInt->lang("system", "deletegatewaylog"));
    logActivity("Cleanup Operation: Gateway Log Emptied");
}
if ($action == "clearmailimportlog") {
    check_token("WHMCS.admin.default");
    $attachments = WHMCS\Database\Capsule::table("tblticketmaillog")->where("attachment", "!=", "")->pluck("attachment")->all();
    try {
        $attachmentStorage = Storage::ticketAttachments();
        foreach ($attachments as $attachmentList) {
            $attachmentSet = explode("|", $attachmentList);
            foreach ($attachmentSet as $attachment) {
                $attachment = trim($attachment);
                if ($attachment) {
                    try {
                        $attachmentStorage->deleteAllowNotPresent($attachment);
                    } catch (Exception $e) {
                    }
                }
            }
        }
    } catch (Exception $e) {
    }
    $query = "TRUNCATE tblticketmaillog";
    $result = full_query($query);
    if ($result) {
        WHMCS\Support\Ticket\TicketImportNotification::truncate();
    }
    infoBox($aInt->lang("system", "cleanupsuccess"), $aInt->lang("system", "deleteticketlog"));
    logActivity("Cleanup Operation: Ticket Mail Import Log Emptied");
}
if ($action == "clearwhoislog") {
    check_token("WHMCS.admin.default");
    $query = "TRUNCATE tblwhoislog";
    $result = full_query($query);
    infoBox($aInt->lang("system", "cleanupsuccess"), $aInt->lang("system", "deletewhoislog"));
    logActivity("Cleanup Operation: WHOIS Lookup Log Emptied");
}
if ($action == "emptytemplatecache") {
    check_token("WHMCS.admin.default");
    $smarty = new WHMCS\Smarty();
    $smarty->clearAllCaches();
    infoBox($aInt->lang("system", "cleanupsuccess"), $aInt->lang("system", "deletecacheinfo"));
    logActivity("Cleanup Operation: Template Cache Emptied");
}
if ($action == "deleteattachments" && $date) {
    check_token("WHMCS.admin.default");
    $count = $total = 0;
    $limitHit = false;
    $error = "";
    if (!$date) {
        $error = "Please enter a date to remove attachments.";
    }
    if ($date) {
        if (!function_exists("removeAttachmentsFromClosedTickets")) {
            require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ticketfunctions.php";
        }
        $date = WHMCS\Carbon::parseDateRangeValue($date);
        $date = $date["from"];
        $data = removeAttachmentsFromClosedTickets($date);
        $count = $data["removed"];
        if ($count === 0 && !empty($data["error"])) {
            $error = $data["error"];
        }
        $limitHit = $data["limitHit"];
        $description = "Cleanup Operation: Automated Prune Ticket Attachments: ";
        $description .= "Removed " . $count . " Attachments from Tickets Closed before ";
        $description .= $date->toAdminDateFormat();
        logAdminActivity($description);
        $title = "system.cleanupsuccess";
        $langString = "system.deleteattachinfo";
        $status = "info";
        if ($limitHit) {
            $langString = "system.deletedAttachmentsLimitHit";
        }
    }
    if ($error) {
        $title = "global.erroroccurred";
        $langString = $error;
        $status = "error";
    }
    infoBox(AdminLang::trans($title), AdminLang::trans($langString, [":date" => $date->toAdminDateFormat()]), $status);
}
$attachmentsStorage = Storage::ticketAttachments();
$ticketAttachments = WHMCS\File\FileAssetCollection::forAssetType(WHMCS\File\FileAsset::TYPE_TICKET_ATTACHMENTS);
$attachmentssize = 0;
if ($attachmentsStorage->isLocalAdapter()) {
    foreach ($ticketAttachments as $file) {
        try {
            $attachmentssize += $attachmentsStorage->getSize($file);
        } catch (Exception $e) {
        }
    }
    if (0 < $attachmentssize) {
        $attachmentssize /= 1048576;
        $attachmentssize = round($attachmentssize, 2);
    }
}
echo $infobox;
echo "\n<p>";
echo $aInt->lang("system", "cleanupdescription");
echo "</p>\n\n<div class=\"admin-tabs-v2\">\n    <ul class=\"nav nav-tabs admin-tabs\" role=\"tablist\">\n        <li class=\"active\" role=\"presentation\">\n            <a id=\"tabSimple\" data-toggle=\"tab\" href=\"#contentSimple\" role=\"tab\">\n                ";
echo AdminLang::trans("global.simple");
echo "            </a>\n        </li>\n        <li role=\"presentation\">\n            <a id=\"tabAdvanced\" data-toggle=\"tab\" href=\"#contentAdvanced\" role=\"tab\">\n                ";
echo AdminLang::trans("global.advanced");
echo "            </a>\n        </li>\n    </ul>\n    <div class=\"tab-content\">\n        <div class=\"tab-pane active\" id=\"contentSimple\">\n\n            <div class=\"row text-center\">\n                <div class=\"col-lg-4 col-md-6\">\n                    <div class=\"well\">\n                        <form method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "\">\n                            <input type=\"hidden\" name=\"action\" value=\"cleargatewaylog\" />\n                            <h2>";
echo $aInt->lang("system", "emptygwlog");
echo "</h2>\n                            <input id=\"system-empty-gateway-log\" type=\"submit\" value=\"";
echo $aInt->lang("global", "go");
echo " &raquo;\" class=\"btn btn-default btn-block\">\n                        </form>\n                    </div>\n                </div>\n                <div class=\"col-lg-4 col-md-6\">\n                    <div class=\"well\">\n                        <form method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "\">\n                            <input type=\"hidden\" name=\"action\" value=\"clearmailimportlog\" />\n                            <h2>";
echo $aInt->lang("system", "emptytmlog");
echo "</h2>\n                            <input id=\"system-empty-ticket-mail-input-log\"  type=\"submit\" value=\"";
echo $aInt->lang("global", "go");
echo " &raquo;\" class=\"btn btn-default btn-block\">\n                        </form>\n                    </div>\n                </div>\n                <div class=\"col-lg-4 col-md-6\">\n                    <div class=\"well\">\n                        <form method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "\">\n                            <input type=\"hidden\" name=\"action\" value=\"clearwhoislog\" />\n                            <h2>";
echo $aInt->lang("system", "emptywllog");
echo "</h2>\n                            <input id=\"system-empty-whois-lookup-log\"  type=\"submit\" value=\"";
echo $aInt->lang("global", "go");
echo " &raquo;\" class=\"btn btn-default btn-block\" />\n                        </form>\n                    </div>\n                </div>\n                <div class=\"col-lg-4 col-md-6\">\n                    <div class=\"well\">\n                        <form method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "\">\n                            <input type=\"hidden\" name=\"action\" value=\"emptytemplatecache\" />\n                            <h2>";
echo $aInt->lang("system", "emptytc");
echo "</h2>\n                            <input id=\"system-empty-template-cache\"  type=\"submit\" value=\"";
echo $aInt->lang("global", "go");
echo " &raquo;\" class=\"btn btn-default btn-block\" />\n                        </form>\n                    </div>\n                </div>\n            </div>\n\n        </div>\n        <div class=\"tab-pane\" id=\"contentAdvanced\">\n\n            <div class=\"row text-center\">\n                <div class=\"col-sm-6\">\n                    <div class=\"well\">\n                        <form method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "?action=pruneclientactivity\">\n                            <h2>";
echo $aInt->lang("system", "prunecal");
echo "</h2>\n                            ";
$result = select_query("tblactivitylog", "COUNT(*)", "userid>0");
$data = mysql_fetch_array($result);
$num_rows = $data[0];
echo $aInt->lang("system", "totallogentries") . ": <b>" . $num_rows . "</b>";
echo "<br>\n                            <div class=\"form-group\">\n                                ";
echo AdminLang::trans("system.deleteentriesbefore");
echo ":\n                                <div class=\"date-picker-prepend-icon\">\n                                    <label for=\"system-empty-activity-log-date\" class=\"field-icon\">\n                                        <i class=\"fal fa-calendar-alt\"></i>\n                                    </label>\n                                    <input id=\"system-empty-activity-log-date\"\n                                           type=\"text\"\n                                           name=\"date\"\n                                           value=\"\"\n                                           class=\"form-control input-inline date-picker-single\"\n                                    />\n                                </div>\n                            </div>\n                            <button id=\"system-empty-activity-log-delete\"  type=\"submit\" class=\"button btn btn-default\">\n                                ";
echo $aInt->lang("global", "delete");
echo "                            </button>\n                        </form>\n                    </div>\n                </div>\n                <div class=\"col-sm-6\">\n                    <div class=\"well\">\n                        <form method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "?action=deletemessages\">\n                            <h2>";
echo $aInt->lang("system", "prunese");
echo "</h2>\n                            ";
$num_rows = WHMCS\Database\Capsule::table("tblemails")->where("pending", 0)->count();
echo $aInt->lang("system", "totalsavedemails") . ": <b>" . $num_rows . "</b>";
echo "<br>\n                            <div class=\"form-group\">\n                                ";
echo AdminLang::trans("system.deletemailsbefore");
echo ":\n                                <div class=\"date-picker-prepend-icon\">\n                                    <label for=\"system-empty-saved-emails-date\" class=\"field-icon\">\n                                        <i class=\"fal fa-calendar-alt\"></i>\n                                    </label>\n                                    <input id=\"system-empty-saved-emails-date\"\n                                           type=\"text\"\n                                           name=\"date\"\n                                           value=\"\"\n                                           class=\"form-control input-inline date-picker-single\"\n                                    />\n                                </div>\n                            </div>\n                            <button id=\"system-empty-saved-emails-delete\"  type=\"submit\" class=\"button btn btn-default\">\n                                ";
echo $aInt->lang("global", "delete");
echo "                            </button>\n                        </form>\n                    </div>\n                </div>\n                <div class=\"col-sm-6\">\n                    <div class=\"well\">\n                        <form method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "?action=deleteattachments\">\n                            <h2>";
echo $aInt->lang("system", "pruneoa");
echo "</h2>\n                            ";
echo $aInt->lang("system", "nosavedattachments") . ": <b>" . $ticketAttachments->count() . "</b>";
if ($ticketAttachments) {
    echo "<br>" . $aInt->lang("system", "filesizesavedatt") . ": <b>" . $attachmentssize . " " . $aInt->lang("fields", "mb") . "</b>";
}
echo "<br>\n                            <div class=\"form-group\">\n                                ";
echo AdminLang::trans("system.deleteattachbefore");
echo ":\n                                <div class=\"date-picker-prepend-icon\">\n                                    <label for=\"system-prune-attachments-before\" class=\"field-icon\">\n                                        <i class=\"fal fa-calendar-alt\"></i>\n                                    </label>\n                                    <input id=\"system-prune-attachments-before\"\n                                           type=\"text\"\n                                           name=\"date\"\n                                           value=\"\"\n                                           data-drops=\"up\"\n                                           class=\"form-control input-inline date-picker-single\"\n                                    />\n                                </div>\n                            </div>\n                            <button id=\"system-empty-atachments-delete\" type=\"submit\" class=\"button btn btn-default\">\n                                ";
echo AdminLang::trans("global.delete");
echo "                            </button>\n                        </form>\n                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n</div>\n\n";
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->display();
