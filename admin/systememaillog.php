<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("View Email Message Log");
$aInt->title = $aInt->lang("system", "emailmessagelog");
$aInt->sidebar = "logs";
$aInt->icon = "logs";
$aInt->sortableTableInit("date");
$tabledata = [];
$result = WHMCS\Mail\Log::with("client");
$numrows = $result->count();
$result->limit($limit)->offset($page * $limit)->orderByDesc("id");
foreach ($result->get() as $data) {
    $id = $data->id;
    $date = WHMCS\Input\Sanitize::makeSafeForOutput($data->sentDate->toAdminDateTimeFormat());
    $subject = WHMCS\Input\Sanitize::makeSafeForOutput($data->subject);
    $userid = $data->clientId;
    $fullName = WHMCS\Input\Sanitize::makeSafeForOutput($data->client->fullName);
    $uri = "clientsemails.php?displaymessage=true&id=" . $id;
    $modalSize = " data-modal-size=\"modal-lg\"";
    $modalTitle = " data-modal-title=\"" . WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("emails.viewemailmessage")) . "\"";
    $tabledata[] = [$date, "<a href=\"" . $uri . "\" class=\"open-modal\"" . $modalTitle . $modalSize . ">" . $subject . "</a>", "<a href=\"clientssummary.php?userid=" . $userid . "\">" . $fullName . "</a>", "<a href=\"sendmessage.php?resend=true&emailid=" . $id . "\"><img src=\"images/icons/resendemail.png\" border=\"0\" alt=\"" . $aInt->lang("emails", "resendemail") . "\"></a>"];
}
$content = $aInt->sortableTable([$aInt->lang("fields", "date"), $aInt->lang("fields", "subject"), $aInt->lang("system", "recipient"), ""], $tabledata);
$aInt->content = $content;
$aInt->display();
