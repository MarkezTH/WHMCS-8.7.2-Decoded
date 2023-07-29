<?php

use WHMCS\Application\Support\Facades\Auth;
use WHMCS\Application\Support\Facades\Lang;

function processUtf8Mb4($message)
{
    global $whmcs;
    $cutUtf8Mb4 = $whmcs->get_config("CutUtf8Mb4");
    if (is_string($message) && $message !== "" && htmlspecialchars($message) === "") {
        $search = ["\0�", "�"];
        $replace = [" ", ""];
        $message = str_replace($search, $replace, $message);
    }
    if (!$cutUtf8Mb4) {
        return $message;
    }
    $emojis = ["/[\\x{1F600}\\x{1F601}]/u" => ":)", "/[\\x{1F603}-\\x{1F606}]/u" => ":D", "/[\\x{1F609}\\x{1F60A}]/u" => ";)", "/\\x{1F610}/u" => ":|", "/[\\x{1F612}\\x{1F61E}\\x{1F61F}]/u" => ":(", "/\\x{1F61B}/u" => ":P", "/\\x{1F622}/u" => ":'("];
    $cleanText = $message;
    $cleanText = preg_replace(array_keys($emojis), array_values($emojis), $cleanText);
    $removePatterns = ["/[\\x{1F600}-\\x{1F64F}]/u", "/[\\x{1F300}-\\x{1F5FF}]/u", "/[\\x{1F680}-\\x{1F6FF}]/u", "/[\\x{2600}-\\x{26FF}]/u", "/[\\x{2700}-\\x{27BF}]/u"];
    $cleanText = preg_replace($removePatterns, "", $cleanText);
    return $cleanText;
}

function getTimeBetweenDates($lastreply, $from = "now")
{
    $datetime = strtotime($from);
    $date2 = strtotime($lastreply);
    $holdtotsec = $datetime - $date2;
    $holdtotmin = ($datetime - $date2) / 60;
    $holdtothr = ($datetime - $date2) / 3600;
    $holdtotday = intval(($datetime - $date2) / 86400);
    $holdhr = intval($holdtothr - $holdtotday * 24);
    $holdmr = intval($holdtotmin - ($holdhr * 60 + $holdtotday * 1440));
    $holdsr = intval($holdtotsec - ($holdhr * 3600 + $holdmr * 60 + 86400 * $holdtotday));
    return ["days" => $holdtotday, "hours" => $holdhr, "minutes" => $holdmr, "seconds" => $holdsr];
}

function getShortLastReplyTime($lastreply)
{
    $timeparts = gettimebetweendates($lastreply);
    $str = "";
    if (0 < $timeparts["days"]) {
        $str .= $timeparts["days"] . "d ";
    }
    $str .= $timeparts["hours"] . "h ";
    $str .= $timeparts["minutes"] . "m";
    return $str;
}

function getLastReplyTime($lastreply)
{
    $timeparts = gettimebetweendates($lastreply);
    $str = "";
    if (0 < $timeparts["days"]) {
        $str .= $timeparts["days"] . " Days ";
    }
    $str .= $timeparts["hours"] . " Hours ";
    $str .= $timeparts["minutes"] . " Minutes ";
    $str .= $timeparts["seconds"] . " Seconds ";
    $str .= "Ago";
    return $str;
}

function getTicketDuration($start, $end)
{
    $timeparts = gettimebetweendates($start, $end);
    $str = "";
    if (0 < $timeparts["days"]) {
        $str .= $timeparts["days"] . " " . Lang::trans("days") . " ";
    }
    if (0 < $timeparts["hours"]) {
        $str .= $timeparts["hours"] . " " . Lang::trans("hours") . " ";
    }
    if (0 < $timeparts["minutes"]) {
        $str .= $timeparts["minutes"] . " " . Lang::trans("minutes") . " ";
    }
    $str .= $timeparts["seconds"] . " " . Lang::trans("seconds") . " ";
    return $str;
}

function getStatusColour($tstatus, $htmlOutput = true)
{
    global $_LANG;
    if (!array_key_exists($tstatus, $ticketcolors)) {
        $ticketcolors[$tstatus] = $color = get_query_val("tblticketstatuses", "color", ["title" => $tstatus]);
    } else {
        $color = $ticketcolors[$tstatus];
    }
    if ($htmlOutput) {
        $langstatus = preg_replace("/[^a-z]/i", "", strtolower($tstatus));
        if ($_LANG["supportticketsstatus" . $langstatus]) {
            $tstatus = $_LANG["supportticketsstatus" . $langstatus];
        }
        $statuslabel = "";
        if ($color) {
            $statuslabel .= "<span style=\"color:" . $color . "\">";
        }
        $statuslabel .= $tstatus;
        if ($color) {
            $statuslabel .= "</span>";
        }
        return $statuslabel;
    }
    return $color;
}

function ticketAutoHyperlinks($message)
{
    return autoHyperLink($message);
}

function AddNote($tid, $message, $markdown = false, WHMCS\Carbon $createdDate = NULL)
{
    if (!function_exists("getAdminName")) {
        require ROOTDIR . "/includes/adminfunctions.php";
    }
    $attachments = uploadTicketAttachments(true);
    if (!$attachments && App::isApiRequest() && ($attachment = App::getFromRequest("attachments"))) {
        if (!is_array($attachment)) {
            $attachment = json_decode(base64_decode($attachment), true);
        }
        if (is_array($attachment)) {
            $attachments = saveTicketAttachmentsFromApiCall($attachment, true);
        }
    }
    if (!$createdDate) {
        $createdDate = WHMCS\Carbon::now();
    }
    $message = processutf8mb4($message);
    insert_query("tblticketnotes", ["ticketid" => $tid, "date" => $createdDate->toDateTimeString(), "admin" => getAdminName(), "message" => $message, "attachments" => $attachments ?: "", "editor" => $markdown ? "markdown" : "plain"]);
    addTicketLog($tid, "Ticket Note Added");
    run_hook("TicketAddNote", ["ticketid" => $tid, "message" => $message, "adminid" => $_SESSION["adminid"], "attachments" => $attachments]);
}

function AdminRead($tid)
{
    $result = select_query("tbltickets", "adminunread", ["id" => $tid]);
    $data = mysql_fetch_assoc($result);
    $adminread = $data["adminunread"];
    $adminreadarray = $adminread ? explode(",", $adminread) : [];
    if (!in_array($_SESSION["adminid"], $adminreadarray)) {
        $adminreadarray[] = $_SESSION["adminid"];
        update_query("tbltickets", ["adminunread" => implode(",", $adminreadarray)], ["id" => $tid]);
    }
}

function ClientRead($tid)
{
    update_query("tbltickets", ["clientunread" => ""], ["id" => $tid]);
}

function addTicketLog($tid, $action)
{
    if (isset($_SESSION["adminid"])) {
        if (!function_exists("getAdminName")) {
            require ROOTDIR . "/includes/adminfunctions.php";
        }
        $action .= " (by " . getAdminName() . ")";
    }
    insert_query("tblticketlog", ["date" => "now()", "tid" => $tid, "action" => $action]);
}

function AddtoLog($tid, $action)
{
    addticketlog($tid, $action);
}

function getDepartmentName($deptId)
{
    if (!isset($departmentNames)) {
        $departmentNames = WHMCS\Support\Department::all()->pluck("name", "id")->toArray();
    }
    $departmentName = "";
    if (array_key_exists($deptId, $departmentNames)) {
        $departmentName = $departmentNames[$deptId];
    }
    return $departmentName;
}

function ticketGenerateAttachmentsListFromString($attachmentsString)
{
    $attachmentsOutput = "";
    $attachmentsString = trim($attachmentsString);
    if ($attachmentsString) {
        $attachmentsOutput .= "<br /><br /><strong>Attachments</strong><br />";
        $attachments = explode("|", $attachmentsString);
        foreach ($attachments as $i => $attachment) {
            $attachmentsOutput .= $i + 1 . ". " . substr($attachment, 7) . "<br />";
        }
    }
    return $attachmentsOutput;
}

function openNewTicket($clientId, $contactid, $deptid, $tickettitle, $message, $urgency, $attachmentsString = "", $from = [], $relatedservice = "", $ccemail = "", $noemail = "", $admin = "", $markdown = false, WHMCS\Carbon $createdDate = NULL, WHMCS\User\User $user = NULL, $ipAddress = NULL)
{
    global $CONFIG;
    $result = select_query("tblticketdepartments", "", ["id" => $deptid]);
    $data = mysql_fetch_array($result);
    $deptid = $data["id"];
    $noautoresponder = $data["noautoresponder"];
    if (!$deptid) {
        exit("Department Not Found. Exiting.");
    }
    $ccemail = trim($ccemail);
    $tickettitle = processutf8mb4($tickettitle);
    $message = processutf8mb4($message);
    if ($clientId) {
        $name = $email = "";
        if (0 < $contactid) {
            $data = get_query_vals("tblcontacts", "firstname,lastname,email", ["id" => $contactid, "userid" => $clientId]);
            $ccemail .= $ccemail ? "," . $data["email"] : $data["email"];
        } else {
            $data = get_query_vals("tblclients", "firstname,lastname,email", ["id" => $clientId]);
        }
        if ($admin) {
            $message = str_replace("[NAME]", $data["firstname"] . " " . $data["lastname"], $message);
            $message = str_replace("[FIRSTNAME]", $data["firstname"], $message);
            $message = str_replace("[EMAIL]", $data["email"], $message);
        }
        $clientname = $data["firstname"] . " " . $data["lastname"];
    } else {
        if ($admin) {
            $message = str_replace("[NAME]", $from["name"], $message);
            $message = str_replace("[FIRSTNAME]", current(explode(" ", $from["name"])), $message);
            $message = str_replace("[EMAIL]", $from["email"], $message);
        }
        $clientname = $from["name"];
    }
    if (!$createdDate) {
        $createdDate = WHMCS\Carbon::now();
    }
    $ccEmailArray = array_unique(explode(",", $ccemail));
    foreach ($ccEmailArray as $key => $value) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            unset($ccEmailArray[$key]);
        }
    }
    if (!defined("ADMINAREA") && Auth::user() && !App::isApiRequest() && !App::isExecutingViaCron() && Auth::client() && !Auth::client()->authedUserIsOwner()) {
        $authedUserEmail = Auth::user()->email;
        if (!in_array($authedUserEmail, $ccEmailArray)) {
            $ccEmailArray[] = $authedUserEmail;
        }
    }
    $ccemail = implode(",", $ccEmailArray);
    $length = 8;
    $seeds = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $c = NULL;
    $seeds_count = strlen($seeds) - 1;
    for ($i = 0; $i < $length; $i++) {
        $c .= $seeds[rand(0, $seeds_count)];
    }
    if (!in_array($urgency, ["High", "Medium", "Low"])) {
        $urgency = "Medium";
    }
    $editor = $markdown ? "markdown" : "plain";
    $requestorId = $user ? $user->id : 0;
    if (!$requestorId) {
        if (Auth::user() && !defined("ADMINAREA") && !App::isApiRequest() && !App::isExecutingViaCron()) {
            $requestorId = Auth::user()->id;
        }
        if (App::isApiRequest() && $clientId && !$contactid) {
            $clientModel = WHMCS\User\Client::find($clientId);
            $requestorId = $clientModel ? $clientModel->owner()->id : 0;
        }
    }
    $adminName = "";
    if ($admin && WHMCS\User\Admin::getAuthenticatedUser()) {
        $adminName = WHMCS\User\Admin::getAuthenticatedUser()->fullName;
    }
    $ticket = new WHMCS\Support\Ticket();
    $mask = new WHMCS\Support\TicketMask();
    $ticket->clientId = $clientId;
    $ticket->contactId = $contactid;
    $ticket->requestorId = $requestorId;
    $ticket->departmentId = $deptid;
    $ticket->date = $createdDate->toDateTimeString();
    $ticket->title = $tickettitle;
    $ticket->message = $message;
    $ticket->urgency = $urgency;
    $ticket->status = "Open";
    $ticket->attachment = $attachmentsString;
    $ticket->lastReply = $createdDate->toDateTimeString();
    if (0 < $requestorId && 0 < $clientId) {
        $ticket->name = "";
        $ticket->email = "";
    } else {
        $ticket->name = $from["name"] ?? "";
        $ticket->email = $from["email"] ?? "";
    }
    $ticket->c = $c;
    $ticket->ipaddress = $ipAddress;
    $ticket->clientUnread = 1;
    $ticket->adminRead = "";
    $ticket->service = $relatedservice;
    $ticket->cc = $ccemail;
    $ticket->editor = $editor;
    $ticket->admin = $adminName;
    $ticket->save();
    try {
        $tid = $mask->id($ticket->id)->unique();
    } catch (WHMCS\Exception\Support\TicketMaskIterationException $e) {
        $ticket->delete();
        throw $e;
    }
    $ticket->ticketNumber = $tid;
    $ticket->save();
    if (!$noemail) {
        if ($admin) {
            sendMessage("Support Ticket Opened by Admin", $ticket->id);
        } else {
            if (!$noautoresponder) {
                sendMessage("Support Ticket Opened", $ticket->id);
            }
        }
    }
    $deptname = getdepartmentname($deptid);
    if (!$noemail) {
        $changes = [];
        $changes["Opened"] = ["new" => $message];
        $changes["Who"] = $admin ? $adminName : $clientname;
        if ($attachmentsString) {
            $changes["Attachments"] = ticketgenerateattachmentslistfromstring($attachmentsString);
        }
        WHMCS\Tickets::notifyTicketChanges($ticket->id, $changes, getDepartmentNotificationIds($deptid));
    }
    addticketlog($ticket->id, "New Support Ticket Opened");
    if ($admin) {
        run_hook("TicketOpenAdmin", ["ticketid" => $ticket->id, "ticketmask" => $tid, "userid" => $clientId, "deptid" => $deptid, "deptname" => $deptname, "subject" => $tickettitle, "message" => $message, "priority" => $urgency]);
    } else {
        run_hook("TicketOpen", ["ticketid" => $ticket->id, "ticketmask" => $tid, "userid" => $clientId, "deptid" => $deptid, "deptname" => $deptname, "subject" => $tickettitle, "message" => $message, "priority" => $urgency]);
    }
    return ["ID" => $ticket->id, "TID" => $tid, "C" => $c, "Subject" => $tickettitle];
}

function AddReply($ticketid, $clientId, $contactid, $message, $admin, $attachmentsString = "", $from = "", $status = "", $noemail = "", $api = false, $markdown = false, $changes = [], WHMCS\Carbon $createdDate = NULL, WHMCS\User\User $user = NULL)
{
    global $CONFIG;
    if (!is_array($from)) {
        $from = ["name" => "", "email" => ""];
    }
    $adminname = "";
    $message = processutf8mb4($message);
    $ticket = WHMCS\Support\Ticket::with(["client", "contact"])->find($ticketid);
    if (!$ticket) {
        return NULL;
    }
    if ($admin) {
        $clientData = ["firstname" => $ticket->client->firstName ?? NULL, "lastname" => $ticket->client->lastName ?? NULL, "email" => $ticket->client->email ?? NULL];
        $guestData = ["firstname" => current(explode(" ", $ticket->name)), "lastname" => "", "email" => $ticket->email];
        $ticket->getRequestorType();
        switch ($ticket->getRequestorType()) {
            case WHMCS\Support\Ticket\RequestorTypes::GUEST:
                $data = $guestData;
                break;
            case WHMCS\Support\Ticket\RequestorTypes::ADMIN:
                if ($ticket->clientId) {
                    $data = $clientData;
                } else {
                    if ($ticket->contactId) {
                        $data = ["firstname" => $ticket->contact->firstName, "lastname" => $ticket->contact->lastName, "email" => $ticket->contact->email];
                    } else {
                        $data = $guestData;
                    }
                }
                break;
            case WHMCS\Support\Ticket\RequestorTypes::LEGACY_SUBACCOUNT:
                $data = ["firstname" => $ticket->contact->firstName, "lastname" => $ticket->contact->lastName, "email" => $ticket->contact->email];
                break;
            default:
                $data = ["firstname" => $ticket->requestor->firstName, "lastname" => $ticket->requestor->lastName, "email" => $ticket->requestor->email];
                $message = str_replace("[NAME]", $data["firstname"] . " " . $data["lastname"], $message);
                $message = str_replace("[FIRSTNAME]", $data["firstname"], $message);
                $message = str_replace("[EMAIL]", $data["email"], $message);
                if (!function_exists("getAdminName")) {
                    require ROOTDIR . "/includes/adminfunctions.php";
                }
                $adminname = $api ? $admin : getAdminName((int) $admin);
        }
    }
    if (!$createdDate) {
        $createdDate = WHMCS\Carbon::now();
    }
    $editor = $markdown ? "markdown" : "plain";
    $requestorId = $user ? $user->id : 0;
    if (!$requestorId && Auth::user() && !defined("ADMINAREA")) {
        $requestorId = Auth::user()->id;
    }
    $replyModel = new WHMCS\Support\Ticket\Reply();
    $replyModel->tid = $ticketid;
    $replyModel->userid = $clientId;
    $replyModel->contactId = $contactid;
    $replyModel->requestorId = $requestorId;
    $replyModel->name = $from["name"] ?? NULL;
    $replyModel->email = $from["email"] ?? NULL;
    $replyModel->date = $createdDate->toDateTimeString();
    $replyModel->message = $message;
    $replyModel->admin = $adminname;
    $replyModel->attachment = $attachmentsString;
    $replyModel->editor = $editor;
    $replyModel->save();
    $ticketreplyid = $replyModel->id;
    $data = $replyModel->ticket;
    $tid = $data->tid;
    $deptid = $data->did;
    $tickettitle = $data->title;
    $urgency = $data->urgency;
    $flagadmin = $data->flag;
    $oldStatus = $data->status;
    $replyName = $clientUserContactModel = NULL;
    if ($requestorId || $contactid) {
        if ($requestorId) {
            $clientUserContactModel = WHMCS\User\User::find($requestorId);
        } else {
            if ($contactid) {
                $clientUserContactModel = WHMCS\User\Client\Contact::find($contactid);
            }
        }
        $replyName = $clientUserContactModel ? $clientUserContactModel->fullName : "";
    }
    if (empty($replyName)) {
        $replyName = $from["name"];
    }
    $deptname = getdepartmentname($deptid);
    if ($admin) {
        if ($status == "") {
            $status = "Answered";
        }
        $updateqry = ["status" => $status, "clientunread" => "1", "lastreply" => $createdDate->toDateTimeString()];
        if (!empty($CONFIG["TicketLastReplyUpdateClientOnly"])) {
            unset($updateqry["lastreply"]);
        }
        update_query("tbltickets", $updateqry, ["id" => $ticketid]);
        addticketlog($ticketid, "New Ticket Response");
        if (!$noemail) {
            sendMessage("Support Ticket Reply", $ticketid, ["ticket_reply_id" => $ticketreplyid]);
        }
        run_hook("TicketAdminReply", ["ticketid" => $ticketid, "replyid" => $ticketreplyid, "deptid" => $deptid, "deptname" => $deptname, "subject" => $tickettitle, "message" => $message, "priority" => $urgency, "admin" => $adminname, "status" => $status]);
    } else {
        $status = "Customer-Reply";
        $updateqry = ["status" => "Customer-Reply", "clientunread" => "1", "adminunread" => "", "lastreply" => $createdDate->toDateTimeString()];
        $UpdateLastReplyTimestamp = WHMCS\Application::getInstance()->get_config("UpdateLastReplyTimestamp");
        if ($UpdateLastReplyTimestamp == "statusonly" && ($oldStatus == $status || $oldStatus == "Open" && $status == "Customer-Reply")) {
            unset($updateqry["lastreply"]);
        }
        update_query("tbltickets", $updateqry, ["id" => $ticketid]);
        addticketlog($ticketid, "New Ticket Response made by User");
        run_hook("TicketUserReply", ["ticketid" => $ticketid, "replyid" => $ticketreplyid, "userid" => $clientId, "deptid" => $deptid, "deptname" => $deptname, "subject" => $tickettitle, "message" => $message, "priority" => $urgency, "status" => $status]);
    }
    if ($oldStatus != $status) {
        $changes["Status"] = ["old" => $oldStatus, "new" => $status];
    }
    $changes["Reply"] = ["new" => $message];
    if ($attachmentsString) {
        $changes["Attachments"] = ticketgenerateattachmentslistfromstring($attachmentsString);
    }
    $recipients = [];
    if (!$admin) {
        $changes["Who"] = $replyName;
        if ($flagadmin) {
            $recipients = [$flagadmin];
        } else {
            if ($noemail) {
                $recipients = [];
            } else {
                $recipients = getDepartmentNotificationIds($deptid);
            }
        }
    } else {
        $changes["Who"] = $adminname;
    }
    WHMCS\Tickets::notifyTicketChanges($ticketid, $changes, $recipients);
    return $replyModel;
}

function processPoppedTicket($to, $name, $email, $subject, $message, $attachment, WHMCS\Mail\AutoSubmittedHeader $autoSubmitted, $cc = [])
{
    $decodestring = $subject . "##||-MESSAGESPLIT-||##" . $message;
    $decodestring = pipeDecodeString($decodestring);
    $decodestring = explode("##||-MESSAGESPLIT-||##", $decodestring);
    list($subject, $body) = $decodestring;
    processPipedTicket($to, $name, $email, $subject, $body, $attachment, $autoSubmitted, $cc);
}

function processPipedTicket($to, $name, $email, $subject, $message, $attachment, WHMCS\Mail\AutoSubmittedHeader $autoSubmitted, $cc = [])
{
    $continueImport = true;
    $raw_message = $message;
    $subject = processutf8mb4($subject);
    $message = processutf8mb4($message);
    $mailstatus = "";
    $mailLogAttachment = $attachment;
    $noEmail = $autoSubmitted->isAutomated();
    $autoGenerated = $autoSubmitted->isReplied();
    $result = select_query("tblticketspamfilters", "", "");
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $type = $data["type"];
        $content = WHMCS\Input\Sanitize::decode($data["content"]);
        if ($type == "sender") {
            if (strtolower($content) == strtolower($email)) {
                $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_SPAM_SENDER;
            }
        } else {
            if ($type == "subject") {
                if (strpos("x" . strtolower($subject), strtolower($content))) {
                    $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_SPAM_SUBJECT;
                }
            } else {
                if ($type == "phrase" && strpos("x" . strtolower($message), strtolower($content))) {
                    $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_SPAM_PHRASE;
                }
            }
        }
        if (!$mailstatus) {
        }
    }
    $responses = run_hook("TicketPiping", ["to" => $to, "cc" => $cc, "name" => $name, "email" => $email, "subject" => $subject, "body" => $message, "attachments" => $attachment]);
    foreach ($responses as $response) {
        if (array_key_exists("skipProcessing", $response) && $response["skipProcessing"]) {
            $ticketImport = WHMCS\Log\TicketImport::factory(WHMCS\Log\TicketImport::STATUS_FAILED_ABORTED_BY_HOOK);
            $ticketImport->to = $to;
            $ticketImport->cc = implode(",", $cc);
            $ticketImport->name = $name;
            $ticketImport->email = $email;
            $ticketImport->subject = $subject;
            $ticketImport->message = $message;
            $ticketImport->attachment = $mailLogAttachment;
            $ticketImport->save();
            return false;
        }
    }
    if (!$email) {
        $ticketImport = WHMCS\Log\TicketImport::factory(WHMCS\Log\TicketImport::STATUS_FAILED_MISSING_SENDER_EMAIL);
        $ticketImport->to = $to;
        $ticketImport->cc = implode(",", $cc);
        $ticketImport->name = $name;
        $ticketImport->email = $email;
        $ticketImport->subject = $subject;
        $ticketImport->message = $message;
        $ticketImport->attachment = $mailLogAttachment;
        $ticketImport->save();
        return false;
    }
    if (!$mailstatus) {
        $tid = WHMCS\Support\Ticket::extractIdentifier($subject);
        $ticket = NULL;
        if ($tid != "") {
            $ticket = WHMCS\Support\Ticket::where("tid", $tid)->first();
            if (!is_null($ticket) && 0 < $ticket->id) {
                if ($ticket->merged_ticket_id) {
                    $tid = $ticket->merged_ticket_id;
                    $ticketStatus = WHMCS\Database\Capsule::table("tbltickets")->where("tid", "=", $ticket->merged_ticket_id)->value("status");
                } else {
                    $tid = $ticket->id;
                    $ticketStatus = $ticket->status;
                }
            } else {
                $tid = 0;
            }
        }
        $to = trim($to);
        $toemails = explode(",", $to);
        $deptid = "";
        foreach ($toemails as $toemail) {
            $result = select_query("tblticketdepartments", "", ["email" => trim(strtolower($toemail))]);
            $data = mysql_fetch_array($result);
            if ($data) {
                $deptid = $data["id"];
            }
            if ($deptid) {
                if (!$deptid) {
                    $result = select_query("tblticketdepartments", "", ["hidden" => ""], "order", "ASC", "1");
                    $data = mysql_fetch_array($result);
                    $deptid = $data["id"];
                }
                if (!$deptid) {
                    $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_DEPT_NOT_FOUND;
                } else {
                    $to = $data["email"];
                    $deptclientsonly = $data["clientsonly"];
                    $deptpiperepliesonly = $data["piperepliesonly"];
                    $noautoresponder = $data["noautoresponder"];
                    if ($to == $email) {
                        $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_BLOCKED_EMAIL_LOOP;
                    } else {
                        $originalMessage = $message;
                        $result = select_query("tblticketbreaklines", "", "", "id", "ASC");
                        while ($data = mysql_fetch_array($result)) {
                            $breakpos = strpos($message, $data["breakline"]);
                            if ($breakpos) {
                                $message = substr($message, 0, $breakpos);
                            }
                        }
                        if (!$message) {
                            $message = $originalMessage;
                        }
                        $message = trim($message);
                        $replyAdmin = WHMCS\User\Admin::where("email", $email)->first();
                        if ($replyAdmin) {
                            $adminid = $replyAdmin->id;
                            if ($tid) {
                                addreply($tid, "", "", htmlspecialchars_array($message), $adminid, $attachment, "", "", $noEmail, false, false);
                                $mailLogAttachment = "";
                                $mailstatus = WHMCS\Log\TicketImport::STATUS_SUCCESSFUL_REPLY_IMPORT;
                            } else {
                                $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_TICKET_NOT_FOUND;
                            }
                        } else {
                            $ccemail = "";
                            $contactid = "";
                            $from = [];
                            $clientId = WHMCS\User\Client::whereIn("status", [WHMCS\User\Client::STATUS_ACTIVE, WHMCS\User\Client::STATUS_INACTIVE])->where("email", $email)->value("id");
                            $user = WHMCS\User\User::username($email)->first();
                            $authorizedClientUser = false;
                            if ($user) {
                                if (!is_null($ticket) && 0 < $tid) {
                                    $clientId = "";
                                    $clientOwnsTicket = $ticket->userId;
                                    if ($clientOwnsTicket) {
                                        $userClient = $user->clients()->find($clientOwnsTicket);
                                        if ($userClient && $userClient->pivot->getPermissions()->hasPermission("tickets")) {
                                            $clientId = $clientOwnsTicket;
                                        }
                                    }
                                    if (!$clientId) {
                                        $tidValidSenderHasNoPermission = true;
                                    }
                                } else {
                                    $clientIds = $user->getClientsByPermission("tickets");
                                    $clientIdsCount = count($clientIds);
                                    if ($clientIdsCount === 1) {
                                        $clientId = $clientIds[0]->id;
                                        $authorizedClientUser = true;
                                    } else {
                                        if (1 < $clientIdsCount) {
                                            $clientId = 0;
                                            $authorizedClientUser = true;
                                        } else {
                                            $clientId = 0;
                                        }
                                    }
                                    unset($clientIds);
                                    unset($clientIdsCount);
                                }
                                if (0 < $clientId) {
                                    $ccemail = $user->email;
                                }
                            }
                            if (!$clientId && !$authorizedClientUser) {
                                $contactsByEmail = WHMCS\User\Client\Contact::where("email", $email)->get();
                                if ($contactsByEmail->count() === 1) {
                                    $clientId = $contactsByEmail->first()->userid;
                                    $contactid = $contactsByEmail->first()->id;
                                    $ccemail = $email;
                                }
                            }
                            if ($deptclientsonly && !$clientId && !$authorizedClientUser) {
                                if ($ticket && $ticket instanceof WHMCS\Support\Ticket) {
                                    if (!WHMCS\Config\Setting::getValue(WHMCS\Log\TicketImport::SETTING_ALLOW_INSECURE_IMPORT)) {
                                        $continueImport = false;
                                        $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_NOT_AUTHORIZED;
                                    }
                                } else {
                                    $continueImport = false;
                                    $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_NOT_RECOGNISED;
                                    if (!$noautoresponder) {
                                        sendMessage("Clients Only Bounce Message", 0, [$name, $email]);
                                    }
                                }
                            }
                            if ($continueImport) {
                                $guestEmailMatches = false;
                                if (empty($clientId)) {
                                    $from["name"] = $name;
                                    $from["email"] = $email;
                                    $clientTicket = false;
                                    if (isset($ticket->email) && $email === $ticket->email || isset($ticket->cc) && isEmailIncluded($email, $ticket->cc)) {
                                        $guestEmailMatches = true;
                                    }
                                } else {
                                    $clientTicket = true;
                                }
                                $filterdate = WHMCS\Carbon::now()->subMinutes(15)->toDateTimeString();
                                $query = "SELECT count(id) FROM tbltickets WHERE date>'" . $filterdate . "' AND ( email='" . mysql_real_escape_string($email) . "'";
                                if ($clientId) {
                                    $query .= " OR userid=" . (int) $clientId;
                                }
                                $query .= " )";
                                $result = full_query($query);
                                $data = mysql_fetch_array($result);
                                $numtickets = $data[0];
                                $ticketEmailLimit = (int) WHMCS\Config\Setting::getValue("TicketEmailLimit");
                                if (!$ticketEmailLimit) {
                                    $ticketEmailLimit = 10;
                                }
                                if ($ticketEmailLimit < $numtickets) {
                                    $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_RATE_LIMITED;
                                } else {
                                    run_hook("TransliterateTicketText", ["subject" => $subject, "message" => $message]);
                                    if ($tid) {
                                        $closedTicketStatuses = [];
                                        $result2 = select_query("tblticketstatuses", "title", ["showactive" => 0, "showawaiting" => 0, "autoclose" => 0]);
                                        while ($data2 = mysql_fetch_array($result2)) {
                                            $closedTicketStatuses[] = $data2["title"];
                                        }
                                        if (isset($ticketStatus) && in_array($ticketStatus, $closedTicketStatuses) && WHMCS\Config\Setting::getValue("PreventEmailReopening")) {
                                            $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_REOPEN_VIA_EMAIL;
                                            if (!$noautoresponder) {
                                                sendMessage("Closed Ticket Bounce Message", $tid, [$name, $email, "clientTicket" => $clientTicket]);
                                            }
                                        } else {
                                            if ($clientTicket) {
                                                $ticket = new WHMCS\Tickets();
                                                $ticket->setID($tid);
                                                addreply($tid, $clientId, $contactid, htmlspecialchars_array($message), "", $attachment, htmlspecialchars_array($from), "", $noEmail, false, false, [], NULL, $user);
                                                $mailLogAttachment = "";
                                                $mailstatus = WHMCS\Log\TicketImport::STATUS_SUCCESSFUL_REPLY_IMPORT;
                                            } else {
                                                if ($guestEmailMatches || WHMCS\Config\Setting::getValue(WHMCS\Log\TicketImport::SETTING_ALLOW_INSECURE_IMPORT)) {
                                                    $ticket = new WHMCS\Tickets();
                                                    $ticket->setID($tid);
                                                    addreply($tid, "", "", htmlspecialchars_array($message), "", $attachment, htmlspecialchars_array($from), "", $noEmail);
                                                    $mailLogAttachment = "";
                                                    $mailstatus = WHMCS\Log\TicketImport::STATUS_SUCCESSFUL_REPLY_IMPORT;
                                                } else {
                                                    $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_NOT_AUTHORIZED;
                                                }
                                            }
                                        }
                                    } else {
                                        if ($autoGenerated) {
                                            $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_AUTO_RESPONDER;
                                        } else {
                                            if ($deptpiperepliesonly) {
                                                $mailstatus = "Only Replies Allowed by Email";
                                                if (!$noautoresponder) {
                                                    sendMessage("Replies Only Bounce Message", 0, [$name, $email]);
                                                }
                                            } else {
                                                $emailTicketCcAllowed = (bool) WHMCS\Config\Setting::getValue("TicketAddCarbonCopyRecipients");
                                                if (!$emailTicketCcAllowed) {
                                                    $cc = [];
                                                }
                                                if ($ccemail) {
                                                    $cc[] = $ccemail;
                                                }
                                                if (0 < count($cc)) {
                                                    if (!isset($supportDepartmentEmails)) {
                                                        $supportDepartmentEmails = WHMCS\Database\Capsule::table("tblticketdepartments")->pluck("email")->all();
                                                    }
                                                    if (is_array($supportDepartmentEmails)) {
                                                        $cc = array_filter($cc, function ($email) use($supportDepartmentEmails) {
                                                            return !in_array($email, $supportDepartmentEmails);
                                                        });
                                                    }
                                                }
                                                $ccemail = implode(",", $cc);
                                                if (empty($tidValidSenderHasNoPermission)) {
                                                    try {
                                                        opennewticket(htmlspecialchars_array($clientId), htmlspecialchars_array($contactid), htmlspecialchars_array($deptid), htmlspecialchars_array($subject), htmlspecialchars_array($message), "Medium", $attachment, htmlspecialchars_array($from), "", htmlspecialchars_array($ccemail), $noEmail, "", false, NULL, $user);
                                                        $mailLogAttachment = "";
                                                        $mailstatus = WHMCS\Log\TicketImport::STATUS_SUCCESSFUL_TICKET_IMPORT;
                                                    } catch (WHMCS\Exception\Support\TicketMaskIterationException $e) {
                                                        $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_ITERATION_LIMIT;
                                                    } catch (Exception $e) {
                                                        $mailstatus = "Ticket Import Failed - " . $e->getMessage();
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    } else {
        if ($attachment) {
            $attachment = explode("|", $attachment);
            $attachmentStorage = Storage::ticketAttachments();
            foreach ($attachment as $file) {
                $attachmentStorage->deleteAllowNotPresent($file);
            }
        }
    }
    if (!$mailstatus) {
        $mailstatus = WHMCS\Log\TicketImport::STATUS_FAILED_TICKET_IMPORT;
        $mailLogAttachment = $attachment;
    }
    $ticketImport = WHMCS\Log\TicketImport::factory($mailstatus);
    $ticketImport->to = $to;
    $ticketImport->cc = implode(",", $cc);
    $ticketImport->name = $name;
    $ticketImport->email = $email;
    $ticketImport->subject = $subject;
    $ticketImport->message = $message;
    $ticketImport->attachment = $mailLogAttachment;
    $ticketImport->save();
    if (isset($ticket) && $ticket instanceof WHMCS\Support\Ticket && !in_array($mailstatus, $ticketImport->getImportedStatuses(true))) {
        if (in_array($ticket->status, $closedTicketStatuses) && !WHMCS\Config\Setting::getValue("SupportReopenTicketOnFailedImport") || WHMCS\Config\Setting::getValue("PreventEmailReopening")) {
            return NULL;
        }
        $ticket->status = WHMCS\Support\Ticket\Status::STATUS_CUSTOMER_REPLY;
        $ticket->save();
        addticketlog($ticket->id, "The ticket status updated due to a new unimported reply.");
    }
}

function checkTicketAttachmentSize()
{
    $postMaxSizeIniSetting = ini_get("post_max_size");
    $postMaxSize = convertIniSize($postMaxSizeIniSetting);
    $contentLength = (int) $_SERVER["CONTENT_LENGTH"];
    if (!$contentLength) {
        return true;
    }
    if ($postMaxSize < $contentLength) {
        logActivity(sprintf("A ticket attachment submission of %d bytes total was rejected due to PHP post_max_size setting being too small (%s or %d bytes).", $contentLength, $postMaxSizeIniSetting, $postMaxSize));
        return false;
    }
    $uploadMaxFileSizeIniSetting = ini_get("upload_max_filesize");
    $uploadMaxFileSize = convertIniSize($uploadMaxFileSizeIniSetting);
    if (isset($_FILES)) {
        if (is_array($_FILES["attachments"]["error"])) {
            $fileTooLarge = in_array(UPLOAD_ERR_INI_SIZE, $_FILES["attachments"]["error"]);
        } else {
            $fileTooLarge = $_FILES["attachments"]["error"] == UPLOAD_ERR_INI_SIZE;
        }
        if ($fileTooLarge) {
            logActivity(sprintf("A ticket attachment was rejected due to PHP upload_max_filesize setting being too small (%s or %d bytes).", $uploadMaxFileSizeIniSetting, $uploadMaxFileSize));
            return false;
        }
    }
    return true;
}

function uploadTicketAttachments($isAdmin = false)
{
    if (!isset($uploadedAttachments)) {
        $uploadedAttachments = WHMCS\File\Upload::getUploadedFiles("attachments");
    }
    $storedAttachments = [];
    foreach ($uploadedAttachments as $key => $uploadedFile) {
        if ($isAdmin || WHMCS\File\Upload::isExtensionAllowed($uploadedFile->getCleanName())) {
            $storedAttachments[] = $uploadedFile->storeAsTicketAttachment();
            unset($uploadedAttachments[$key]);
        }
    }
    return implode("|", $storedAttachments);
}

function saveTicketAttachmentsFromApiCall($attachmentArray = [], $isAdmin = false)
{
    $attachments = [];
    if (0 < count($attachmentArray)) {
        $storage = Storage::ticketAttachments();
        foreach ($attachmentArray as $attachment) {
            if (array_key_exists("name", $attachment) && array_key_exists("data", $attachment)) {
                $filename = $attachment["name"];
                $filenameParts = explode(".", $filename);
                $extension = array_pop($filenameParts);
                if ($isAdmin || WHMCS\File\Upload::isExtensionAllowed($filename)) {
                    $filename = implode(".", $filenameParts);
                    $filename = preg_replace("/[^a-zA-Z0-9\\-_ \\.]/", "", $filename);
                    $fileNameToSave = (new WHMCS\Utility\Random())->number(6) . "_" . $filename . "." . $extension;
                    if (!$filename) {
                        $filename = md5(time());
                        while (true) {
                        }
                        $storage->put($fileNameToSave, base64_decode($attachment["data"]));
                        $attachments[] = $fileNameToSave;
                    }
                    if ($storage->has($fileNameToSave)) {
                    }
                }
            }
        }
    }
    return implode("|", $attachments);
}

function checkTicketAttachmentExtension($file_name)
{
    return WHMCS\File\Upload::isExtensionAllowed($file_name);
}

function pipeDecodeString($input)
{
    $input = preg_replace("/(=\\?[^?]+\\?(q|b)\\?[^?].{0,75}\\?=)(\\s)+=\\?/i", "\\1=?", $input);
    $encodingList = mb_list_encodings();
    while (preg_match("/(=\\?([^?]+)\\?(q|b)\\?([^?].{0,75})\\?=)/i", $input, $matches)) {
        list($encoded, $charset, $encoding, $text) = $matches;
        strtolower($encoding);
        switch (strtolower($encoding)) {
            case "b":
                $text = base64_decode($text);
                break;
            case "q":
                $text = str_replace("_", " ", $text);
                preg_match_all("/=([a-f0-9]{2})/i", $text, $matches);
                foreach ($matches[1] as $value) {
                    $text = str_replace("=" . $value, chr(hexdec($value)), $text);
                }
                break;
            default:
                $detectedEncoding = mb_detect_encoding($text, $encodingList, true);
                if ($detectedEncoding != "UTF-8") {
                    $text = mb_convert_encoding($text, "UTF-8", $detectedEncoding);
                }
                $input = str_replace($encoded, $text, $input);
        }
    }
    return $input;
}

function deleteTicket($ticketid, $replyid = 0)
{
    $ticketid = (int) $ticketid;
    $replyid = (int) $replyid;
    $ticket = WHMCS\Support\Ticket::find($ticketid);
    if (!$ticket) {
        return NULL;
    }
    $attachments = [];
    $where = 0 < $replyid ? ["id" => $replyid] : ["tid" => $ticketid];
    $result = select_query("tblticketreplies", "attachment", $where);
    while ($data = mysql_fetch_array($result)) {
        $attachments[] = $data["attachment"];
    }
    if (!$replyid) {
        $data = get_query_vals("tbltickets", "did, attachment", ["id" => $ticketid]);
        $deptid = $data["did"];
        $attachments[] = $data["attachment"];
    }
    foreach ($attachments as $attachment) {
        if ($attachment) {
            $attachment = explode("|", $attachment);
            foreach ($attachment as $filename) {
                try {
                    Storage::ticketAttachments()->deleteAllowNotPresent($filename);
                } catch (Exception $e) {
                    throw new WHMCS\Exception\Fatal("Could not delete file: " . htmlentities($e->getMessage()));
                }
            }
        }
    }
    if (!$replyid) {
        if (!function_exists("getCustomFields")) {
            require_once ROOTDIR . "/includes/customfieldfunctions.php";
        }
        $customfields = getCustomFields("support", $deptid, $ticketid, true);
        foreach ($customfields as $field) {
            delete_query("tblcustomfieldsvalues", ["fieldid" => $field["id"], "relid" => $ticketid]);
        }
        delete_query("tbltickettags", ["ticketid" => $ticketid]);
        delete_query("tblticketnotes", ["ticketid" => $ticketid]);
        delete_query("tblticketlog", ["tid" => $ticketid]);
        delete_query("tblticketreplies", ["tid" => $ticketid]);
        $ticket->delete();
        logActivity("Deleted Ticket - Ticket ID: " . $ticketid);
        run_hook("TicketDelete", ["ticketId" => $ticketid, "adminId" => WHMCS\Session::get("adminid")]);
    } else {
        delete_query("tblticketreplies", ["id" => $replyid]);
        addticketlog($ticketid, "Deleted Ticket Reply (ID: " . $replyid . ")");
        logActivity("Deleted Ticket Reply - ID: " . $replyid);
        run_hook("TicketDeleteReply", ["ticketId" => $ticketid, "replyId" => $replyid, "adminId" => WHMCS\Session::get("adminid")]);
    }
}

function getKBAutoSuggestions($text)
{
    $kbarticles = [];
    $hookret = run_hook("SubmitTicketAnswerSuggestions", ["text" => $text]);
    if (count($hookret)) {
        foreach ($hookret as $hookdat) {
            foreach ($hookdat as $arrdata) {
                $kbarticles[] = $arrdata;
            }
        }
    } else {
        $ignorewords = ["able", "about", "above", "according", "accordingly", "across", "actually", "after", "afterwards", "again", "against", "ain't", "allow", "allows", "almost", "alone", "along", "already", "also", "although", "always", "among", "amongst", "another", "anybody", "anyhow", "anyone", "anything", "anyway", "anyways", "anywhere", "apart", "appear", "appreciate", "appropriate", "aren't", "around", "aside", "asking", "associated", "available", "away", "awfully", "became", "because", "become", "becomes", "becoming", "been", "before", "beforehand", "behind", "being", "believe", "below", "beside", "besides", "best", "better", "between", "beyond", "both", "brief", "c'mon", "came", "can't", "cannot", "cant", "cause", "causes", "certain", "certainly", "changes", "clearly", "come", "comes", "concerning", "consequently", "consider", "considering", "contain", "containing", "contains", "corresponding", "could", "couldn't", "course", "currently", "definitely", "described", "despite", "didn't", "different", "does", "doesn't", "doing", "don't", "done", "down", "downwards", "during", "each", "eight", "either", "else", "elsewhere", "enough", "entirely", "especially", "even", "ever", "every", "everybody", "everyone", "everything", "everywhere", "exactly", "example", "except", "fifth", "first", "five", "followed", "following", "follows", "former", "formerly", "forth", "four", "from", "further", "furthermore", "gets", "getting", "given", "gives", "goes", "going", "gone", "gotten", "greetings", "hadn't", "happens", "hardly", "hasn't", "have", "haven't", "having", "he's", "hello", "help", "hence", "here", "here's", "hereafter", "hereby", "herein", "hereupon", "hers", "herself", "himself", "hither", "hopefully", "howbeit", "however", "i'll", "i've", "ignored", "immediate", "inasmuch", "indeed", "indicate", "indicated", "indicates", "inner", "insofar", "instead", "into", "inward", "isn't", "it'd", "it'll", "it's", "itself", "just", "keep", "keeps", "kept", "know", "known", "knows", "last", "lately", "later", "latter", "latterly", "least", "less", "lest", "let's", "like", "liked", "likely", "little", "look", "looking", "looks", "mainly", "many", "maybe", "mean", "meanwhile", "merely", "might", "more", "moreover", "most", "mostly", "much", "must", "myself", "name", "namely", "near", "nearly", "necessary", "need", "needs", "neither", "never", "nevertheless", "next", "nine", "nobody", "none", "noone", "normally", "nothing", "novel", "nowhere", "obviously", "often", "okay", "once", "ones", "only", "onto", "other", "others", "otherwise", "ought", "ours", "ourselves", "outside", "over", "overall", "particular", "particularly", "perhaps", "placed", "please", "plus", "possible", "presumably", "probably", "provides", "quite", "rather", "really", "reasonably", "regarding", "regardless", "regards", "relatively", "respectively", "right", "said", "same", "saying", "says", "second", "secondly", "seeing", "seem", "seemed", "seeming", "seems", "seen", "self", "selves", "sensible", "sent", "serious", "seriously", "seven", "several", "shall", "should", "shouldn't", "since", "some", "somebody", "somehow", "someone", "something", "sometime", "sometimes", "somewhat", "somewhere", "soon", "sorry", "specified", "specify", "specifying", "still", "such", "sure", "take", "taken", "tell", "tends", "than", "thank", "thanks", "thanx", "that", "that's", "thats", "their", "theirs", "them", "themselves", "then", "thence", "there", "there's", "thereafter", "thereby", "therefore", "therein", "theres", "thereupon", "these", "they", "they'd", "they'll", "they're", "they've", "think", "third", "this", "thorough", "thoroughly", "those", "though", "three", "through", "throughout", "thru", "thus", "together", "took", "toward", "towards", "tried", "tries", "truly", "trying", "twice", "under", "unfortunately", "unless", "unlikely", "until", "unto", "upon", "used", "useful", "uses", "using", "usually", "value", "various", "very", "want", "wants", "wasn't", "we'd", "we'll", "we're", "we've", "welcome", "well", "went", "were", "weren't", "what", "what's", "whatever", "when", "whence", "whenever", "where", "where's", "whereafter", "whereas", "whereby", "wherein", "whereupon", "wherever", "whether", "which", "while", "whither", "who's", "whoever", "whole", "whom", "whose", "will", "willing", "wish", "with", "within", "without", "won't", "wonder", "would", "wouldn't", "you'd", "you'll", "you're", "you've", "your", "yours", "yourself", "yourselves", "zero"];
        $text = str_replace("\n", " ", $text);
        $textparts = explode(" ", strtolower($text));
        $validword = 0;
        foreach ($textparts as $k => $v) {
            if (in_array($v, $ignorewords) || strlen($textparts[$k]) <= 3 || 100 <= $validword) {
                unset($textparts[$k]);
            } else {
                $validword++;
            }
        }
        $kbarticles = getKBAutoSuggestionsQuery("title", $textparts, "5");
        if (count($kbarticles) < 5) {
            $numleft = 5 - count($kbarticles);
            $kbarticles = array_merge($kbarticles, getKBAutoSuggestionsQuery("article", $textparts, $numleft, $kbarticles));
        }
    }
    return $kbarticles;
}

function getKBAutoSuggestionsQuery($field, $textparts, $limit, $existingkbarticles = "")
{
    $kbarticles = [];
    $where = "";
    foreach ($textparts as $textpart) {
        $where .= $field . " LIKE '%" . db_escape_string($textpart) . "%' OR ";
    }
    $where = !$where ? "id!=''" : substr($where, 0, -4);
    if (is_array($existingkbarticles)) {
        $existingkbids = [];
        foreach ($existingkbarticles as $v) {
            $existingkbids[] = (int) $v["id"];
        }
        $where = "(" . $where . ")";
        if (0 < count($existingkbids)) {
            $where .= " AND id NOT IN (" . db_build_in_array($existingkbids) . ")";
        }
    }
    $result = full_query("SELECT id,parentid FROM tblknowledgebase WHERE " . $where . " ORDER BY useful DESC LIMIT 0," . (int) $limit);
    while ($data = mysql_fetch_array($result)) {
        $articleid = $data["id"];
        $parentid = $data["parentid"];
        if ($parentid) {
            $articleid = $parentid;
        }
        $result2 = full_query("SELECT tblknowledgebaselinks.categoryid FROM tblknowledgebase INNER JOIN tblknowledgebaselinks ON tblknowledgebase.id=tblknowledgebaselinks.articleid INNER JOIN tblknowledgebasecats ON tblknowledgebasecats.id=tblknowledgebaselinks.categoryid WHERE (tblknowledgebase.id=" . (int) $articleid . " OR tblknowledgebase.parentid=" . (int) $articleid . ") AND tblknowledgebasecats.hidden=''");
        $data = mysql_fetch_array($result2);
        $categoryid = $data["categoryid"];
        if ($categoryid) {
            $result2 = full_query("SELECT * FROM tblknowledgebase WHERE (id=" . (int) $articleid . " OR parentid=" . (int) $articleid . ") AND (language='" . db_escape_string(WHMCS\Session::get("Language")) . "' OR language='') ORDER BY language DESC");
            $data = mysql_fetch_array($result2);
            $title = $data["title"];
            $article = $data["article"];
            $views = $data["views"];
            $kbarticles[] = ["id" => $articleid, "category" => $categoryid, "title" => $title, "article" => ticketsummary($article), "text" => $article];
        }
    }
    return $kbarticles;
}

function ticketsummary($text, $length = 100)
{
    $tail = "...";
    $text = strip_tags($text);
    $txtl = strlen($text);
    if ($length < $txtl) {
        for ($i = 1; $text[$length - $i] != " "; $i++) {
            if ($i == $length) {
                return substr($text, 0, $length) . $tail;
            }
        }
        $text = substr($text, 0, $length - $i + 1) . $tail;
    }
    return $text;
}

function getTicketContacts($userid)
{
    $contacts = "";
    $result = select_query("tblcontacts", "", ["userid" => $userid, "email" => ["sqltype" => "NEQ", "value" => ""]]);
    while ($data = mysql_fetch_array($result)) {
        $contacts .= "<option value=\"" . $data["id"] . "\"";
        if (isset($_POST["contactid"]) && $_POST["contactid"] == $data["id"]) {
            $contacts .= " selected";
        }
        $contacts .= ">" . $data["firstname"] . " " . $data["lastname"] . " - " . $data["email"] . "</option>";
    }
    if ($contacts) {
        return "<select name=\"contactid\" class=\"form-control select-inline\"><option value=\"0\">None</option>" . $contacts . "</select>";
    }
}

function getTicketAttachmentsInfo($ticketId, $attachment, $type = "ticket", $relatedId = 0)
{
    $PHP_SELF = App::getPhpSelf();
    $attachments = [];
    if ($attachment) {
        $attachment = explode("|", $attachment);
        foreach ($attachment as $num => $filename) {
            $file = substr($filename, 7);
            switch ($type) {
                case "note":
                    $attachments[] = ["filename" => $file, "isImage" => isAttachmentAnImage($filename), "removed" => false, "dllink" => "dl.php?type=an&id=" . $relatedId . "&i=" . $num, "deletelink" => $PHP_SELF . "?action=viewticket&id=" . $ticketId . "&removeattachment=true&type=n&" . "idsd=" . $relatedId . "&filecount=" . $num . generate_token("link")];
                    break;
                case "reply":
                    $attachments[] = ["filename" => $file, "isImage" => isAttachmentAnImage($filename), "removed" => false, "dllink" => "dl.php?type=ar&id=" . $relatedId . "&i=" . $num, "deletelink" => $PHP_SELF . "?action=viewticket&id=" . $ticketId . "&removeattachment=true&type=r&" . "idsd=" . $relatedId . "&filecount=" . $num . generate_token("link")];
                    break;
                case "removed":
                    $attachments[] = ["filename" => $file, "isImage" => false, "removed" => true, "dllink" => "", "deletelink" => ""];
                    break;
                default:
                    $attachments[] = ["filename" => $file, "isImage" => isAttachmentAnImage($filename), "removed" => false, "dllink" => "dl.php?type=a&id=" . $ticketId . "&i=" . $num, "deletelink" => $PHP_SELF . "?action=viewticket&id=" . $ticketId . "&removeattachment=true&" . "idsd=" . $ticketId . "&filecount=" . $num . generate_token("link")];
            }
        }
    }
    return $attachments;
}

function isAttachmentAnImage($file)
{
    if (!$file) {
        return false;
    }
    try {
        return (bool) getimagesizefromstring(Storage::ticketAttachments()->read($file));
    } catch (Exception $e) {
        return false;
    }
}

function getAdminDepartmentAssignments()
{
    $result = select_query("tbladmins", "supportdepts", ["id" => $_SESSION["adminid"]]);
    $data = mysql_fetch_array($result);
    $supportdepts = $data["supportdepts"];
    $supportdepts = explode(",", $supportdepts);
    foreach ($supportdepts as $k => $v) {
        if (!$v) {
            unset($supportdepts[$k]);
        }
    }
    $DepartmentIDs = $supportdepts;
    return $supportdepts;
}

function getDepartments()
{
    $departmentsarray = [];
    $result = select_query("tblticketdepartments", "id,name", "");
    $departmentsarray = [];
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $name = getdepartmentname($data["id"]);
        $departmentsarray[$id] = $name;
    }
    return $departmentsarray;
}

function validateAdminTicketAccess($ticketid)
{
    $returnValue = false;
    $data = get_query_vals("tbltickets", "id, did, flag, merged_ticket_id", ["id" => $ticketid]);
    if (!$data || !$data["id"]) {
        $returnValue = "invalidid";
    } else {
        if (!in_array($data["did"], getadmindepartmentassignments()) && !checkPermission("Access All Tickets Directly", true)) {
            $returnValue = "deptblocked";
        } else {
            if ($data["flag"] && $data["flag"] != $_SESSION["adminid"] && !checkPermission("View Flagged Tickets", true) && !checkPermission("Access All Tickets Directly", true)) {
                $returnValue = "flagged";
            } else {
                if ($data["merged_ticket_id"]) {
                    $returnValue = "merged" . $data["merged_ticket_id"];
                }
            }
        }
    }
    return $returnValue;
}

function genPredefinedRepliesList($cat, $predefq = "")
{
    global $aInt;
    $catscontent = "";
    $repliescontent = "";
    if (!$predefq) {
        if (!$cat) {
            $cat = 0;
        }
        $result = select_query("tblticketpredefinedcats", "", ["parentid" => $cat], "name", "ASC");
        $i = 0;
        while ($data = mysql_fetch_array($result)) {
            $id = $data["id"];
            $name = $data["name"];
            $catscontent .= "<td width=\"33%\">" . DI::make("asset")->imgTag("folder.gif", "Folder", ["align" => "absmiddle"]) . " <a href=\"#\" onclick=\"selectpredefcat('" . $id . "');return false\">" . $name . "</a></td>";
            $i++;
            if ($i % 3 == 0) {
                $catscontent .= "</tr><tr>";
                $i = 0;
            }
        }
    }
    $where = $predefq ? ["name" => ["sqltype" => "LIKE", "value" => $predefq]] : ["catid" => $cat];
    $result = select_query("tblticketpredefinedreplies", "", $where, "name", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $name = $data["name"];
        $reply = strip_tags($data["reply"]);
        $shortreply = substr($reply, 0, 100) . "...";
        $shortreply = str_replace(chr(10), " ", $shortreply);
        $shortreply = str_replace(chr(13), " ", $shortreply);
        $repliescontent .= "&nbsp;" . DI::make("asset")->imgTag("article.gif", "Article", ["align" => "absmiddle"]) . "<a href=\"#\" onclick=\"selectpredefreply('" . $id . "');return false\">" . $name . "</a> - " . $shortreply . "<br>";
    }
    $content = "";
    if ($catscontent) {
        $content .= "<strong>" . $aInt->lang("support", "categories") . "</strong><br><br><table width=\"95%\"><tr>" . $catscontent . "</tr></table><br>";
    }
    if ($repliescontent) {
        if ($predefq) {
            $content .= "<strong>" . $aInt->lang("global", "searchresults") . "</strong><br><br>" . $repliescontent;
        } else {
            $content .= "<strong>" . $aInt->lang("support", "replies") . "</strong><br><br>" . $repliescontent;
        }
    }
    if (!$content) {
        if ($predefq) {
            $content .= "<strong>" . $aInt->lang("global", "searchresults") . "</strong><br><br>" . $aInt->lang("global", "nomatchesfound") . "<br>";
        } else {
            $content .= "<span style=\"line-height:22px;\">" . $aInt->lang("support", "catempty") . "</span><br>";
        }
    }
    $result = select_query("tblticketpredefinedcats", "parentid", ["id" => $cat]);
    $data = mysql_fetch_array($result);
    if (0 < $cat || $predefq) {
        $content .= "<br /><a href=\"#\" onclick=\"selectpredefcat('0');return false\"><img src=\"images/icons/navrotate.png\" align=\"top\" /> " . $aInt->lang("support", "toplevel") . "</a>";
    }
    if (0 < $cat) {
        $content .= " &nbsp;<a href=\"#\" onclick=\"selectpredefcat('" . $data[0] . "');return false\"><img src=\"images/icons/navback.png\" align=\"top\" /> " . $aInt->lang("support", "uponelevel") . "</a>";
    }
    return $content;
}

function closeTicket($id)
{
    global $whmcs;
    $ticket = WHMCS\Database\Capsule::table("tbltickets")->find($id);
    if (is_null($ticket)) {
        return false;
    }
    if ($ticket->status == "Closed") {
        return false;
    }
    $changes = [];
    if (defined("CLIENTAREA")) {
        addticketlog($id, "Closed by Client");
        $changes["Who"] = Auth::user()->fullName;
    } else {
        if (defined("ADMINAREA") || defined("APICALL")) {
            addticketlog($id, "Status changed to Closed");
            $changes["Who"] = getAdminName(WHMCS\Session::get("adminid"));
        } else {
            addticketlog($id, "Ticket Auto Closed For Inactivity");
            $changes["Who"] = "System";
        }
    }
    $changes["Status"] = ["old" => $ticket->status, "new" => "Closed"];
    update_query("tbltickets", ["status" => "Closed"], ["id" => $ticket->id]);
    $skipFeedbackRequest = false;
    $skipNotification = false;
    $responses = run_hook("TicketClose", ["ticketid" => $id]);
    foreach ($responses as $response) {
        if (array_key_exists("skipFeedbackRequest", $response) && $response["skipFeedbackRequest"]) {
            $skipFeedbackRequest = true;
        }
        if (array_key_exists("skipNotification", $response) && $response["skipNotification"]) {
            $skipNotification = true;
        }
    }
    if (!$skipFeedbackRequest) {
        $department = WHMCS\Database\Capsule::table("tblticketdepartments")->find($ticket->did);
        if ($department->feedback_request) {
            $feedbackcheck = get_query_val("tblticketfeedback", "id", ["ticketid" => $id]);
            if (!$feedbackcheck) {
                sendMessage("Support Ticket Feedback Request", $id);
            }
        }
    }
    if (!$skipNotification) {
        WHMCS\Tickets::notifyTicketChanges($id, $changes);
    }
    return true;
}

function getDepartmentNotificationIds($departmentId)
{
    $admins = WHMCS\User\Admin::join("tbladminroles", "tbladmins.roleid", "=", "tbladminroles.id")->where("tbladmins.disabled", "=", "0")->where("tbladminroles.supportemails", "=", "1")->where("tbladmins.ticketnotifications", "!=", "")->get(["tbladmins.id", "tbladmins.supportdepts", "tbladmins.ticketnotifications"]);
    $notificationAdmins = [];
    foreach ($admins as $admin) {
        if (in_array($departmentId, $admin->supportDepartmentIds) && in_array($departmentId, $admin->receivesTicketNotifications)) {
            $notificationAdmins[] = $admin->id;
        }
    }
    return $notificationAdmins;
}

function checkTicketChanges($ticketId, stdClass $ticketInfo = NULL)
{
    $changeList = [];
    $lastReplyId = (int) App::getFromRequest("lastReplyId");
    $currentSubject = App::getFromRequest("currentSubject");
    $currentStatus = App::getFromRequest("currentStatus");
    $currentCc = App::getFromRequest("currentCc");
    $currentUserId = App::getFromRequest("currentUserId");
    $currentDepartmentId = App::getFromRequest("currentDepartmentId");
    $currentFlag = App::getFromRequest("currentFlag");
    $currentPriority = App::getFromRequest("currentPriority");
    if (!$ticketInfo) {
        $ticketInfo = WHMCS\Database\Capsule::table("tbltickets")->where("tbltickets.id", $ticketId)->leftJoin("tblticketreplies", function (Illuminate\Database\Query\JoinClause $query) use($lastReplyId) {
            $query->on("tbltickets.id", "=", "tblticketreplies.tid")->on("tblticketreplies.id", ">", WHMCS\Database\Capsule::raw($lastReplyId));
        })->groupBy("tblticketreplies.tid")->orderBy("tblticketreplies.id", "DESC")->first(["tbltickets.status", "tbltickets.cc", "tbltickets.userid", "tbltickets.did", "tbltickets.flag", "tbltickets.urgency", "tbltickets.title", "tblticketreplies.id as lastReplyId", "tblticketreplies.admin as replyAdminName", "tblticketreplies.userid as replyUserId"]);
    }
    if (!is_null($ticketInfo->lastReplyId)) {
        if ($ticketInfo->replyAdminName) {
            $changeList[] = AdminLang::trans("support.newReply");
        } else {
            if ($ticketInfo->replyUserId) {
                $changeList[] = AdminLang::trans("support.newReplyByClient");
            }
        }
    }
    if ($ticketInfo->status != $currentStatus) {
        $changeList[] = AdminLang::trans("support.statusChange", [":oldStatus" => $currentStatus, ":newStatus" => $ticketInfo->status]);
    }
    if ($ticketInfo->cc != $currentCc) {
        $changeList[] = AdminLang::trans("support.ccChange", [":oldCc" => $currentCc, ":newCc" => $ticketInfo->cc]);
    }
    if ($ticketInfo->userid != $currentUserId) {
        $changeList[] = AdminLang::trans("support.userChange", [":oldUser" => $currentUserId, ":newUser" => $ticketInfo->userid]);
    }
    if ($ticketInfo->did != $currentDepartmentId) {
        $oldDid = getdepartmentname($currentDepartmentId);
        $newDid = getdepartmentname($ticketInfo->did);
        $changeList[] = AdminLang::trans("support.departmentChange", [":oldDepartment" => $oldDid, ":newDepartment" => $newDid]);
    }
    if ($ticketInfo->flag != $currentFlag) {
        $oldFlag = $currentFlag ? getAdminName($currentFlag) : "Unassigned";
        $newFlag = $ticketInfo->flag ? getAdminName($ticketInfo->flag) : "Unassigned";
        $changeList[] = AdminLang::trans("support.flagChange", [":oldFlag" => $oldFlag, ":newFlag" => $newFlag]);
    }
    if ($ticketInfo->urgency != $currentPriority) {
        $changeList[] = AdminLang::trans("support.priorityChange", [":oldPriority" => $currentPriority, ":newPriority" => $ticketInfo->urgency]);
    }
    if ($ticketInfo->title != $currentSubject) {
        $changeList[] = AdminLang::trans("support.subjectChange", [":oldSubject" => $currentSubject, ":newSubject" => $ticketInfo->title]);
    }
    return $changeList;
}

function removeAttachmentsFromClosedTickets($removeAttachmentsPeriod = 0)
{
    $migrationProgress = WHMCS\File\Migration\FileAssetMigrationProgress::forAssetType(WHMCS\File\FileAsset::TYPE_TICKET_ATTACHMENTS)->first();
    if ($migrationProgress) {
        return ["removed" => 0, "left" => 0, "limitHit" => false, "error" => "system.migrationInProgress"];
    }
    $removedCount = 0;
    $remainingRecords = 0;
    if ($removeAttachmentsPeriod) {
        if ($removeAttachmentsPeriod instanceof WHMCS\Carbon) {
            $removeAttachmentsBefore = $removeAttachmentsPeriod;
        } else {
            if (is_int($removeAttachmentsPeriod)) {
                $removeAttachmentsBefore = WHMCS\Carbon::today()->subMonthsNoOverflow($removeAttachmentsPeriod);
            }
        }
        $closedTicketStatuses = WHMCS\Database\Capsule::table("tblticketstatuses")->where("showactive", 0)->where("showawaiting", 0)->pluck("title")->all();
        $response = removeAttachmentsFromClosedTicketsTable("tblticketreplies", $removeAttachmentsBefore, $closedTicketStatuses);
        $removedCount += $response["removedCount"];
        $remainingRecords += $response["remainingRecords"];
        $response = removeAttachmentsFromClosedTicketsTable("tblticketnotes", $removeAttachmentsBefore, $closedTicketStatuses);
        $removedCount += $response["removedCount"];
        $remainingRecords += $response["remainingRecords"];
        $response = removeAttachmentsFromClosedTicketsTable("tbltickets", $removeAttachmentsBefore, $closedTicketStatuses);
        $removedCount += $response["removedCount"];
        $remainingRecords += $response["remainingRecords"];
    }
    return ["removed" => $removedCount, "left" => $remainingRecords, "limitHit" => 0 < $remainingRecords];
}

function removeAttachmentsFromClosedTicketsTable($table, $removeAttachmentsBefore, $closedTicketStatuses)
{
    $idField = $table . ".id";
    $joinField = NULL;
    $attachmentFieldName = "attachment";
    if ($table == "tblticketreplies") {
        $joinField = "tblticketreplies.tid";
    } else {
        if ($table == "tblticketnotes") {
            $joinField = "tblticketnotes.ticketid";
            $attachmentFieldName = "attachments";
        }
    }
    $query = WHMCS\Database\Capsule::table($table)->where($table . ".attachments_removed", 0)->whereIn("tbltickets.status", $closedTicketStatuses)->where("tbltickets.lastreply", "<", $removeAttachmentsBefore->toDateTimeString())->where($table . "." . $attachmentFieldName, "!=", "");
    if (!is_null($joinField)) {
        $query->join("tbltickets", "tbltickets.id", "=", $joinField);
    }
    if ($table == "tbltickets") {
        $query->where("merged_ticket_id", 0);
    }
    $prunedIds = [];
    $ticketResults = $query->orderBy($idField)->limit(1000)->pluck($table . "." . $attachmentFieldName, $table . ".id")->all();
    foreach ($ticketResults as $ticketId => $ticketAttachments) {
        $attachments = explode("|", $ticketAttachments);
        foreach ($attachments as $attachment) {
            try {
                Storage::ticketAttachments()->deleteAllowNotPresent($attachment);
            } catch (Exception $e) {
                logActivity("Automated Prune Ticket Attachments: Unable to remove attachment '" . $attachment . "': " . $e->getMessage());
            }
        }
        $prunedIds[] = $ticketId;
    }
    WHMCS\Database\Capsule::table($table)->whereIn("id", $prunedIds)->update(["attachments_removed" => "1"]);
    return ["removedCount" => count($prunedIds), "remainingRecords" => $query->count($idField)];
}

function filterEmails($values = false, $unique)
{
    $emails = [];
    foreach ($values as $val) {
        if (filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $email = mb_strtolower($val);
            if ($unique) {
                $emails[$email] = $email;
            } else {
                $emails[] = $email;
            }
        }
    }
    return array_values($emails);
}

function filterEmailsFromString($values = ",", $separator = false, $unique)
{
    if (0 < strlen($values)) {
        $values = implode($separator, filteremails(explode($separator, $values), $unique));
    }
    return $values;
}

function isEmailIncluded($email, $emailList)
{
    $res = false;
    if (0 < strlen($email) && 0 < strlen($emailList)) {
        $res = in_array(strtolower($email), explode(",", strtolower($emailList)));
    }
    return $res;
}

function getAttachmentContent($attachment)
{
    $stream = $attachment->getBinaryContentStream();
    if (!is_null($stream)) {
        return $stream->getContents();
    }
    return NULL;
}

function getAttachmentFilename($attachment, $defaultName, $defaultExt)
{
    $filename = $attachment->getFilename();
    if (!empty($filename)) {
        return WHMCS\File::getFilename($filename, $defaultName);
    }
    $extension = scoalesce(WHMCS\File::guessFileExtension($attachment->getContentType(), $attachment->getContent()), $defaultExt);
    return $defaultName . "." . $extension;
}

function replaceAttachmentCidWithFilename($attachment, $message, $filename)
{
    $cid = $attachment->getContentId();
    if (!is_null($cid)) {
        $cidSymbols = mb_str_split($cid);
        $cidCodes = "";
        foreach ($cidSymbols as $cidSymbol) {
            $code = mb_ord($cidSymbol);
            if (!is_null($code)) {
                $cidCodes .= "\\x{" . decHex($code) . "}";
            }
        }
        $result = mb_ereg_replace("\\[cid:" . $cidCodes . "\\]", "[" . $filename . "]", $message);
        if (is_string($result)) {
            $message = $result;
        }
    }
    return $message;
}
