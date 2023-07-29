<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$result = select_query("tblquotes", "", ["id" => $quoteid]);
$data = mysql_fetch_array($result);
$quoteid = $data["id"];
if (!$quoteid) {
    $apiresults = ["result" => "error", "message" => "Quote ID Not Found"];
} else {
    delete_query("tblquotes", ["id" => $quoteid]);
    delete_query("tblquoteitems", ["quoteid" => $quoteid]);
    $apiresults = ["result" => "success"];
}
