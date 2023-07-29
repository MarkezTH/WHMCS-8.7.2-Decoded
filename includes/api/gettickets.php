<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (empty($limitstart)) {
    $limitstart = 0;
}
if (empty($limitnum)) {
    $limitnum = 25;
}
$deptid = (int) App::get_req_var("deptid");
$clientid = (int) App::get_req_var("clientid");
$status = App::getFromRequest("status");
$adminUser = WHMCS\User\Admin::getAuthenticatedUser();
$query = WHMCS\Support\Ticket::notMerged();
if ($deptid) {
    $query->where("did", $deptid);
}
if ($clientid) {
    $query->where("userid", (int) $clientid);
}
if (!empty($email)) {
    $query->where(function ($subQuery) use($email) {
        $subQuery->where("email", $email);
        $client = WHMCS\User\Client::where("email", $email)->first();
        if ($client) {
            $subQuery->orWhere("userid", $client->id);
        }
    });
}
if ($status == "Awaiting Reply") {
    $query->awaitingReply();
} else {
    if ($status == "All Active Tickets") {
        $query->active();
    } else {
        if ($status == "My Flagged Tickets") {
            $query->active()->where("flag", $adminUser->id);
        } else {
            if ($status) {
                $query->where("status", $status);
            }
        }
    }
}
if (isset($subject)) {
    $query->where("title", "LIKE", "%" . $subject . "%");
}
if (empty($ignore_dept_assignments) && $adminUser) {
    $departmentIds = $adminUser->getSupportDepartmentIds();
    if (0 < count($departmentIds)) {
        $query->whereIn("did", $departmentIds);
    } else {
        $query->where("did", "0");
    }
}
$totalresults = $query->count();
$apiresults = ["result" => "success", "totalresults" => $totalresults, "startnumber" => $limitstart];
$tickets = $query->orderBy("lastreply", "desc")->offset($limitstart)->limit($limitnum)->get();
$apiresults["numreturned"] = $tickets->count();
foreach ($tickets as $ticket) {
    $apiresults["tickets"]["ticket"][] = $ticket->toArray();
}
$responsetype = "xml";
