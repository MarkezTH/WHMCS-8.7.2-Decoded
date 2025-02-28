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
if (isset($_REQUEST["projectid"])) {
    $result = select_query("mod_project", "", ["id" => (int) $projectid]);
    $data = mysql_fetch_assoc($result);
    $projectid = $data["id"];
    if (!$projectid) {
        $apiresults = ["result" => "error", "message" => "Project ID Not Found"];
        return NULL;
    }
}
if (!isset($_REQUEST["taskid"])) {
    $apiresults = ["result" => "error", "message" => "Task ID is Required"];
} else {
    if (isset($_REQUEST["taskid"])) {
        $result = select_query("mod_projecttasks", "", ["id" => (int) $taskid]);
        $data = mysql_fetch_assoc($result);
        $taskid = $data["id"];
        if (!$taskid) {
            $apiresults = ["result" => "error", "message" => "Task ID Not Found"];
            return NULL;
        }
    }
    $taskid = (int) $_REQUEST["taskid"];
    if (isset($_REQUEST["adminid"])) {
        $result_adminid = select_query("tbladmins", "id", ["id" => $_REQUEST["adminid"]]);
        $data_adminid = mysql_fetch_array($result_adminid);
        if (!$data_adminid["id"]) {
            $apiresults = ["result" => "error", "message" => "Admin ID Not Found"];
            return NULL;
        }
    }
    $projectid = $_REQUEST["projectid"];
    $adminid = isset($_REQUEST["adminid"]) ? $data_adminid["id"] : 0;
    $task = $_REQUEST["task"];
    $notes = $_REQUEST["notes"];
    $duedate = $_REQUEST["duedate"];
    $completed = isset($_REQUEST["completed"]) ? 1 : 0;
    $updateqry = [];
    if ($projectid) {
        $updateqry["projectid"] = $projectid;
    }
    if ($task) {
        $updateqry["task"] = $task;
    }
    if ($notes) {
        $updateqry["notes"] = $notes;
    }
    if ($duedate) {
        $updateqry["duedate"] = $duedate;
    }
    if ($adminid) {
        $updateqry["adminid"] = $adminid;
    }
    if ($completed) {
        $updateqry["completed"] = $completed;
    }
    update_query("mod_projecttasks", $updateqry, ["id" => $taskid]);
    $apiresults = ["result" => "success", "message" => "Task has been updated"];
}
