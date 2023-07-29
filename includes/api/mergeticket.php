<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$masterTicketId = (int) App::getFromRequest("ticketid");
$mergeTicketIds = array_filter(explode(",", App::getFromRequest("mergeticketids")));
$newSubject = App::getFromRequest("newsubject");
if (!$masterTicketId) {
    $apiresults = ["result" => "error", "message" => "Ticket ID Required"];
} else {
    try {
        $masterTicket = WHMCS\Support\Ticket::where("merged_ticket_id", 0)->findOrFail($masterTicketId);
    } catch (Exception $e) {
        $apiresults = ["result" => "error", "message" => "Ticket ID Invalid"];
        return NULL;
    }
    if (count($mergeTicketIds) === 0) {
        $apiresults = ["result" => "error", "message" => "Merge Ticket IDs Required"];
    } else {
        $invalidMergeTicketIds = [];
        foreach ($mergeTicketIds as $mergeTicketId) {
            try {
                $mergeTicket = WHMCS\Support\Ticket::findOrFail($mergeTicketId);
            } catch (Exception $e) {
                $invalidMergeTicketIds[] = $mergeTicketId;
            }
        }
        if (0 < count($invalidMergeTicketIds)) {
            $apiresults = ["result" => "error", "message" => "Invalid Merge Ticket IDs: " . implode(", ", $invalidMergeTicketIds)];
            return NULL;
        }
        if ($newSubject) {
            $masterTicket->title = $newSubject;
            $masterTicket->save();
        }
        $masterTicket->mergeOtherTicketsInToThis($mergeTicketIds);
        $apiresults = ["result" => "success", "ticketid" => $masterTicketId];
    }
}
