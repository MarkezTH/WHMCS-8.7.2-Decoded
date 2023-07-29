<?php

define("CLIENTAREA", true);
require "init.php";
require "includes/ticketfunctions.php";
require "includes/clientfunctions.php";
require "includes/customfieldfunctions.php";
$tid = $whmcs->get_req_var("tid");
$c = $whmcs->get_req_var("c");
$closeticket = $whmcs->get_req_var("closeticket");
$postreply = $whmcs->get_req_var("postreply");
$replyname = $whmcs->get_req_var("replyname");
$replyemail = $whmcs->get_req_var("replyemail");
$replymessage = $whmcs->get_req_var("replymessage");
$c = preg_replace("/[^A-Za-z0-9]/", "", $c);
$pagetitle = $_LANG["supportticketsviewticket"];
$breadcrumbnav = "<a href=\"index.php\">" . $_LANG["globalsystemname"] . "</a> > <a href=\"clientarea.php\">" . $_LANG["clientareatitle"] . "</a> > <a href=\"supporttickets.php\">" . $_LANG["supportticketspagetitle"] . "</a> > <a href=\"viewticket.php?tid=" . $tid . "&amp;c=" . $c . "\">" . $_LANG["supportticketsviewticket"] . "</a>";
$pageicon = "images/supporttickets_big.gif";
$templatefile = "viewticket";
$displayTitle = Lang::trans("supportticketsviewticket");
$tagline = "";
initialiseClientArea($pagetitle, $displayTitle, $tagline, $pageicon, $breadcrumbnav);
if (Auth::user() && Auth::client()) {
    checkContactPermission("tickets");
}
$usingsupportmodule = false;
if ($CONFIG["SupportModule"]) {
    if (!isValidforPath($CONFIG["SupportModule"])) {
        exit("Invalid Support Module");
    }
    $supportmodulepath = "modules/support/" . $CONFIG["SupportModule"] . "/viewticket.php";
    if (file_exists($supportmodulepath)) {
        $usingsupportmodule = true;
        $templatefile = "";
        require $supportmodulepath;
        outputClientArea($templatefile);
        exit;
    }
}
$ticket = WHMCS\Support\Ticket::where("tid", $tid)->where("c", $c)->first();
if (!$ticket) {
    $smarty->assign("error", true);
    $smarty->assign("invalidTicketId", true);
} else {
    if ($ticket->isMergedTicket()) {
        $mergedTicket = WHMCS\Support\Ticket::find($ticket->merged_ticket_id);
        redir("tid=" . $mergedTicket->tid . "&c=" . $mergedTicket->c);
    }
    if ($ticket->clientId && WHMCS\Config\Setting::getValue("RequireLoginforClientTickets")) {
        Auth::requireLoginAndClient(true);
        try {
            Auth::forceSwitchClientIdOrFail($ticket->userid);
            $smarty->assign("invalidTicketId", false);
        } catch (Exception $e) {
            $smarty->assign("invalidTicketId", true);
            outputClientArea($templatefile, false, ["ClientAreaPageViewTicket"]);
            exit;
        }
    }
}
if ($ticket) {
    $tickets = new WHMCS\Tickets();
    $tickets->setID($ticket->id);
    $AccessedTicketIDs = WHMCS\Session::get("AccessedTicketIDs");
    $AccessedTicketIDsArray = explode(",", $AccessedTicketIDs);
    $AccessedTicketIDsArray[] = $ticket->id;
    WHMCS\Session::set("AccessedTicketIDs", implode(",", $AccessedTicketIDsArray));
    if ($whmcs->get_req_var("feedback") && $tickets->getDepartmentFeedbackNotifications()) {
        Menu::primarySidebar("ticketFeedback");
        Menu::secondarySidebar("ticketFeedback");
        $templatefile = "ticketfeedback";
        $smartyvalues["displayTitle"] = Lang::trans("ticketfeedbackrequest");
        $smartyvalues["tagline"] = Lang::trans("ticketfeedbackforticket") . $ticket->ticketNumber;
        $smartyvalues["id"] = $ticket->id;
        $smartyvalues["tid"] = $ticket->ticketNumber;
        $smartyvalues["c"] = $ticket->accessKey;
        $status = $ticket->status;
        $closedcheck = get_query_val("tblticketstatuses", "id", ["title" => $status, "showactive" => "0"]);
        $smartyvalues["stillopen"] = !$closedcheck ? true : false;
        $feedbackcheck = get_query_val("tblticketfeedback", "id", ["ticketid" => $ticket->id]);
        $smartyvalues["feedbackdone"] = $feedbackcheck;
        $date = $ticket->date;
        $smartyvalues["opened"] = WHMCS\Carbon::createFromFormat("Y-m-d H:i:s", $date)->format("l, jS F Y H:ia");
        $lastreply = get_query_val("tblticketreplies", "date", ["tid" => $ticket->id], "id", "DESC");
        if (!$lastreply) {
            $lastreply = $date;
        }
        $smartyvalues["lastreply"] = WHMCS\Carbon::createFromFormat("Y-m-d H:i:s", $lastreply)->format("l, jS F Y H:ia");
        $duration = getTicketDuration($date, $lastreply);
        $smartyvalues["duration"] = $duration;
        $ratings = [];
        for ($i = 1; $i <= 10; $i++) {
            $ratings[] = $i;
        }
        $smartyvalues["ratings"] = $ratings;
        $comments = $whmcs->isInRequest("comments") ? $whmcs->get_req_var("comments") : [];
        $staffinvolved = [];
        $sql = "SELECT DISTINCT tblticketreplies.admin,tbladmins.id AS staffid FROM tblticketreplies LEFT JOIN tbladmins ON CONCAT(tbladmins.firstname, \" \", tbladmins.lastname)=tblticketreplies.admin WHERE tblticketreplies.tid=? AND tbladmins.id IS NOT NULL";
        $staffList = WHMCS\Database\Capsule::connection()->select($sql, [$ticket->id]);
        foreach ($staffList as $staffMember) {
            $adminInvolved = trim($staffMember->admin);
            if ($adminInvolved) {
                $staffinvolved[$staffMember->staffid] = $adminInvolved;
            }
            if (!isset($comments[$staffMember->staffid])) {
                $comments[$staffMember->staffid] = "";
            }
        }
        $smartyvalues["staffinvolved"] = $staffinvolved;
        $smartyvalues["staffinvolvedtext"] = implode(", ", $staffinvolved);
        $smartyvalues["rate"] = $whmcs->get_req_var("rate");
        if (!isset($comments["generic"])) {
            $comments["generic"] = "";
        }
        $smartyvalues["comments"] = $comments;
        $errormessage = "";
        $smartyvalues["success"] = false;
        if ($whmcs->get_req_var("validate")) {
            check_token();
            foreach ($staffinvolved as $staffid => $staffname) {
                if (!$whmcs->get_req_var("rate", $staffid)) {
                    $errormessage .= "<li>" . Lang::trans("feedbacksupplyrating", [":staffname" => $staffname]) . "</li>";
                }
            }
            $smartyvalues["errormessage"] = $errormessage;
            if (!$errormessage) {
                foreach ($staffinvolved as $staffid => $staffname) {
                    insert_query("tblticketfeedback", ["ticketid" => $ticket->id, "adminid" => $staffid, "rating" => $whmcs->get_req_var("rate", $staffid), "comments" => $whmcs->get_req_var("comments", $staffid), "datetime" => "now()", "ip" => WHMCS\Utility\Environment\CurrentRequest::getIP()]);
                }
                if (trim($whmcs->get_req_var("comments", "generic"))) {
                    insert_query("tblticketfeedback", ["ticketid" => $ticket->id, "adminid" => "0", "rating" => "0", "comments" => $whmcs->get_req_var("comments", "generic"), "datetime" => "now()", "ip" => WHMCS\Utility\Environment\CurrentRequest::getIP()]);
                }
                $smartyvalues["success"] = true;
            }
        }
        outputClientArea($templatefile);
        exit;
    } else {
        if ($closeticket) {
            closeTicket($ticket->id);
            redir("tid=" . $ticket->ticketNumber . "&c=" . $ticket->accessKey);
        }
        $rating = $whmcs->get_req_var("rating");
        if ($rating) {
            $rating = explode("_", $rating);
            $replyid = isset($rating[0]) && 4 < strlen($rating[0]) ? substr($rating[0], 4) : "";
            $ratingscore = isset($rating[1]) ? $rating[1] : "";
            if (is_numeric($replyid) && is_numeric($ratingscore)) {
                update_query("tblticketreplies", ["rating" => $ratingscore], ["id" => $replyid, "tid" => $ticket->id]);
            }
            redir("tid=" . $ticket->ticketNumber . "&c=" . $ticket->accessKey);
        }
        $action = App::getFromRequest("action");
        if ($action) {
            check_token();
            $email = trim(App::getFromRequest("email"));
            $response = [];
            try {
                $cc = explode(",", $ticket->cc);
                switch ($action) {
                    case "delete":
                        if (!in_array($email, $cc)) {
                            throw new WHMCS\Exception\Validation\InvalidValue(Lang::trans("support.deleteEmailNotExisting", [":email" => $email]));
                        }
                        $cc = array_flip($cc);
                        unset($cc[$email]);
                        $cc = array_filter(array_flip($cc));
                        $response = ["success" => true, "message" => Lang::trans("support.successDelete", [":email" => $email])];
                        break;
                    case "add":
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            throw new WHMCS\Exception\Validation\InvalidValue(Lang::trans("support.invalidEmail", [":email" => $email]));
                        }
                        if (in_array($email, $cc)) {
                            throw new WHMCS\Exception\Validation\InvalidValue(Lang::trans("support.addEmailExists", [":email" => $email]));
                        }
                        $clientEmail = Auth::client()->email;
                        if ($email == $clientEmail) {
                            throw new WHMCS\Exception\Validation\InvalidValue(Lang::trans("support.clientEmail", [":email" => $email]));
                        }
                        $cc[] = $email;
                        $cc = array_filter($cc);
                        $response = ["success" => true, "message" => Lang::trans("support.successAdd", [":email" => $email])];
                        break;
                    default:
                        $response = ["error" => "An invalid request was made. Please try again."];
                        if (array_key_exists("success", $response) && $response["success"]) {
                            WHMCS\Database\Capsule::table("tbltickets")->where("id", $ticket->id)->update(["cc" => implode(",", $cc)]);
                            addTicketLog($ticket->id, $response["message"]);
                        }
                }
            } catch (Exception $e) {
                $response = ["error" => $e->getMessage()];
            }
            $jsonResponse = new WHMCS\Http\JsonResponse();
            $jsonResponse->setData($response);
            $jsonResponse->send();
            WHMCS\Terminus::getInstance()->doExit();
        }
        $errormessage = "";
        $uploadMaxFileSize = getUploadMaxFileSize("MB");
        if ($postreply) {
            if (checkTicketAttachmentSize()) {
                check_token();
                $smarty->assign("postingReply", true);
                $validate = new WHMCS\Validate();
                if (!Auth::user()) {
                    $validate->validate("required", "replyname", "supportticketserrornoname");
                    if ($validate->validate("required", "replyemail", "supportticketserrornoemail")) {
                        $validate->validate("email", "replyemail", "clientareaerroremailinvalid");
                    }
                }
                $validate->validate("required", "replymessage", "supportticketserrornomessage");
                if ($validate->hasErrors()) {
                    $errormessage .= $validate->getHTMLErrorOutput();
                }
                if ($_FILES["attachments"]) {
                    foreach ($_FILES["attachments"]["name"] as $num => $filename) {
                        $filename = trim($filename);
                        if ($filename) {
                            $filenameparts = explode(".", $filename);
                            $extension = end($filenameparts);
                            $filename = implode(array_slice($filenameparts, 0, -1));
                            $filename = preg_replace("/[^a-zA-Z0-9-_ ]/", "", $filename);
                            $filename .= "." . $extension;
                            $validextension = checkTicketAttachmentExtension($filename);
                            if (!$validextension) {
                                $errormessage .= "<li>" . $_LANG["supportticketsfilenotallowed"];
                            }
                        }
                    }
                }
                if (!$errormessage) {
                    try {
                        $attachments = uploadTicketAttachments();
                        $from = ["name" => $replyname, "email" => $replyemail];
                        AddReply($ticket->id, $ticket->userid, "", $replymessage, "", $attachments, $from, "", false, false, true);
                        redir("tid=" . $ticket->ticketNumber . "&c=" . $ticket->accessKey);
                    } catch (WHMCS\Exception\Storage\StorageException $e) {
                        $errormessage = Lang::trans("support.ticketError");
                    }
                }
            } else {
                $errormessage .= Lang::trans("supportticketsuploadtoolarge");
                $errormessage .= "  " . Lang::trans("maxFileSize", [":fileSize" => $uploadMaxFileSize]);
            }
        } else {
            $smarty->assign("postingReply", false);
        }
        $smarty->assign("displayTitle", "Ticket #" . $ticket->ticketNumber . " - " . $ticket->subject);
        $smarty->assign("uploadMaxFileSize", $uploadMaxFileSize);
        $lastreply = fromMySQLDate($ticket->lastreply, 1, 1);
        $markup = new WHMCS\View\Markup\Markup();
        $markupFormat = $markup->determineMarkupEditor("ticket_msg", $ticket->editor);
        $message = $markup->transform($ticket->getSafeMessage(), $markupFormat);
        $closedTicketStatuses = WHMCS\Database\Capsule::table("tblticketstatuses")->where("showactive", "=", "0")->where("showawaiting", "=", "0")->pluck("title")->all();
        $showclosebutton = !in_array($ticket->status, $closedTicketStatuses);
        $customfields = getCustomFields("support", $ticket->departmentId, $ticket->id, "", "", "", true);
        ClientRead($ticket->id);
        $smarty->assign("id", $ticket->id);
        $smarty->assign("c", $ticket->accessKey);
        $smarty->assign("tid", $ticket->ticketNumber);
        $smarty->assign("date", fromMySQLDate($ticket->date, 1, 1));
        $smarty->assign("departmentid", $ticket->departmentId);
        $smarty->assign("department", $ticket->getDepartmentName());
        $smarty->assign("subject", $ticket->subject);
        $smarty->assign("message", $message);
        $smarty->assign("status", getStatusColour($ticket->status));
        $smarty->assign("urgency", $_LANG["supportticketsticketurgency" . strtolower($ticket->priority)]);
        $smarty->assign("priority", $_LANG["supportticketsticketurgency" . strtolower($ticket->priority)]);
        $smarty->assign("attachments", $ticket->getAttachmentsForDisplay());
        $smarty->assign("attachments_removed", $ticket->attachmentsRemoved);
        $smarty->assign("lastreply", $ticket->lastreply);
        $smarty->assign("showclosebutton", $showclosebutton);
        $smarty->assign("closedticket", !$showclosebutton);
        $smarty->assign("customfields", $customfields);
        $smarty->assign("ratingenabled", $CONFIG["TicketRatingEnabled"]);
        $locale = preg_replace("/[^a-zA-Z0-9_\\-]*/", "", Lang::getLanguageLocale());
        $locale = $locale == "locale" ? "en" : substr($locale, 0, 2);
        $smarty->assign("mdeLocale", $locale);
        $smarty->assign("loadMarkdownEditor", true);
        $replies = $ascreplies = [];
        $ascreplies[] = ["id" => "", "userid" => $ticket->userid, "contactid" => $ticket->contactid, "name" => $ticket->getRequestorName(), "email" => $ticket->getRequestorEmail(), "ipaddress" => $ticket->ipaddress, "requestor" => ["name" => $ticket->getRequestorName(), "email" => $ticket->getRequestorEmail(), "type" => $ticket->getRequestorType(), "type_normalised" => WHMCS\Utility\Status::normalise($ticket->getRequestorType())], "admin" => $ticket->postedByAnAdmin(), "date" => fromMySQLDate($ticket->date, 1, 1), "message" => $message, "attachments" => $ticket->getAttachmentsForDisplay(), "attachments_removed" => $ticket->attachmentsRemoved, "rating" => $ticket->rating, "ipaddress" => $ticket->ipaddress];
        foreach ($ticket->replies()->orderBy("date")->get() as $reply) {
            $markupFormat = $markup->determineMarkupEditor("ticket_reply", $reply->editor);
            $message = $markup->transform($reply->getSafeMessage(), $markupFormat);
            $ascreplies[] = ["id" => $reply->id, "userid" => $reply->userid, "contactid" => $reply->contactid, "name" => $reply->getRequestorName(), "email" => $reply->getRequestorEmail(), "requestor" => ["name" => $reply->getRequestorName(), "email" => $reply->getRequestorEmail(), "type" => $reply->getRequestorType(), "type_normalised" => WHMCS\Utility\Status::normalise($reply->getRequestorType())], "admin" => $reply->postedByAnAdmin(), "date" => fromMySQLDate($reply->date, 1, 1), "message" => $message, "attachments" => $reply->getAttachmentsForDisplay(), "attachments_removed" => $reply->attachmentsRemoved, "rating" => $reply->rating, "ipaddress" => NULL];
            $replies[] = $ascreplies;
        }
        $smarty->assign("replies", $replies);
        $smarty->assign("ascreplies", $ascreplies);
        krsort($ascreplies);
        $smarty->assign("descreplies", $ascreplies);
        $ratings = [];
        for ($counter = 1; $counter <= 5; $counter++) {
            $ratings[] = $counter;
        }
        $smarty->assign("ratings", $ratings);
        if (Auth::user()) {
            $replyname = Auth::user()->fullName;
            $replyemail = Auth::user()->email;
        }
        $smarty->assign("errormessage", $errormessage);
        $smarty->assign("replyname", $replyname);
        $smarty->assign("replyemail", $replyemail);
        $smarty->assign("replymessage", $replymessage);
        $smarty->assign("allowedfiletypes", implode(", ", $tickets->getAllowedAttachments()));
    }
}
Menu::addContext("ticketId", $ticket->id);
Menu::addContext("ticket", $ticket);
Menu::primarySidebar("ticketView");
Menu::secondarySidebar("ticketView");
outputClientArea($templatefile, false, ["ClientAreaPageViewTicket"]);
