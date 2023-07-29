<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!isset($_REQUEST["projectid"])) {
    $apiresults = ["result" => "error", "message" => "Project ID Not Set"];
} else {
    $result = select_query("mod_project", "", ["id" => (int) $projectid]);
    $data = mysql_fetch_assoc($result);
    $projectid = $data["id"];
    if (!$projectid) {
        $apiresults = ["result" => "error", "message" => "Project ID Not Found"];
    } else {
        $apiresults["projectinfo"] = $data;
        $result_task = select_query("mod_projecttasks", "", ["projectid" => (int) $projectid]);
        while ($data_tasks = mysql_fetch_assoc($result_task)) {
            $data_tasks["timelogs"] = [];
            $result_time = select_query("mod_projecttimes", "", ["taskid" => (int) $data_tasks["id"]]);
            while ($DATA = mysql_fetch_assoc($result_time)) {
                $DATA["starttime"] = date("Y-m-d H:i:s", $DATA["start"]);
                $DATA["endtime"] = date("Y-m-d H:i:s", $DATA["end"]);
                $data_tasks["timelogs"]["timelog"][] = $DATA;
            }
            $apiresults["tasks"]["task"][] = $data_tasks;
        }
        $apiresults["messages"] = [];
        $result_message = select_query("mod_projectmessages", "", ["projectid" => (int) $projectid]);
        while ($DATA_message = mysql_fetch_assoc($result_message)) {
            $apiresults["messages"]["message"][] = $DATA_message;
        }
        $responsetype = "xml";
    }
}
