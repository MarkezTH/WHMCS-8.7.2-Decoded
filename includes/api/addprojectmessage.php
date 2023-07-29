<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getClientsDetails")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
if (!function_exists("saveCustomFields")) {
    require ROOTDIR . "/includes/customfieldfunctions.php";
}
if (!isset($_REQUEST["projectid"])) {
    $apiresults = ["result" => "error", "message" => "Project ID not Set"];
} else {
    if (isset($_REQUEST["projectid"])) {
        $result = select_query("mod_project", "", ["id" => (int) $projectid]);
        $data = mysql_fetch_assoc($result);
        $projectid = $data["id"];
        if (!$projectid) {
            $apiresults = ["result" => "error", "message" => "Project ID Not Found"];
            return NULL;
        }
    }
    if (!isset($_REQUEST["message"])) {
        $apiresults = ["result" => "error", "message" => "Message not Entered"];
    } else {
        if (!isset($_REQUEST["adminid"])) {
            $_REQUEST["adminid"] = $_SESSION["adminid"];
        }
        if (isset($_REQUEST["adminid"])) {
            $result_adminid = select_query("tbladmins", "id", ["id" => $_REQUEST["adminid"]]);
            $data_adminid = mysql_fetch_array($result_adminid);
            if (!$data_adminid["id"]) {
                $apiresults = ["result" => "error", "message" => "Admin ID Not Found"];
                return NULL;
            }
        }
        $projectid = $_REQUEST["projectid"];
        $adminid = $_REQUEST["adminid"];
        $message = $_REQUEST["message"];
        $date = "now()";
        $apply = insert_query("mod_projectmessages", ["projectid" => $projectid, "adminid" => $adminid, "message" => $message, "date" => $date]);
        $apiresults = ["result" => "success", "message" => "Message has been added"];
    }
}
