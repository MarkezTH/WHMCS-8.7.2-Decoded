<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$projectid = (int) $_REQUEST["projectid"];
$taskid = (int) $_REQUEST["taskid"];
if (!$projectid) {
    $apiresults = ["result" => "error", "message" => "Project ID is Required"];
} else {
    if (!$taskid) {
        $apiresults = ["result" => "error", "message" => "Task ID is Required"];
    } else {
        $result = select_query("mod_project", "", ["id" => (int) $projectid]);
        $data = mysql_fetch_assoc($result);
        $projectid = $data["id"];
        if (!$projectid) {
            $apiresults = ["result" => "error", "message" => "Project ID Not Found"];
        } else {
            $result_taskid = select_query("mod_projecttasks", "id", ["id" => $_REQUEST["taskid"]]);
            $data_taskid = mysql_fetch_array($result_taskid);
            if (!$data_taskid["id"]) {
                $apiresults = ["result" => "error", "message" => "Task ID Not Found"];
            } else {
                delete_query("mod_projecttasks", ["id" => $taskid, "projectid" => $projectid]);
                $apiresults = ["result" => "success", "message" => "Task has been deleted"];
            }
        }
    }
}
