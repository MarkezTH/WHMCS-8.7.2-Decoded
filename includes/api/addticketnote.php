<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getAdminName")) {
    require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "adminfunctions.php";
}
if (!function_exists("AddNote")) {
    require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ticketfunctions.php";
}
$errorResponse = function ($message) {
    return ["result" => "error", "message" => $message];
};
$ticketnum = App::get_req_var("ticketnum");
$ticketid = (int) App::get_req_var("ticketid");
$message = App::getFromRequest("message");
$useMarkdown = stringLiteralToBool(App::get_req_var("markdown"));
$created = App::getFromRequest("created");
$ticketData = WHMCS\Database\Capsule::table("tbltickets");
if ($ticketnum) {
    $ticketData->where("tid", $ticketnum);
} else {
    $ticketData->where("id", $ticketid);
}
$data = $ticketData->first(["id", "tid", "title"]);
if (!$data) {
    $apiresults = ["result" => "error", "message" => "Ticket ID not found"];
} else {
    $ticketid = $data->id;
    if (!$message) {
        $apiresults = ["result" => "error", "message" => "Message is required"];
    } else {
        $timeDateNow = false;
        if (!$created) {
            $created = WHMCS\Carbon::now();
        } else {
            try {
                $created = WHMCS\Carbon::parse($created);
                $timeDateNow = WHMCS\Carbon::now();
            } catch (Exception $e) {
                $apiresults = ["result" => "error", "message" => "Invalid Date Format"];
                return NULL;
            }
        }
        if ($timeDateNow && !$created->lte($timeDateNow)) {
            $apiresults = ["result" => "error", "message" => "Note creation date cannot be in the future"];
        } else {
            try {
                AddNote($ticketid, $message, $useMarkdown, $created);
            } catch (WHMCS\Exception\Storage\StorageException $e) {
                $apiresults = $errorResponse(sprintf("%s. See activity log for details.", $e->getMessage()));
                return NULL;
            }
            $mentionedAdminIds = WHMCS\Mentions\Mentions::getIdsForMentions($message);
            $changes["Note"] = ["new" => $message, "editor" => "markdown"];
            $changes["Who"] = getAdminName(WHMCS\Session::get("adminid"));
            WHMCS\Tickets::notifyTicketChanges($ticketid, $changes, [], $mentionedAdminIds);
            if ($mentionedAdminIds) {
                $ticketTid = $ticket->tid;
                $ticketTitle = $ticket->title;
                WHMCS\Mentions\Mentions::sendNotification("ticket", $ticketid, $message, $mentionedAdminIds, AdminLang::trans("mention.ticket") . " #" . $ticketTid . " - " . $ticketTitle);
            }
            $apiresults = ["result" => "success"];
        }
    }
}
