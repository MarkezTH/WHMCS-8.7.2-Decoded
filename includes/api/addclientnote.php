<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$userid = (int) App::getFromRequest("userid");
$notes = (string) App::getFromRequest("notes");
$sticky = (int) (bool) App::getFromRequest("sticky");
$userid = get_query_val("tblclients", "id", ["id" => $userid]);
if (!$userid) {
    $apiresults = ["result" => "error", "message" => "Client ID not found"];
} else {
    if (!$notes) {
        $apiresults = ["result" => "error", "message" => "Notes can not be empty"];
    } else {
        $sticky = $sticky ? 1 : 0;
        $noteid = insert_query("tblnotes", ["userid" => $userid, "adminid" => $_SESSION["adminid"], "created" => "now()", "modified" => "now()", "note" => $notes, "sticky" => $sticky]);
        $apiresults = ["result" => "success", "noteid" => $noteid];
    }
}
