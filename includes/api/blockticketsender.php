<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$ticketId = App::getFromRequest("ticketid");
$delete = (bool) App::getFromRequest("delete");
if (!$ticketId) {
    $apiresults = ["result" => "error", "message" => "Ticket ID Required"];
} else {
    $ticket = WHMCS\Database\Capsule::table("tbltickets")->find($ticketId);
    if (!$ticket) {
        $apiresults = ["result" => "error", "message" => "Ticket ID Not Found"];
    } else {
        if ($ticket->userid) {
            $apiresults = ["result" => "error", "message" => "A Client Cannot Be Blocked"];
        } else {
            $email = $ticket->email;
            if (!$email) {
                $apiresults = ["result" => "error", "message" => "Missing Email Address"];
            } else {
                $blockedAlready = WHMCS\Database\Capsule::table("tblticketspamfilters")->where("type", "sender")->where("content", $email)->count();
                if ($blockedAlready === 0) {
                    WHMCS\Database\Capsule::table("tblticketspamfilters")->insert(["type" => "sender", "content" => $email]);
                }
                $apiresults = ["result" => "success", "deleted" => false];
                if ($delete) {
                    if (!function_exists("deleteTicket")) {
                        require ROOTDIR . "/includes/ticketfunctions.php";
                    }
                    try {
                        deleteTicket($ticketId);
                        $apiresults["deleted"] = true;
                    } catch (Exception $e) {
                        $apiresults = ["result" => "error", "message" => $e->getMessage()];
                        return NULL;
                    }
                }
            }
        }
    }
}
