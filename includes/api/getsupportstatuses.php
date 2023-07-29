<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$statuses = [];
$result = select_query("tblticketstatuses", "", "", "sortorder", "ASC");
while ($data = mysql_fetch_array($result)) {
    $statuses[$data["title"]]["count"] = 0;
    $statuses[$data["title"]]["color"] = $data["color"];
}
$apiresults = ["result" => "success", "totalresults" => count($statuses), "statuses" => ["status" => []]];
$where = "";
$deptid = (int) App::get_req_var("deptid");
$statusesCountQuery = WHMCS\Database\Capsule::table("tbltickets");
if ($deptid) {
    $statusesCountQuery = $statusesCountQuery->where("did", "=", $deptid);
}
$statusesCountResults = $statusesCountQuery->where("merged_ticket_id", "=", 0)->groupBy("status")->pluck(WHMCS\Database\Capsule::raw("count(id)"), "status")->all();
foreach ($statuses as $status => $dataArray) {
    $count = 0;
    if (isset($statusesCountResults[$status]) && $statusesCountResults[$status]) {
        $count = $statusesCountResults[$status];
    }
    $apiresults["statuses"]["status"][] = ["title" => $status, "count" => $count, "color" => $dataArray["color"]];
}
$responsetype = "xml";
