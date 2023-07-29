<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("closeClient")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
$result = select_query("tblclients", "id", ["id" => $clientid]);
$data = mysql_fetch_array($result);
if (!is_array($data) || empty($data["id"])) {
    $apiresults = ["result" => "error", "message" => "Client ID Not Found"];
} else {
    closeClient($_REQUEST["clientid"]);
    $apiresults = ["result" => "success", "clientid" => $_REQUEST["clientid"]];
}
