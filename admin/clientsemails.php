<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("View Email Message Log", false);
$aInt->setClientsProfilePresets();
$whmcs = WHMCS\Application::getInstance();
$userid = $whmcs->get_req_var("userid");
$messageID = $whmcs->get_req_var("messageID");
$emailTemplate = WHMCS\Mail\Template::find($messageID);
$displaymessage = App::getFromRequest("displaymessage");
$id = (int) $whmcs->get_req_var("id");
$action = App::getFromRequest("action");
$aInt->assertClientBoundary($userid);
$aInt->setHelpLink("Clients:Emails/Notes/Logs Tabs");
if ($displaymessage == "true") {
    try {
        $data = WHMCS\Mail\Log::findOrFail($id);
        $date = $data->sentDate;
        $to = $data->to;
        if (!$to) {
            $to = [AdminLang::trans("emails.registeredemail")];
        }
        $to = WHMCS\Input\Sanitize::makeSafeForOutput(implode(", ", $to));
        $cc = WHMCS\Input\Sanitize::makeSafeForOutput(implode(", ", $data->cc));
        $bcc = WHMCS\Input\Sanitize::makeSafeForOutput(implode(", ", $data->bcc));
        $subject = $data->subject;
        $message = $data->message;
        $attachments = $data->attachments;
        $content = view("admin.client.profile.view-email", ["to" => $to, "cc" => $cc, "bcc" => $bcc, "subject" => WHMCS\Input\Sanitize::makeSafeForOutput($subject), "message" => WHMCS\Input\Sanitize::encode($message), "attachments" => $attachments]);
    } catch (Exception $e) {
        $content = "Invalid Email Requested";
    }
    $aInt->jsonResponse(["body" => $content]);
}
if ($action == "send" && $messageID == 0) {
    redir("type=" . $type . "&id=" . $id, "sendmessage.php");
}
if ($action == "delete") {
    check_token("WHMCS.admin.default");
    delete_query("tblemails", ["id" => $id, "userid" => $userid]);
    redir("userid=" . $userid);
}
$aInt->valUserID($userid);
ob_start();
$jscode = "";
if ($action == "send") {
    check_token("WHMCS.admin.default");
    $additional = [];
    if (App::isInRequest("aid") && is_numeric(App::getFromRequest("aid"))) {
        $additional = ["addon_id" => App::getFromRequest("aid")];
    }
    $result = sendMessage($emailTemplate, $id, $additional, true);
    $queryStr = "userid=" . $userid;
    if ($result === true) {
        $queryStr .= "&success=1";
    } else {
        if ($result === false) {
            $queryStr .= "&error=1";
        } else {
            if (0 < strlen($result)) {
                $queryStr .= "&error=1";
                WHMCS\Session::set("EmailError", $result);
            }
        }
    }
    $whmcsConfig = $whmcs->getApplicationConfig();
    $smtp_debug = $whmcsConfig["smtp_debug"];
    if ($smtp_debug) {
        $debug = WHMCS\Session::set("SMTPDebug", base64_encode(ob_get_contents()));
    }
    redir($queryStr);
}
$aInt->deleteJSConfirm("doDelete", "emails", "suredelete", "clientsemails.php?userid=" . $userid . "&action=delete&id=");
$debug = base64_decode(WHMCS\Session::getAndDelete("SMTPDebug"));
if ($debug) {
    echo $debug;
}
$success = $whmcs->get_req_var("success");
$error = $whmcs->get_req_var("error");
if ($success) {
    infoBox($aInt->lang("global", "success"), $aInt->lang("email", "sentSuccessfully"), "success");
} else {
    if ($error) {
        $result = WHMCS\Session::get("EmailError");
        WHMCS\Session::delete("EmailError");
        if ($result) {
            infoBox($aInt->lang("global", "erroroccurred"), $result, "error");
        } else {
            infoBox($aInt->lang("global", "erroroccurred"), $aInt->lang("email", "emailAborted"), "warning");
        }
    }
}
if ($infobox) {
    echo $infobox;
}
$aInt->sortableTableInit("date", "DESC");
$result = WHMCS\Mail\Log::ofClient($userid);
$numrows = $result->count();
$result->orderBy($orderby, $order)->limit($limit)->offset($page * $limit);
foreach ($result->get() as $data) {
    $id = (int) $data->id;
    $date = fromMySQLDate($data->sentDate, true);
    $subject = $data->subject;
    if (!$subject) {
        $subject = AdminLang::trans("emails.nosubject");
    }
    $additional = "";
    if (!empty($data->attachments)) {
        $additional .= " <i class=\"fal fa-paperclip\"></i>";
    }
    $uri = "clientsemails.php?displaymessage=true&id=" . $id;
    $modalSize = " data-modal-size=\"modal-lg\"";
    $modalTitle = "data-modal-title=\"" . WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("emails.viewemailmessage")) . "\"";
    $tabledata[] = [WHMCS\Input\Sanitize::makeSafeForOutput($date), "<a href=\"" . $uri . "\" class=\"open-modal\"" . $modalTitle . $modalSize . ">" . WHMCS\Input\Sanitize::makeSafeForOutput($subject) . "</a>" . $additional, "<a href=\"sendmessage.php?resend=true&emailid=" . $id . "\"><img src=\"images/icons/resendemail.png\" border=\"0\" alt=\"" . $aInt->lang("emails", "resendemail") . "\"></a>", "<a href=\"#\" onClick=\"doDelete('" . $id . "')\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\" /></a>"];
}
echo $aInt->sortableTable([["date", $aInt->lang("fields", "date")], ["subject", $aInt->lang("emails", "subject")], "", ""], $tabledata);
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
