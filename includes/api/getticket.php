<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("AddReply")) {
    require ROOTDIR . "/includes/ticketfunctions.php";
}
$ticketid = App::getFromRequest("ticketid");
$ticketnum = App::getFromRequest("ticketnum");
$repliessort = App::getFromRequest("repliessort");
$repliessort = strtoupper($repliessort);
$sortorder = in_array($repliessort, ["ASC", "DESC"]) ? $repliessort : "ASC";
if ($ticketid) {
    $ticket = WHMCS\Support\Ticket::find($ticketid);
} else {
    $ticket = WHMCS\Support\Ticket::where("tid", $ticketnum)->first();
}
if (is_null($ticket)) {
    $apiresults = ["result" => "error", "message" => "Ticket ID Not Found"];
} else {
    $apiresults = array_merge(["result" => "success"], $ticket->toArray());
    $first_reply = ["replyid" => "0", "userid" => $ticket->userid, "contactid" => $ticket->contactid, "name" => $apiresults["requestor_name"], "email" => $apiresults["requestor_email"], "requestor_name" => $apiresults["requestor_name"], "requestor_email" => $apiresults["requestor_email"], "requestor_type" => $apiresults["requestor_type"], "date" => $ticket->date->toDateTimeString(), "message" => $ticket->getSafeMessage(), "attachment" => $ticket->attachment, "attachments" => $apiresults["attachments"], "attachments_removed" => $ticket->attachmentsRemoved, "admin" => $ticket->admin];
    if ($sortorder == "ASC") {
        $apiresults["replies"]["reply"][] = $first_reply;
    }
    foreach ($ticket->replies()->orderBy("id", $sortorder)->get() as $reply) {
        $attachments = [];
        foreach ($reply->getAttachmentsForDisplay() as $key => $filename) {
            $attachments[] = ["filename" => $filename, "index" => $key];
        }
        $apiresults["replies"]["reply"][] = ["replyid" => $reply->id, "userid" => $reply->userid, "contactid" => $reply->contactid, "name" => $reply->getRequestorName(), "email" => $reply->getRequestorEmail(), "requestor_name" => $reply->getRequestorName(), "requestor_email" => $reply->getRequestorEmail(), "requestor_type" => $reply->getRequestorType(), "date" => $reply->date->toDateTimeString(), "message" => $reply->getSafeMessage(), "attachment" => $reply->attachment, "attachments" => $attachments, "attachments_removed" => stringLiteralToBool($reply->attachments_removed), "admin" => $reply->admin, "rating" => $reply->rating];
    }
    if ($sortorder != "ASC") {
        $apiresults["replies"]["reply"][] = $first_reply;
    }
    $apiresults["notes"] = [];
    foreach ($ticket->notes()->orderBy("id", $sortorder)->get() as $note) {
        $attachments = [];
        foreach ($note->getAttachmentsForDisplay() as $key => $filename) {
            $attachments[] = ["filename" => $filename, "index" => $key];
        }
        $apiresults["notes"]["note"][] = ["noteid" => $note->id, "date" => $note->date->toDateTimeString(), "message" => $note->getSafeMessage(), "attachment" => $note->attachment, "attachments" => $attachments, "attachments_removed" => $note->attachmentsRemoved, "admin" => $note->admin];
    }
    $responsetype = "xml";
}
