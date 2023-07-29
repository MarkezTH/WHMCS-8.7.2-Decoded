<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("deleteTicket")) {
    require ROOTDIR . "/includes/ticketfunctions.php";
}
$ticketId = App::getFromRequest("ticketid");
$replyId = App::getFromRequest("replyid");
if (!$ticketId) {
    $apiresults = ["result" => "error", "message" => "Ticket ID Required"];
} else {
    if (!$replyId) {
        $apiresults = ["result" => "error", "message" => "Reply ID Required"];
    } else {
        $ticket = WHMCS\Database\Capsule::table("tbltickets")->find($ticketId);
        if (!$ticket) {
            $apiresults = ["result" => "error", "message" => "Ticket ID Not Found"];
        } else {
            $reply = WHMCS\Database\Capsule::table("tblticketreplies")->where("tid", $ticketId)->find($replyId);
            if (!$reply) {
                $apiresults = ["result" => "error", "message" => "Reply ID Not Found"];
            } else {
                try {
                    deleteTicket($ticketId, $replyId);
                    $apiresults = ["result" => "success"];
                } catch (Exception $e) {
                    $apiresults = ["result" => "error", "message" => $e->getMessage()];
                    return NULL;
                }
            }
        }
    }
}
