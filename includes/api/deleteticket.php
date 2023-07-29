<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$ticket = NULL;
if (!empty($ticketid)) {
    $ticket = WHMCS\Support\Ticket::find($ticketid);
}
if (is_null($ticket)) {
    $apiresults = ["result" => "error", "message" => "Ticket ID not found"];
} else {
    if (!function_exists("deleteTicket")) {
        require ROOTDIR . "/includes/ticketfunctions.php";
    }
    deleteTicket($ticket->id);
    $apiresults = ["result" => "success"];
}
